<?php
// Factory reset support. The heavy lifting behind the reset and restore
// wizards: verifying the reset key, building a full archive before any wipe,
// emptying the data, and re-seeding the fresh default state. Pure functions,
// required by the reset and restore controllers. Nothing here emits output.

declare(strict_types=1);

// ---------------------------------------------------------------------------
// The reset key
// ---------------------------------------------------------------------------

// Whether a reset key hash is configured. With no hash the reset area is off.
function reset_key_configured(): bool
{
    return trim((string)config('reset.key_hash', '')) !== '';
}

// Timing-safe check of an entered key against the stored SHA-256 hash. The key
// is high-entropy, so a hash comparison is adequate here, unlike a password.
function reset_verify_key(string $key): bool
{
    $stored = trim((string)config('reset.key_hash', ''));
    if ($stored === '') {
        return false;
    }
    return hash_equals(strtolower($stored), hash('sha256', $key));
}

// Whether a second administrator must approve a reset before it can execute.
function reset_two_person_required(): bool
{
    return (bool)config('reset.two_person', false);
}

// ---------------------------------------------------------------------------
// Tables and the preserve list
// ---------------------------------------------------------------------------

// Every base table in the current database.
function reset_all_tables(): array
{
    $tables = [];
    $res = db()->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
    sort($tables);
    return $tables;
}

// Tables never truncated by a reset: the reset history and its approvals, and
// the system identity a fresh install would seed identically anyway (the
// permission, role and module catalogues). Preserving these is equivalent to
// re-seeding them and far more robust.
function reset_preserve_tables(): array
{
    return ['reset_log', 'reset_approvals', 'permissions', 'roles', 'role_permissions', 'modules'];
}

// For a scoped reset: the activity and transaction tables that are cleared
// while people, accounts, groups, master data and configuration are kept.
function reset_transactional_tables(): array
{
    return [
        'attendance', 'leave_requests', 'leave_balances',
        'projects', 'tasks', 'task_comments', 'timesheet_entries',
        'tickets', 'ticket_comments', 'bookings',
        'appraisals', 'appraisal_goals', 'review_cycles', 'training_records',
        'tenders', 'tender_boq_items', 'tender_documents', 'tender_evaluation_criteria',
        'tender_go_assessments', 'tender_requirements', 'tender_securities', 'tender_team',
        'opportunities', 'item_requests', 'requisitions',
        'purchase_orders', 'purchase_order_items', 'goods_receipts', 'goods_receipt_items',
        'fund_releases', 'expense_claims', 'expense_lines', 'petty_cash',
        'payroll_runs', 'payslips', 'staff_loans',
        'asset_assignments', 'vehicle_logs',
        'notifications', 'audit_log',
    ];
}

// ---------------------------------------------------------------------------
// Storage for temporary archives
// ---------------------------------------------------------------------------

function reset_storage_dir(): string
{
    $dir = APP_ROOT . '/storage/reset_tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function reset_company_slug(): string
{
    $name = (string)setting('org_name', config('app.name', 'meridian'));
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'meridian';
}

// Every file under storage/uploads, as paths relative to that directory.
function reset_upload_files(): array
{
    $base = rtrim((string)config('uploads.dir', APP_ROOT . '/storage/uploads'), '/');
    if (!is_dir($base)) {
        return [];
    }
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile()) {
            $files[] = ltrim(substr($file->getPathname(), strlen($base)), '/');
        }
    }
    sort($files);
    return $files;
}

// ---------------------------------------------------------------------------
// The archive (backup before wipe)
// ---------------------------------------------------------------------------

