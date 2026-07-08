<?php
// Authentication: login, logout, forced and voluntary password change,
// self-service password reset. Login failures follow a timing-consistent
// path and every message stays generic, so responses never reveal whether
// an account exists.

declare(strict_types=1);

// Bcrypt hash of the string "meridian-dummy", verified against on unknown
// usernames so the failure path costs the same time as a real one.
const AUTH_DUMMY_HASH = '$2y$12$56RIbffnkI7D3ARGNMpu5uh3IJdovzuMw0Miv4YolgIkYUspWzQka';

function auth_password_ok(string $password, array &$why): bool
{
    $why = [];
    if (mb_strlen($password) < 12) {
        $why[] = 'Use at least 12 characters.';
    }
    $blocklist = ['password', 'password1234', 'meridian12345', 'letmein12345', 'qwerty123456', 'changeme12345', '123456789012'];
    if (in_array(mb_strtolower($password), $blocklist, true)) {
        $why[] = 'That password is too common.';
    }
    return !$why;
}

function login_form(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    render('auth/login', ['pageTitle' => 'Sign in'], false);
}

function login_submit(): void
{
    $username = in_str('username');
    $password = (string)(input()['password'] ?? '');
    $ip = request_ip();

    $user = $username !== ''
        ? db_row('SELECT * FROM users WHERE username = ?', [$username])
        : null;

    $hash = $user['password_hash'] ?? AUTH_DUMMY_HASH;
    $valid = password_verify($password, $hash) && $user && (int)$user['is_active'] === 1;

    db_query(
        'INSERT INTO login_attempts (username, ip_address, successful) VALUES (?,?,?)',
        [mb_substr($username, 0, 60), $ip, $valid ? 1 : 0]
    );

    if (!$valid) {
        flash('danger', 'Those sign in details were not recognized.');
        redirect('/login');
    }

    // Credentials are verified. If two-factor is enrolled, the user is not
    // logged in yet: move to a short-lived pending state and require the code.
    // The pending markers grant no access, since current_user reads user_id
    // which is still unset here.
    if (($user['totp_secret'] ?? null) !== null && $user['totp_secret'] !== '') {
        session_regenerate_id(true);
        unset($_SESSION['user_id']);
        $_SESSION['totp_pending_user'] = (int)$user['id'];
        $_SESSION['totp_pending_at'] = time();
        redirect('/login/verify');
    }

    // Success: fresh session id, fresh CSRF token.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
    csrf_token();

    db_query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);
    audit('login', 'user', (int)$user['id']);

    if ((int)$user['must_change_password'] === 1) {
        redirect('/account/password');
    }
    redirect('/dashboard');
}

// ---------------------------------------------------------------------------
// Two-factor authentication (TOTP), opt-in per user.
//
// The secret and the recovery codes are the sensitive material. The secret is
// only ever echoed on the enrolment screen and never logged or returned in
// JSON elsewhere. Recovery codes are shown once at generation and stored only
// as bcrypt hashes.
// ---------------------------------------------------------------------------

const TOTP_PENDING_TTL = 300; // 5 minutes

// A 160-bit base32 secret, standard for authenticator apps and well within
// the totp_secret column width.
function totp_new_secret(): string
{
    return rtrim(\ParagonIE\ConstantTime\Base32::encodeUpper(random_bytes(20)), '=');
}

// Build a labelled TOTP object for a stored or candidate secret.
function totp_for(string $secret, string $label): \OTPHP\TOTP
{
    $totp = \OTPHP\TOTP::create($secret);
    $totp->setLabel($label);
    $totp->setIssuer((string)setting('org_name', (string)config('app.name', 'Meridian')));
    return $totp;
}

// Verify a 6-digit code against a secret with one step of drift tolerance.
function totp_verify_code(string $secret, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    return \OTPHP\TOTP::create($secret)->verify($code, null, 1);
}

// Ten one-time recovery codes in the form XXXXX-XXXXX, returned as plaintext
// for a single display. Only their bcrypt hashes are ever stored.
function totp_generate_recovery_codes(int $count = 10): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $codes;
}

// Replace a user's recovery codes with a fresh set, returning the plaintext.
function totp_store_recovery_codes(int $userId): array
{
    $codes = totp_generate_recovery_codes(10);
    db_query('DELETE FROM totp_recovery_codes WHERE user_id = ?', [$userId]);
    foreach ($codes as $c) {
        db_query(
            'INSERT INTO totp_recovery_codes (user_id, code_hash) VALUES (?,?)',
            [$userId, password_hash($c, PASSWORD_BCRYPT)]
        );
    }
    return $codes;
}

function totp_recovery_remaining(int $userId): int
{
    return (int)db_val(
        'SELECT COUNT(*) FROM totp_recovery_codes WHERE user_id = ? AND used_at IS NULL',
        [$userId]
    );
}

