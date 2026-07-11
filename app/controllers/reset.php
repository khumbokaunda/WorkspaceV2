<?php
// Factory reset wizard. The most destructive action in the system, so almost
// all of this is about preventing accidental or unauthorized use: a restricted
// permission, a three-factor gate (password, current TOTP code, and the reset
// key), a full verified archive taken before any wipe, a typed confirmation,
// and an audit record that survives the wipe. Every step is server validated
// and the destructive step is unreachable until every prior step is satisfied.

declare(strict_types=1);

require_once APP_ROOT . '/app/helpers/reset_support.php';
require_once APP_ROOT . '/app/controllers/auth.php';
require_once APP_ROOT . '/app/middleware/throttle.php';

function reset_reason_categories(): array
{
    return ['Testing', 'Decommission', 'Handover to New Owner', 'Data Corruption Recovery', 'Other'];
}

// The wizard state carried in the session, with safe defaults.
function reset_wiz(): array
{
    return $_SESSION['reset_wiz'] ?? [
        'step'        => 1,
        'reason'      => null,
        'authed_at'   => null,
        'archive'     => null,
        'saved'       => false,
        'pw_choice'   => null,
        'new_password'=> null,
    ];
}

function reset_wiz_save(array $state): void
{
    $_SESSION['reset_wiz'] = $state;
}

function reset_wiz_clear(): void
{
    unset($_SESSION['reset_wiz']);
}

function index(): void
{
    if (!reset_key_configured()) {
        render('admin/reset_disabled', [
            'pageTitle' => 'Factory Reset',
            'breadcrumbs' => ['Admin' => null, 'Factory Reset' => null],
        ]);
    }
    $user = current_user();
    $state = reset_wiz();
    $approval = reset_pending_approval();
    $approvalRequester = null;
    if ($approval) {
        $approvalRequester = (string)db_val('SELECT username FROM users WHERE id = ?', [(int)$approval['requested_by']]);
    }
    render('admin/reset', [
        'pageTitle' => 'Factory Reset',
        'breadcrumbs' => ['Admin' => null, 'Factory Reset' => null],
        'state' => $state,
        'categories' => reset_reason_categories(),
        'confirmPhrase' => reset_confirm_phrase(),
        'hasTotp' => ($user['totp_secret'] ?? '') !== '',
        'twoPerson' => reset_two_person_required(),
        'pendingApproval' => $approval,
        'approvalRequester' => $approvalRequester,
        'currentUserId' => (int)$user['id'],
    ]);
}

// The read-only reset history. Durable across resets, since reset_log is on
// the preserve list.
function history(): void
{
    render('admin/reset_history', [
        'pageTitle' => 'Reset History',
        'breadcrumbs' => ['Admin' => null, 'Factory Reset' => '/admin/reset', 'History' => null],
        'entries' => db_all('SELECT * FROM reset_log ORDER BY id DESC LIMIT 200'),
    ]);
}

// Step 1: the acknowledgements.
function step_understand(): void
{
    $in = input();
    foreach (['ack_erase', 'ack_undo', 'ack_users'] as $ack) {
        if (empty($in[$ack])) {
            json_err('Please confirm every acknowledgement to continue.', 422);
        }
    }
    $state = reset_wiz();
    $state['step'] = max($state['step'], 2);
    reset_wiz_save($state);
    json_ok(['step' => 2]);
}

// Step 2: the reason.
function step_reason(): void
{
    $category = in_str('reason_category');
    $text = in_str('reason_text');
    if (!in_array($category, reset_reason_categories(), true)) {
        json_err('Choose a reason category.', 422, ['reason_category' => 'Required.']);
    }
    if (mb_strlen($text) < 5) {
        json_err('Give a short explanation of why the reset is happening.', 422, ['reason_text' => 'Please explain.']);
    }
    $state = reset_wiz();
    $state['reason'] = ['category' => $category, 'text' => $text];
    $state['step'] = max($state['step'], 3);
    reset_wiz_save($state);
    json_ok(['step' => 3]);
}

// Step 3: authenticate with all three factors.
function step_authenticate(): void
{
    $state = reset_wiz();
    if ($state['step'] < 3) {
        json_err('Complete the earlier steps first.', 409);
    }
    $err = '';
    if (!reset_check_factors(in_str('password'), in_str('totp'), in_str('reset_key'), $err)) {
        json_err($err, 422);
    }
    $state['authed_at'] = time();
    $state['step'] = max($state['step'], 4);
    reset_wiz_save($state);
    audit('reset.authenticated', 'reset', null, ['reason' => $state['reason']['category'] ?? null]);
    json_ok(['step' => 4]);
}