// Write a full SQL dump of the given tables to a file on disk, in batches so a
// large table never loads into memory at once. Returns per-table row counts.
function reset_dump_sql(string $path, array $tables): array
{
    $db = db();
    $fh = fopen($path, 'w');
    if ($fh === false) {
        throw new RuntimeException('Could not open a temporary file for the archive.');
    }
    fwrite($fh, "-- Meridian archive SQL dump\n");
    fwrite($fh, '-- Generated ' . gmdate('c') . " UTC\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n");

    $counts = [];
    foreach ($tables as $table) {
        $res = $db->query('SHOW CREATE TABLE `' . $table . '`');
        $create = $res ? ($res->fetch_assoc()['Create Table'] ?? '') : '';
        fwrite($fh, "\n-- Table `$table`\nDROP TABLE IF EXISTS `$table`;\n" . $create . ";\n");

        $counts[$table] = (int)($db->query('SELECT COUNT(*) AS c FROM `' . $table . '`')->fetch_assoc()['c']);
        $offset = 0;
        $batch = 500;
        while (true) {
            $rs = $db->query('SELECT * FROM `' . $table . '` LIMIT ' . $batch . ' OFFSET ' . $offset);
            if (!$rs || $rs->num_rows === 0) {
                break;
            }
            $cols = null;
            $rows = [];
            while ($r = $rs->fetch_assoc()) {
                if ($cols === null) {
                    $cols = array_keys($r);
                }
                $vals = array_map(
                    fn($v) => $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'",
                    array_values($r)
                );
                $rows[] = '(' . implode(',', $vals) . ')';
            }
            if ($rows) {
                $collist = '`' . implode('`,`', $cols) . '`';
                fwrite($fh, "INSERT INTO `$table` ($collist) VALUES\n" . implode(",\n", $rows) . ";\n");
            }
            $seen = $rs->num_rows;
            $offset += $batch;
            if ($seen < $batch) {
                break;
            }
        }
    }
    fwrite($fh, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($fh);
    return $counts;
}

// Build the full archive: a SQL dump of every table, every uploaded file, and a
// manifest with checksums and row counts, packaged as a single zip. Throws on
// any failure so the caller can abort the whole wizard and change nothing.
// Returns ['filename', 'path', 'checksum', 'size', 'manifest'].
function reset_build_archive(string $scope = 'full'): array
{
    $dir = reset_storage_dir();
    $slug = reset_company_slug();
    $stamp = gmdate('Ymd-His');
    $base = $slug . '-' . $stamp;
    $sqlPath = $dir . '/' . $base . '.sql';
    $zipPath = $dir . '/' . $base . '.zip';

    $tables = reset_all_tables();
    $counts = reset_dump_sql($sqlPath, $tables);
    $sqlChecksum = hash_file('sha256', $sqlPath);

    $uploadsBase = rtrim((string)config('uploads.dir', APP_ROOT . '/storage/uploads'), '/');
    $files = reset_upload_files();

    $manifest = [
        'app_version'     => app_version(),
        'schema_version'  => schema_migration_version(),
        'generated_utc'   => gmdate('c'),
        'scope'           => $scope,
        'company'         => (string)setting('org_name', config('app.name', 'Meridian')),
        'sql_filename'    => 'database.sql',
        'sql_checksum'    => $sqlChecksum,
        'row_counts'      => $counts,
        'files'           => $files,
    ];

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($sqlPath);
        throw new RuntimeException('Could not create the archive file.');
    }
    $zip->addFile($sqlPath, 'database.sql');
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    foreach ($files as $rel) {
        $abs = $uploadsBase . '/' . $rel;
        if (is_file($abs)) {
            $zip->addFile($abs, 'uploads/' . $rel);
        }
    }
    if ($zip->close() !== true) {
        @unlink($sqlPath);
        @unlink($zipPath);
        throw new RuntimeException('The archive could not be finalised.');
    }
    @unlink($sqlPath); // only the zip is kept

    $checksum = hash_file('sha256', $zipPath);
    $size = filesize($zipPath);
    if ($checksum === false || $size === false) {
        throw new RuntimeException('The archive could not be verified after building.');
    }

    return [
        'filename' => basename($zipPath),
        'path'     => $zipPath,
        'checksum' => $checksum,
        'size'     => (int)$size,
        'manifest' => $manifest,
    ];
}

// Remove temporary archives older than the given age (default one hour), and
// optionally one specific file, so backups never accumulate on the server.
function reset_cleanup_tmp(int $olderThanSeconds = 3600, ?string $keep = null): void
{
    $dir = reset_storage_dir();
    foreach (glob($dir . '/*') ?: [] as $file) {
        if ($keep !== null && basename($file) === basename($keep)) {
            continue;
        }
        if (is_file($file) && (time() - filemtime($file)) >= $olderThanSeconds) {
            @unlink($file);
        }
    }
}

// ---------------------------------------------------------------------------
// The wipe and the re-seed
// ---------------------------------------------------------------------------

// Truncate every table except the preserve list, and (for a full reset) delete
// every uploaded file. A scoped reset passes only the transactional tables and
// leaves files in place.
function reset_wipe(string $scope = 'full'): void
{
    $db = db();
    if ($scope === 'scoped') {
        $tables = array_values(array_intersect(reset_transactional_tables(), reset_all_tables()));
    } else {
        $preserve = reset_preserve_tables();
        $tables = array_values(array_diff(reset_all_tables(), $preserve));
    }

    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $db->query('TRUNCATE TABLE `' . $table . '`');
    }
    $db->query('SET FOREIGN_KEY_CHECKS = 1');

    if ($scope !== 'scoped') {
        reset_delete_uploads();
    }
}

// Delete every file under storage/uploads, keeping the directory itself.
function reset_delete_uploads(): void
{
    $base = rtrim((string)config('uploads.dir', APP_ROOT . '/storage/uploads'), '/');
    if (!is_dir($base)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isFile() || $item->isLink()) {
            @unlink($item->getPathname());
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        }
    }
}

