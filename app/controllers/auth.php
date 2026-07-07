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
