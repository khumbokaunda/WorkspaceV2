<?php
// Companion restore wizard. Accepts an archive produced by the reset wizard,
// verifies its manifest checksum and version compatibility, replays the SQL and
// restores the uploaded files. Gated as tightly as reset: the restricted
// system.reset permission and the same three-factor gate. A restore replaces
// everything, so the administrator is logged out afterwards.

declare(strict_types=1);

require_once APP_ROOT . '/app/helpers/reset_support.php';
require_once APP_ROOT . '/app/controllers/auth.php';
require_once APP_ROOT . '/app/middleware/throttle.php';

function index(): void
{
    if (!reset_key_configured()) {
        render('admin/reset_disabled', [
            'pageTitle' => 'Restore from Archive',
            'breadcrumbs' => ['Admin' => null, 'Restore' => null],
        ]);
    }
    $user = current_user();
    render('admin/restore', [
        'pageTitle' => 'Restore from Archive',
        'breadcrumbs' => ['Admin' => null, 'Factory Reset' => '/admin/reset', 'Restore' => null],
        'confirmPhrase' => reset_confirm_phrase(),
        'hasTotp' => ($user['totp_secret'] ?? '') !== '',
    ]);
}

// Verify an archive and replay it. Multipart upload plus the three factors and
// the typed confirmation. Nothing is changed until the archive is verified.
function execute(): void
{
    $user = current_user();

    // Typed confirmation.
    if (trim(in_str('confirm_phrase')) !== reset_confirm_phrase()) {
        json_err('The confirmation phrase does not match.', 422, ['confirm_phrase' => 'Does not match.']);
    }
    // Three-factor gate, throttled, exactly as reset.
    $err = '';
    if (!reset_check_factors((string)($_POST['password'] ?? ''), (string)($_POST['totp'] ?? ''), (string)($_POST['reset_key'] ?? ''), $err)) {
        json_err($err, 422);
    }

    // The uploaded archive.
    if (empty($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
        json_err('Choose the archive file to restore.', 422, ['archive' => 'Required.']);
    }
    $tmp = $_FILES['archive']['tmp_name'];
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        json_err('That file is not a readable archive.', 422, ['archive' => 'Not a valid archive.']);
    }
    $manifestRaw = $zip->getFromName('manifest.json');
    $sql = $zip->getFromName('database.sql');
    if ($manifestRaw === false || $sql === false) {
        $zip->close();
        json_err('The archive is missing its manifest or database dump.', 422, ['archive' => 'Incomplete archive.']);
    }
    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest)) {
        $zip->close();
        json_err('The archive manifest is unreadable.', 422, ['archive' => 'Corrupt manifest.']);
    }

    // Integrity: the dump must match the checksum the manifest recorded.
    if (!hash_equals((string)($manifest['sql_checksum'] ?? ''), hash('sha256', $sql))) {
        $zip->close();
        json_err('The archive failed its integrity check and was not restored.', 422, ['archive' => 'Checksum mismatch.']);
    }
    // Compatibility: refuse an archive from a newer schema than this instance.
    $archiveSchema = (int)($manifest['schema_version'] ?? 0);
    if ($archiveSchema > schema_migration_version()) {
        $zip->close();
        json_err('This archive was produced by a newer version and cannot be restored here.', 422, ['archive' => 'Incompatible version.']);
    }

    // Record intent before touching data.
    db_query(
        'INSERT INTO reset_log
            (initiated_user_id, initiated_username, initiated_email, reason_category, reason_text,
             archive_filename, archive_checksum, app_version, ip_address, scope, success)
         VALUES (?,?,?,?,?,?,?,?,?,?,0)',
        [
            (int)$user['id'], (string)$user['username'], (string)($user['email'] ?? null),
            'Restore', 'Restored from archive ' . ($_FILES['archive']['name'] ?? ''),
            (string)($_FILES['archive']['name'] ?? ''), hash('sha256', $sql),
            app_version(), request_ip(), 'restore',
        ]
    );

    try {
        restore_replay_sql($sql);
        restore_files($zip);
    } catch (Throwable $ex) {
        $zip->close();
        error_log('Restore failed: ' . $ex->getMessage());
        json_err('The restore failed partway through. The database may be in a mixed state; restore a known-good archive to recover.', 500);
    }
    $zip->close();

    // Log success into the freshly restored reset_log, so the restore is
    // recorded even though the replay rewrote the table.
    db_query(
        'INSERT INTO reset_log
            (initiated_user_id, initiated_username, initiated_email, reason_category, reason_text,
             archive_filename, archive_checksum, app_version, ip_address, scope, success)
         VALUES (?,?,?,?,?,?,?,?,?,?,1)',
        [
            (int)$user['id'], (string)$user['username'], (string)($user['email'] ?? null),
            'Restore', 'Restored from archive ' . ($_FILES['archive']['name'] ?? ''),
            (string)($_FILES['archive']['name'] ?? ''), hash('sha256', $sql),
            app_version(), request_ip(), 'restore',
        ]
    );

    // The users table has been replaced, so the current session is no longer
    // valid. Log out and send the administrator back to sign in.
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    json_ok(['redirect' => '/login?restored=1']);
}

// Replay a SQL dump. The dump carries its own foreign-key toggles and uses
// escaped values, so multi_query sends the whole script to the server to parse,
// which handles semicolons embedded in string values correctly.
function restore_replay_sql(string $sql): void
{
    $db = db();
    if (!$db->multi_query($sql)) {
        throw new RuntimeException('The database dump could not be replayed: ' . $db->error);
    }
    do {
        if ($res = $db->store_result()) {
            $res->free();
        }
    } while ($db->more_results() && $db->next_result());
    if ($db->errno) {
        throw new RuntimeException('The database dump failed during replay: ' . $db->error);
    }
    // Make sure foreign key checks are back on regardless of the dump.
    $db->query('SET FOREIGN_KEY_CHECKS = 1');
}

// Restore uploaded files from the archive: clear the current uploads, then
// extract every uploads/ entry back to its relative path.
function restore_files(ZipArchive $zip): void
{
    reset_delete_uploads();
    $base = rtrim((string)config('uploads.dir', APP_ROOT . '/storage/uploads'), '/');
    if (!is_dir($base)) {
        mkdir($base, 0750, true);
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || strpos($name, 'uploads/') !== 0 || substr($name, -1) === '/') {
            continue;
        }
        $rel = substr($name, strlen('uploads/'));
        // Guard against path traversal in archive entries.
        if ($rel === '' || strpos($rel, '..') !== false) {
            continue;
        }
        $dest = $base . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
        }
        $stream = $zip->getStream($name);
        if ($stream === false) {
            continue;
        }
        $out = fopen($dest, 'w');
        if ($out !== false) {
            stream_copy_to_stream($stream, $out);
            fclose($out);
        }
        fclose($stream);
    }
}
