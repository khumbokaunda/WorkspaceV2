<?php
// Rate limiting for the login and password reset endpoints, backed by the
// login_attempts table. Counts failures per username and per IP inside a
// rolling window. Lockout is progressive and doubles up to a cap, so it slows
// an attacker down without permanently locking real staff out. Administrator
// accounts get a stricter threshold.

declare(strict_types=1);

const THROTTLE_WINDOW_SECONDS = 900;
const THROTTLE_BASE_LOCK      = 30;
const THROTTLE_MAX_LOCK       = 900;
const THROTTLE_FREE_FAILURES  = 5;
const THROTTLE_FREE_FAILURES_ADMIN = 3;
const THROTTLE_FREE_FAILURES_IP    = 20;

function throttle_is_admin_username(string $username): bool
{
    // An administrator is an active member of the Administrators access group.
    return (bool)db_val(
        "SELECT 1 FROM users u
         JOIN user_groups ug ON ug.user_id = u.id
         JOIN `groups` g ON g.id = ug.group_id
         WHERE u.username = ? AND g.group_key = 'administrators'",
        [$username]
    );
}

// Seconds the caller must still wait, or 0 when the attempt may proceed.
function throttle_wait_seconds(string $username, string $ip): int
{
    $since = date('Y-m-d H:i:s', time() - THROTTLE_WINDOW_SECONDS);

    $userFails = 0;
    $lastFail  = null;
    if ($username !== '') {
        $row = db_row(
            'SELECT COUNT(*) AS c, MAX(attempted_at) AS last FROM login_attempts
             WHERE username = ? AND successful = 0 AND attempted_at >= ?',
            [$username, $since]
        );
        $userFails = (int)($row['c'] ?? 0);
        $lastFail  = $row['last'] ?? null;
    }
    $ipRow = db_row(
        'SELECT COUNT(*) AS c, MAX(attempted_at) AS last FROM login_attempts
         WHERE ip_address = ? AND successful = 0 AND attempted_at >= ?',
        [$ip, $since]
    );
    $ipFails = (int)($ipRow['c'] ?? 0);
    if ($ipRow['last'] !== null && ($lastFail === null || $ipRow['last'] > $lastFail)) {
        $lastFail = $ipRow['last'];
    }

    $freeUser = ($username !== '' && throttle_is_admin_username($username))
        ? THROTTLE_FREE_FAILURES_ADMIN : THROTTLE_FREE_FAILURES;

    $overUser = max(0, $userFails - $freeUser);
    $overIp   = max(0, $ipFails - THROTTLE_FREE_FAILURES_IP);
    $over     = max($overUser, $overIp);
    if ($over === 0 || $lastFail === null) {
        return 0;
    }

    $lock = min(THROTTLE_BASE_LOCK * (2 ** ($over - 1)), THROTTLE_MAX_LOCK);
    $readyAt = strtotime($lastFail) + (int)$lock;
    return max(0, $readyAt - time());
}

function middleware_throttle(?string $arg = null): void
{
    $username = trim((string)(input()['username'] ?? ''));
    // The two-factor verify step has no username field. Fall back to the
    // pending user's username (server side, never from the client) so
    // per-username throttling still applies to code guesses.
    if ($username === '' && !empty($_SESSION['totp_pending_user'])) {
        $username = (string)db_val('SELECT username FROM users WHERE id = ?', [(int)$_SESSION['totp_pending_user']]);
    }
    $wait = throttle_wait_seconds($username, request_ip());
    if ($wait <= 0) {
        return;
    }
    $msg = 'Too many attempts. Please wait ' . $wait . ' seconds and try again.';
    if (is_api_request()) {
        json_err($msg, 429);
    }
    flash('danger', $msg);
    redirect($_SERVER['HTTP_REFERER'] ?? '/login');
}