// Re-seed the fresh default state: module enablement, base settings, the seed
// departments and the protected Administrators and Managers groups. The setup
// flag is left unset so first-run setup runs again. Mirrors what a fresh
// install plus its migrations would produce.
function reset_reseed_defaults(): void
{
    $db = db();

    // Module enablement: core on, optional off.
    $db->query('UPDATE modules SET is_enabled = is_core');

    // Base settings, exactly the fresh-install set. setup_completed is left
    // absent so the instance behaves as freshly installed.
    $settings = [
        'org_name'            => (string)config('app.name', 'Meridian'),
        'late_threshold'      => '08:30',
        'timezone'            => (string)config('app.timezone', 'Africa/Blantyre'),
        'leave_year_start'    => '01-01',
        'cert_reminder_days'  => '90,30,7',
        'cert_notify_manager' => '1',
        'mail_notifications'  => '1',
        'totp_required_admin' => '0',
    ];
    foreach ($settings as $key => $value) {
        db_query('INSERT INTO settings (setting_key, value) VALUES (?,?)', [$key, $value]);
    }

    // Default leave types.
    foreach ([['Annual', 1, 21], ['Sick', 1, 14], ['Maternity', 1, 90], ['Paternity', 1, 10], ['Compassionate', 1, 5], ['Unpaid', 0, 0]] as $lt) {
        db_query('INSERT INTO leave_types (name, is_paid, default_annual_allocation) VALUES (?,?,?)', $lt);
    }

    reset_seed_groups();
}

// Seed the built-in groups after a wipe, mirroring the groups migration: the
// protected Administrators group (all administrator permissions plus the
// system identity permissions), Managers, and the default departments carrying
// the staff baseline.
function reset_seed_groups(): void
{
    db_query(
        "INSERT INTO `groups` (group_key, name, type, is_system, sort_order) VALUES
            ('administrators', 'Administrators', 'access_group', 1, 1),
            ('managers', 'Managers', 'access_group', 0, 2)"
    );
    foreach ([['management', 'Management', 11], ['sales', 'Sales', 12], ['it', 'IT', 13], ['hr', 'HR', 14], ['finance', 'Finance', 15], ['general', 'General', 100]] as $d) {
        db_query("INSERT INTO `groups` (group_key, name, type, is_system, sort_order) VALUES (?,?, 'department', 0, ?)", $d);
    }

    // Administrators: every administrator-role permission, plus the system
    // identity permissions.
    db_query(
        "INSERT INTO group_permissions (group_id, permission_id)
         SELECT g.id, rp.permission_id FROM `groups` g
         JOIN roles r ON r.role_key = 'admin' JOIN role_permissions rp ON rp.role_id = r.id
         WHERE g.group_key = 'administrators'"
    );
    db_query(
        "INSERT IGNORE INTO group_permissions (group_id, permission_id)
         SELECT g.id, p.id FROM `groups` g JOIN permissions p ON p.permission_key IN ('system.admin', 'system.reset')
         WHERE g.group_key = 'administrators'"
    );
    // Managers: the manager-role permissions.
    db_query(
        "INSERT INTO group_permissions (group_id, permission_id)
         SELECT g.id, rp.permission_id FROM `groups` g
         JOIN roles r ON r.role_key = 'manager' JOIN role_permissions rp ON rp.role_id = r.id
         WHERE g.group_key = 'managers'"
    );
    // Departments: the staff baseline so a department-only member keeps access.
    db_query(
        "INSERT INTO group_permissions (group_id, permission_id)
         SELECT g.id, rp.permission_id FROM `groups` g
         JOIN roles r ON r.role_key = 'staff' JOIN role_permissions rp ON rp.role_id = r.id
         WHERE g.type = 'department'"
    );
}

// Recreate the single administrator account after a wipe, in the Administrators
// group with a primary department. TOTP is deliberately not carried over; the
// reset already proved identity, and the admin re-enrols on next login.
// Returns the new user id.
function reset_recreate_admin(string $username, string $email, string $passwordHash, int $mustChange): int
{
    db_query(
        "INSERT INTO people (first_name, last_name, email, job_title, department, employment_status, start_date)
         VALUES ('System', 'Administrator', ?, 'Administrator', 'IT', 'Active', CURDATE())",
        [$email]
    );
    $personId = db_insert_id();

    db_query(
        'INSERT INTO users (username, email, password_hash, person_id, role_id, is_active, must_change_password)
         VALUES (?,?,?,?,NULL,1,?)',
        [$username, $email, $passwordHash, $personId, $mustChange]
    );
    $userId = db_insert_id();

    $adminGroupId = (int)db_val("SELECT id FROM `groups` WHERE group_key = 'administrators'");
    $deptId = (int)db_val("SELECT id FROM `groups` WHERE group_key = 'it' AND type = 'department'");
    if ($adminGroupId) {
        db_query('INSERT INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,0)', [$userId, $adminGroupId]);
    }
    if ($deptId) {
        db_query('INSERT INTO user_groups (user_id, group_id, is_primary) VALUES (?,?,1)', [$userId, $deptId]);
    }
    return $userId;
}

// The seed default administrator password, used by the "factory password"
// option. Matches the fresh-install seed; must_change_password forces a change.
function reset_factory_password(): string
{
    return 'ChangeMe!12345';
}
