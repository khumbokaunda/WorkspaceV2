<?php
// Requires a signed-in, active user. Also enforces the forced first-login
// password change: until it is done, only the password page and logout work.

declare(strict_types=1);

function middleware_auth(?string $arg = null): void
{
    $user = current_user();
    if (!$user) {
        if (is_api_request()) {
            json_err('Your session has ended. Please sign in again.', 401);
        }
        flash('warning', 'Please sign in to continue.');
        redirect('/login');
    }

    if ((int)$user['must_change_password'] === 1) {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $allowed = ['/account/password', '/logout'];
        if (!in_array($path, $allowed, true)) {
            if (is_api_request()) {
                json_err('You must change your password before continuing.', 403);
            }
            redirect('/account/password');
        }
    }
}