// Step 4a: build the archive. Aborts the wizard on any failure so no wipe can
// proceed without a verified backup.
function step_backup(): void
{
    $state = reset_wiz();
    if (empty($state['authed_at']) || $state['step'] < 4) {
        json_err('Authenticate before generating the backup.', 409);
    }
    try {
        reset_cleanup_tmp(0, null); // clear any previous temp archives first
        $archive = reset_build_archive('full');
    } catch (Throwable $ex) {
        error_log('Reset archive build failed: ' . $ex->getMessage());
        json_err('The backup could not be generated, so nothing was changed. ' . $ex->getMessage(), 500);
    }
    $state['archive'] = [
        'filename' => $archive['filename'],
        'checksum' => $archive['checksum'],
        'size'     => $archive['size'],
    ];
    $state['saved'] = false;
    reset_wiz_save($state);
    json_ok([
        'filename' => $archive['filename'],
        'checksum' => $archive['checksum'],
        'size'     => $archive['size'],
    ]);
}

// Step 4b: stream the built archive to the browser.
function download(): void
{
    $state = reset_wiz();
    $archive = $state['archive'] ?? null;
    if (!$archive) {
        render_error(404, 'No backup', 'There is no backup to download. Generate it first.');
    }
    $path = reset_storage_dir() . '/' . basename($archive['filename']);
    if (!is_file($path)) {
        render_error(404, 'Backup expired', 'The backup file is no longer available. Generate it again.');
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($archive['filename']) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    readfile($path);
    exit;
}

// Step 4c: confirm the archive is saved, re-checking the checksum the admin
// read from the download.
function step_backup_confirm(): void
{
    $state = reset_wiz();
    $archive = $state['archive'] ?? null;
    if (!$archive) {
        json_err('Generate and download the backup first.', 409);
    }
    if (empty(input()['saved'])) {
        json_err('Confirm that you have saved the backup.', 422, ['saved' => 'Required.']);
    }
    $entered = strtolower(trim(in_str('checksum')));
    if (!hash_equals(strtolower($archive['checksum']), $entered)) {
        json_err('The checksum does not match the downloaded backup.', 422, ['checksum' => 'Does not match.']);
    }
    $state['saved'] = true;
    $state['step'] = max($state['step'], 5);
    reset_wiz_save($state);
    json_ok(['step' => 5]);
}

// Step 5: the post-reset administrator password choice.
function step_password(): void
{
    $state = reset_wiz();
    if (empty($state['saved']) || $state['step'] < 5) {
        json_err('Save the backup before choosing the password option.', 409);
    }
    $choice = in_str('pw_choice');
    if (!in_array($choice, ['keep', 'new', 'factory'], true)) {
        json_err('Choose a password option.', 422, ['pw_choice' => 'Required.']);
    }
    $newPassword = null;
    if ($choice === 'new') {
        $newPassword = (string)(input()['new_password'] ?? '');
        $why = [];
        if (!auth_password_ok($newPassword, $why)) {
            json_err(implode(' ', $why), 422, ['new_password' => implode(' ', $why)]);
        }
    }
    $state['pw_choice'] = $choice;
    $state['new_password'] = $newPassword;
    $state['step'] = max($state['step'], 6);
    reset_wiz_save($state);
    json_ok(['step' => 6]);
}

// Step 7: execute. Re-verifies the three factors and the typed phrase at the
// moment of execution, runs the reset, and logs the administrator out.
function execute(): void
{
    $state = reset_wiz();
    if ($state['step'] < 6 || empty($state['saved']) || empty($state['authed_at']) || empty($state['archive'])) {
        json_err('The reset is not ready. Complete every step first.', 409);
    }
    if (empty($state['reason'])) {
        json_err('The reason is missing. Restart the wizard.', 409);
    }
    // Typed confirmation phrase.
    if (trim(in_str('confirm_phrase')) !== reset_confirm_phrase()) {
        json_err('The confirmation phrase does not match.', 422, ['confirm_phrase' => 'Does not match.']);
    }
    // Re-verify all three factors at the moment of execution.
    $err = '';
    if (!reset_check_factors(in_str('password'), in_str('totp'), in_str('reset_key'), $err)) {
        json_err($err, 422);
    }
    // Two-person rule, if configured.
    if (reset_two_person_required()) {
        $approval = reset_pending_approval();
        if (!$approval || $approval['status'] !== 'approved') {
            json_err('A second administrator must approve this reset before it can run.', 403);
        }
    }

    $user = current_user();
    $scope = 'full';

    // 1. Record the intent first, so a record exists even if a later step fails.
    db_query(
        'INSERT INTO reset_log
            (initiated_user_id, initiated_username, initiated_email, reason_category, reason_text,
             archive_filename, archive_checksum, archive_size_bytes, app_version, ip_address, scope, success)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,0)',
        [
            (int)$user['id'], (string)$user['username'], (string)($user['email'] ?? null),
            (string)$state['reason']['category'], (string)$state['reason']['text'],
            (string)$state['archive']['filename'], (string)$state['archive']['checksum'], (int)$state['archive']['size'],
            app_version(), request_ip(), $scope,
        ]
    );
    $logId = db_insert_id();

    // 2. Capture what is needed to recreate the administrator, and the current
    // administrator addresses to notify (before the users table is wiped).
    $username = (string)$user['username'];
    $email = (string)($user['email'] ?? '');
    $keepHash = (string)$user['password_hash'];
    $adminEmails = array_map(
        fn($r) => (string)$r['email'],
        db_all(
            "SELECT DISTINCT u.email FROM users u
             JOIN user_groups ug ON ug.user_id = u.id
             JOIN `groups` g ON g.id = ug.group_id
             WHERE g.group_key = 'administrators' AND u.email <> ''"
        )
    );

    try {
        // 4. Wipe.
        reset_wipe($scope);
        // 5. Re-seed defaults.
        reset_reseed_defaults();
        // 6. Recreate the single administrator per the chosen password option.
        [$hash, $mustChange] = reset_resolve_admin_password($state, $keepHash);
        reset_recreate_admin($username, $email, $hash, $mustChange);
        // Mark the pending approval used, if any.
        if (reset_two_person_required()) {
            db_query("UPDATE reset_approvals SET status = 'used' WHERE status = 'approved'");
        }
        // 7. Success.
        db_query('UPDATE reset_log SET success = 1 WHERE id = ?', [$logId]);
    } catch (Throwable $ex) {
        error_log('Reset execution failed after intent logged: ' . $ex->getMessage());
        db_query('UPDATE reset_log SET notes = ? WHERE id = ?', [substr($ex->getMessage(), 0, 60000), $logId]);
        json_err('The reset failed partway through. Your archive is safe and can be restored. Reference is in the reset log.', 500);
    }

    // Notify the original administrators by email, if mail is configured.
    reset_email_admins($username, $state['reason'], $adminEmails);

    // 8. Destroy the session and log out.
    reset_cleanup_tmp(0, null);
    reset_wiz_clear();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    json_ok(['redirect' => '/login?reset=1']);
}