// Resolve and validate the pending two-factor login, or redirect to /login.
// Returns the pending user row.
function totp_require_pending(): array
{
    $pending = $_SESSION['totp_pending_user'] ?? null;
    if (!$pending) {
        redirect('/login');
    }
    if (time() - (int)($_SESSION['totp_pending_at'] ?? 0) > TOTP_PENDING_TTL) {
        unset($_SESSION['totp_pending_user'], $_SESSION['totp_pending_at']);
        flash('warning', 'That sign in step timed out. Please sign in again.');
        redirect('/login');
    }
    $user = db_row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int)$pending]);
    if (!$user || ($user['totp_secret'] ?? null) === null || $user['totp_secret'] === '') {
        unset($_SESSION['totp_pending_user'], $_SESSION['totp_pending_at']);
        redirect('/login');
    }
    return $user;
}

function totp_challenge_form(): void
{
    totp_require_pending();
    render('auth/totp_verify', ['pageTitle' => 'Two-factor verification'], false);
}

function totp_challenge_submit(): void
{
    $user = totp_require_pending();
    $useRecovery = !empty(input()['use_recovery']);
    $code = preg_replace('/\s+/', '', in_str('code'));
    $ip = request_ip();

    $ok = false;
    $viaRecovery = false;

    if ($useRecovery) {
        $submitted = strtoupper((string)$code);
        foreach (db_all(
            'SELECT id, code_hash FROM totp_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [(int)$user['id']]
        ) as $row) {
            if (password_verify($submitted, $row['code_hash'])) {
                db_query('UPDATE totp_recovery_codes SET used_at = NOW() WHERE id = ?', [(int)$row['id']]);
                $ok = true;
                $viaRecovery = true;
                break;
            }
        }
    } else {
        $ok = totp_verify_code((string)$user['totp_secret'], (string)$code);
    }

    // Log the attempt so the existing throttle protects this step too.
    db_query(
        'INSERT INTO login_attempts (username, ip_address, successful) VALUES (?,?,?)',
        [mb_substr((string)$user['username'], 0, 60), $ip, $ok ? 1 : 0]
    );

    if (!$ok) {
        // Identical message for a wrong TOTP and a wrong recovery code.
        flash('danger', 'That code was not correct.');
        redirect('/login/verify');
    }

    // Full login.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['totp_pending_user'], $_SESSION['totp_pending_at'], $_SESSION['csrf_token']);
    csrf_token();
    db_query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int)$user['id']]);

    if ($viaRecovery) {
        $remaining = totp_recovery_remaining((int)$user['id']);
        audit('two_factor.recovery_used', 'user', (int)$user['id'], ['remaining' => $remaining]);
        flash('warning', 'You signed in with a recovery code. ' . $remaining . ' of your recovery codes remain. Consider regenerating them from the two-factor page.');
    } else {
        audit('two_factor.pass', 'user', (int)$user['id']);
    }

    if ((int)$user['must_change_password'] === 1) {
        redirect('/account/password');
    }
    redirect('/dashboard');
}

function totp_setup_form(): void
{
    $user = current_user();
    $enrolled = ($user['totp_secret'] ?? null) !== null && $user['totp_secret'] !== '';
    $data = [
        'pageTitle' => 'Two-factor authentication',
        'breadcrumbs' => ['Account' => null, 'Two-factor authentication' => null],
        'enrolled' => $enrolled,
    ];
    if ($enrolled) {
        $data['recoveryRemaining'] = totp_recovery_remaining((int)$user['id']);
    } else {
        // A candidate secret, held in the session until a live code confirms it.
        $secret = totp_new_secret();
        $_SESSION['totp_candidate'] = $secret;
        $label = (string)($user['email'] ?: $user['username']);
        $data['secret'] = $secret;
        $data['uri'] = totp_for($secret, $label)->getProvisioningUri();
    }
    render('auth/totp_setup', $data);
}

function totp_enable(): void
{
    $user = current_user();
    if (($user['totp_secret'] ?? null) !== null && $user['totp_secret'] !== '') {
        json_err('Two-factor is already enabled on this account.', 409);
    }
    $candidate = (string)($_SESSION['totp_candidate'] ?? '');
    if ($candidate === '') {
        json_err('Your setup session expired. Reload the page and start again.', 422);
    }
    $code = preg_replace('/\s+/', '', in_str('code'));
    if (!totp_verify_code($candidate, (string)$code)) {
        json_err('That code did not match. Check your authenticator app and try again.', 422, ['code' => 'Incorrect code.']);
    }

    db_query('UPDATE users SET totp_secret = ? WHERE id = ?', [$candidate, (int)$user['id']]);
    unset($_SESSION['totp_candidate']);
    $codes = totp_store_recovery_codes((int)$user['id']);
    audit('two_factor.enabled', 'user', (int)$user['id']);
    json_ok(['recovery_codes' => $codes]);
}

