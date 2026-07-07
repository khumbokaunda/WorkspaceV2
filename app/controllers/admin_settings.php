<?php
// Admin: application settings and leave types. Every tunable lives here,
// nothing an administrator should adjust is hardcoded.

declare(strict_types=1);

// Live self-check for diagnosing an environment where writes appear to
// succeed but do not persist. Reports the connected database, the autocommit
// state and server details, then performs a real insert, reads it back and
// removes it, so a commit problem or a wrong-database problem shows plainly.
function system_check(): void
{
    $info = [
        'db_host'    => (string)config('db.host'),
        'db_name'    => (string)config('db.name'),
        'db_version' => (string)db_val('SELECT VERSION()'),
        'autocommit' => (string)db_val('SELECT @@autocommit'),
        'sql_mode'   => (string)db_val('SELECT @@sql_mode'),
        'has_autocommit_fix' => str_contains(@file_get_contents(APP_ROOT . '/app/bootstrap.php') ?: '', 'autocommit(true)'),
        'asset_count' => (int)db_val('SELECT COUNT(*) FROM assets'),
        'people_count' => (int)db_val('SELECT COUNT(*) FROM people'),
    ];

    // Round-trip write test on a throwaway tag.
    $tag = 'SYSCHECK-' . bin2hex(random_bytes(3));
    $persisted = false;
    $writeError = null;
    try {
        db_query(
            'INSERT INTO assets (asset_tag, name, category, status) VALUES (?,?,?,?)',
            [$tag, 'System check probe', 'Other', 'Retired']
        );
        $newId = db_insert_id();
        // Read back on a brand new connection so an uncommitted row (the
        // autocommit-off case) reports as not persisted rather than a false pass.
        $dbc = config('db');
        $probe = @new mysqli($dbc['host'], $dbc['user'], $dbc['pass'], $dbc['name'], (int)$dbc['port']);
        if ($probe && !$probe->connect_errno) {
            $stmt = $probe->prepare('SELECT id FROM assets WHERE id = ?');
            $stmt->bind_param('i', $newId);
            $stmt->execute();
            $persisted = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            $probe->close();
        }
        db_query('DELETE FROM assets WHERE asset_tag = ?', [$tag]);
    } catch (Throwable $ex) {
        $writeError = $ex->getMessage();
    }
    $info['write_test'] = $persisted ? 'passed' : 'failed';
    $info['write_error'] = $writeError;
    json_out(['ok' => true, 'check' => $info]);
}

function index(): void
{
    $settings = [];
    foreach (db_all('SELECT setting_key, value FROM settings') as $row) {
        $settings[$row['setting_key']] = $row['value'];
    }
    render('admin/settings', [
        'pageTitle' => 'Settings',
        'breadcrumbs' => ['Admin' => null, 'Settings' => null],
        'settings' => $settings,
        'leaveTypes' => db_all('SELECT * FROM leave_types ORDER BY id'),
    ]);
}

function save(): void
{
    $in = input();
    $editable = [
        'org_name' => fn($v) => $v !== '' && mb_strlen($v) <= 100,
        'late_threshold' => fn($v) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v),
        'timezone' => fn($v) => in_array($v, DateTimeZone::listIdentifiers(), true),
        'leave_year_start' => fn($v) => (bool)preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $v),
        'cert_reminder_days' => fn($v) => (bool)preg_match('/^\d{1,3}(,\d{1,3})*$/', $v),
        'cert_notify_manager' => fn($v) => in_array($v, ['0', '1'], true),
        'mail_notifications' => fn($v) => in_array($v, ['0', '1'], true),
        'totp_required_admin' => fn($v) => in_array($v, ['0', '1'], true),
    ];
    $changed = [];
    foreach ($editable as $key => $isValid) {
        if (!array_key_exists($key, $in)) {
            continue;
        }
        $value = trim((string)$in[$key]);
        if (!$isValid($value)) {
            json_err('The value for ' . str_replace('_', ' ', $key) . ' is not valid.', 422, [$key => 'Not valid.']);
        }
        db_query(
            'INSERT INTO settings (setting_key, value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
        $changed[] = $key;
    }
    audit('settings.update', 'settings', null, ['keys' => $changed]);
    json_ok();
}

// Create or update a leave type. Existing balances keep their allocation;
// the default applies to balances created after the change.
function save_leave_type(): void
{
    $in = input();
    $errors = validate($in, [
        'name' => 'required|max:80',
        'default_annual_allocation' => 'required|numeric',
    ]);
    if ($errors) {
        json_err('Please correct the highlighted fields.', 422, $errors);
    }
    $alloc = (float)$in['default_annual_allocation'];
    if ($alloc < 0 || $alloc > 366) {
        json_err('The allocation must be between 0 and 366.', 422, ['default_annual_allocation' => 'Out of range.']);
    }
    $isPaid = (int)(bool)($in['is_paid'] ?? 1);
    $typeId = in_int('id');

    if ($typeId) {
        if (!db_val('SELECT id FROM leave_types WHERE id = ?', [$typeId])) {
            json_err('That leave type does not exist.', 404);
        }
        $clash = db_val('SELECT id FROM leave_types WHERE name = ? AND id <> ?', [in_str('name'), $typeId]);
        if ($clash) {
            json_err('A leave type with that name already exists.', 422, ['name' => 'Already exists.']);
        }
        db_query(
            'UPDATE leave_types SET name = ?, is_paid = ?, default_annual_allocation = ? WHERE id = ?',
            [in_str('name'), $isPaid, $alloc, $typeId]
        );
        audit('leave_type.update', 'leave_type', $typeId, ['name' => in_str('name'), 'allocation' => $alloc]);
    } else {
        if (db_val('SELECT id FROM leave_types WHERE name = ?', [in_str('name')])) {
            json_err('A leave type with that name already exists.', 422, ['name' => 'Already exists.']);
        }
        db_query(
            'INSERT INTO leave_types (name, is_paid, default_annual_allocation) VALUES (?,?,?)',
            [in_str('name'), $isPaid, $alloc]
        );
        $typeId = db_insert_id();
        audit('leave_type.create', 'leave_type', $typeId, ['name' => in_str('name'), 'allocation' => $alloc]);
    }
    json_ok(['leave_type_id' => $typeId]);
}