// Resolve the recreated administrator's password hash and forced-change flag
// from the wizard's password choice.
function reset_resolve_admin_password(array $state, string $keepHash): array
{
    switch ($state['pw_choice']) {
        case 'new':
            return [password_hash((string)$state['new_password'], PASSWORD_BCRYPT), 0];
        case 'factory':
            return [password_hash(reset_factory_password(), PASSWORD_BCRYPT), 1];
        case 'keep':
        default:
            return [$keepHash, 0];
    }
}

// Email the given administrator addresses that a reset occurred (best effort).
// The addresses are captured before the wipe so the original administrators are
// reached, not just the recreated one.
function reset_email_admins(string $actor, array $reason, array $emails): void
{
    try {
        $when = date('j M Y H:i');
        $body = '<p>A factory reset was performed on this instance.</p>'
            . '<p><strong>By:</strong> ' . e($actor) . '<br>'
            . '<strong>When:</strong> ' . e($when) . '<br>'
            . '<strong>Reason:</strong> ' . e($reason['category'] ?? '') . ' - ' . e($reason['text'] ?? '') . '</p>';
        foreach (array_unique($emails) as $addr) {
            if ($addr !== '') {
                send_mail((string)$addr, 'Administrator', 'A factory reset was performed', $body);
            }
        }
    } catch (Throwable $ex) {
        error_log('Reset admin email failed: ' . $ex->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Scoped reset: clears activity and transactions while keeping people,
// accounts, groups, master data and configuration. Lighter than a full reset
// but still takes a verified backup and re-authenticates.
// ---------------------------------------------------------------------------

function scoped_execute(): void
{
    $user = current_user();
    $err = '';
    // Two factors here (password and TOTP), plus the typed phrase. No full wipe,
    // so the reset key is not required, but identity still is.
    if (($user['totp_secret'] ?? '') === '') {
        json_err('Enrol in two-factor authentication before clearing data.', 422);
    }
    $ip = request_ip();
    $wait = throttle_wait_seconds((string)$user['username'], $ip);
    if ($wait > 0) {
        json_err('Too many attempts. Please wait ' . $wait . ' seconds and try again.', 429);
    }
    $ok = password_verify(in_str('password'), (string)$user['password_hash'])
        && totp_verify_code((string)$user['totp_secret'], preg_replace('/\D/', '', in_str('totp')));
    db_query('INSERT INTO login_attempts (username, ip_address, successful) VALUES (?,?,?)', [(string)$user['username'], $ip, $ok ? 1 : 0]);
    if (!$ok) {
        json_err('The password or code did not match. This attempt has been recorded.', 422);
    }
    if (trim(in_str('confirm_phrase')) !== reset_confirm_phrase()) {
        json_err('The confirmation phrase does not match.', 422, ['confirm_phrase' => 'Does not match.']);
    }

    try {
        reset_cleanup_tmp(0, null);
        $archive = reset_build_archive('scoped');
    } catch (Throwable $ex) {
        error_log('Scoped reset archive failed: ' . $ex->getMessage());
        json_err('The backup could not be generated, so nothing was changed.', 500);
    }
    db_query(
        'INSERT INTO reset_log
            (initiated_user_id, initiated_username, initiated_email, reason_category, reason_text,
             archive_filename, archive_checksum, archive_size_bytes, app_version, ip_address, scope, success)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,0)',
        [
            (int)$user['id'], (string)$user['username'], (string)($user['email'] ?? null),
            'Scoped', in_str('reason_text') ?: 'Cleared transactional data',
            (string)$archive['filename'], (string)$archive['checksum'], (int)$archive['size'],
            app_version(), $ip, 'scoped',
        ]
    );
    $logId = db_insert_id();
    try {
        reset_wipe('scoped');
        db_query('UPDATE reset_log SET success = 1 WHERE id = ?', [$logId]);
    } catch (Throwable $ex) {
        error_log('Scoped reset failed: ' . $ex->getMessage());
        db_query('UPDATE reset_log SET notes = ? WHERE id = ?', [substr($ex->getMessage(), 0, 60000), $logId]);
        json_err('The scoped reset failed partway through. Your archive is safe.', 500);
    }
    reset_cleanup_tmp(0, null);
    audit('reset.scoped', 'reset', $logId);
    json_ok(['message' => 'Transactional data has been cleared.']);
}

// ---------------------------------------------------------------------------
// Two-person approval
// ---------------------------------------------------------------------------

// The current pending or approved request, if any.
function reset_pending_approval(): ?array
{
    return db_row("SELECT * FROM reset_approvals WHERE status IN ('pending','approved') ORDER BY id DESC LIMIT 1");
}

// Create an approval request for the current reason (used when two-person is on).
function request_approval(): void
{
    if (!reset_two_person_required()) {
        json_err('Two-person approval is not enabled.', 409);
    }
    $state = reset_wiz();
    if (empty($state['reason'])) {
        json_err('Record the reason before requesting approval.', 409);
    }
    $user = current_user();
    // One open request at a time.
    if (reset_pending_approval()) {
        json_err('There is already an open reset request awaiting approval.', 409);
    }
    db_query(
        'INSERT INTO reset_approvals (requested_by, reason_category, reason_text, scope) VALUES (?,?,?,?)',
        [(int)$user['id'], (string)$state['reason']['category'], (string)$state['reason']['text'], 'full']
    );
    audit('reset.approval_requested', 'reset', db_insert_id());
    json_ok();
}

// A second administrator approves the open request. The requester cannot
// approve their own request.
function approve(string $id): void
{
    $reqId = (int)$id;
    $req = db_row("SELECT * FROM reset_approvals WHERE id = ? AND status = 'pending'", [$reqId]);
    if (!$req) {
        json_err('That request does not exist or is not pending.', 404);
    }
    $user = current_user();
    if ((int)$req['requested_by'] === (int)$user['id']) {
        json_err('A reset must be approved by a different administrator.', 403);
    }
    db_query(
        "UPDATE reset_approvals SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?",
        [(int)$user['id'], $reqId]
    );
    audit('reset.approved', 'reset', $reqId);
    json_ok();
}

// Cancel the open request.
function cancel_approval(string $id): void
{
    $reqId = (int)$id;
    db_query("UPDATE reset_approvals SET status = 'cancelled' WHERE id = ? AND status IN ('pending','approved')", [$reqId]);
    audit('reset.approval_cancelled', 'reset', $reqId);
    json_ok();
}