function totp_disable(): void
{
    $user = current_user();
    // Disabling a security control always re-checks identity.
    $password = (string)(input()['password'] ?? '');
    if (!password_verify($password, (string)$user['password_hash'])) {
        json_err('Your password is not correct.', 422, ['password' => 'Not correct.']);
    }
    db_query('UPDATE users SET totp_secret = NULL WHERE id = ?', [(int)$user['id']]);
    db_query('DELETE FROM totp_recovery_codes WHERE user_id = ?', [(int)$user['id']]);
    audit('two_factor.disabled', 'user', (int)$user['id']);
    json_ok();
}

function totp_regenerate_recovery(): void
{
    $user = current_user();
    if (($user['totp_secret'] ?? null) === null || $user['totp_secret'] === '') {
        json_err('Two-factor is not enabled on this account.', 409);
    }
    // Reissuing recovery material re-checks identity, like disabling.
    $password = (string)(input()['password'] ?? '');
    if (!password_verify($password, (string)$user['password_hash'])) {
        json_err('Your password is not correct.', 422, ['password' => 'Not correct.']);
    }
    $codes = totp_store_recovery_codes((int)$user['id']);
    audit('two_factor.recovery_regenerated', 'user', (int)$user['id']);
    json_ok(['recovery_codes' => $codes]);
}

function logout(): void
{
    $user = current_user();
    if ($user) {
        audit('logout', 'user', (int)$user['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('/login');
}

function password_form(): void
{
    $user = current_user();
    render('auth/change_password', [
        'pageTitle' => 'Change password',
        'forced' => (int)$user['must_change_password'] === 1,
    ], (int)$user['must_change_password'] !== 1);
}

function password_submit(): void
{
    $user = current_user();
    $current = (string)(input()['current_password'] ?? '');
    $new     = (string)(input()['new_password'] ?? '');
    $confirm = (string)(input()['confirm_password'] ?? '');

    if (!password_verify($current, $user['password_hash'])) {
        json_err('Your current password is not correct.', 422, ['current_password' => 'Not correct.']);
    }
    $why = [];
    if (!auth_password_ok($new, $why)) {
        json_err(implode(' ', $why), 422, ['new_password' => implode(' ', $why)]);
    }
    if ($new !== $confirm) {
        json_err('The confirmation does not match the new password.', 422, ['confirm_password' => 'Does not match.']);
    }
    if ($new === $current) {
        json_err('Choose a password you have not just used.', 422, ['new_password' => 'Choose a different password.']);
    }

    db_query(
        'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
        [password_hash($new, PASSWORD_BCRYPT), (int)$user['id']]
    );
    session_regenerate_id(true);
    audit('password.change', 'user', (int)$user['id']);
    json_ok(['redirect' => '/dashboard']);
}

function forgot_form(): void
{
    render('auth/forgot_password', ['pageTitle' => 'Reset password'], false);
}

function forgot_submit(): void
{
    $email = in_str('email');
    $user = $email !== '' ? db_row('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]) : null;

    if ($user) {
        $token = bin2hex(random_bytes(32));
        db_query(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
            [(int)$user['id'], hash('sha256', $token)]
        );
        $link = rtrim(config('app.base_url', ''), '/') . '/reset-password/' . $token;
        send_mail(
            $user['email'],
            $user['username'],
            'Password reset',
            '<p>A password reset was requested for your account. The link below works once and expires in 30 minutes.</p>'
            . '<p><a href="' . e($link) . '">Reset your password</a></p>'
            . '<p>If you did not request this, you can ignore this message.</p>'
        );
        audit('password.reset_requested', 'user', (int)$user['id']);
    }
    // Identical response whether or not the account exists.
    flash('info', 'If that email address is registered, a reset link has been sent.');
    redirect('/login');
}

function reset_form(string $token): void
{
    $row = db_row(
        'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [hash('sha256', $token)]
    );
    if (!$row) {
        flash('danger', 'That reset link is not valid or has expired.');
        redirect('/login');
    }
    render('auth/reset_password', ['pageTitle' => 'Choose a new password', 'token' => $token], false);
}

function reset_submit(): void
{
    $token   = in_str('token');
    $new     = (string)(input()['new_password'] ?? '');
    $confirm = (string)(input()['confirm_password'] ?? '');

    $row = db_row(
        'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [hash('sha256', $token)]
    );
    if (!$row) {
        flash('danger', 'That reset link is not valid or has expired.');
        redirect('/login');
    }
    $why = [];
    if (!auth_password_ok($new, $why) || $new !== $confirm) {
        flash('danger', $why ? implode(' ', $why) : 'The two passwords do not match.');
        redirect('/reset-password/' . rawurlencode($token));
    }
    db_query(
        'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
        [password_hash($new, PASSWORD_BCRYPT), (int)$row['user_id']]
    );
    db_query('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int)$row['id']]);
    audit('password.reset_completed', 'user', (int)$row['user_id']);
    flash('success', 'Your password has been changed. Sign in with the new one.');
    redirect('/login');
}
