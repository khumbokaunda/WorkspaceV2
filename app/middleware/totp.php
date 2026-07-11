<?php
// Two-factor enforcement for administrators. When the requirement is on and
// an admin has not enrolled yet, only the enrolment routes and logout are
// allowed; every other request is redirected to the enrolment page. This
// forces enrolment without locking the admin out of the page that fixes it.
//
// The router injects this after auth on every authenticated route, so the
// whitelist below is what prevents a redirect loop; do not remove it.

declare(strict_types=1);

function middleware_totp(?string $arg = null): void
{
    $user = current_user();
    if (!$user) {
        return; // auth middleware handles the unauthenticated case
    }
    if (!user_is_admin($user)) {
        return; // the requirement targets administrators (system.admin holders)
    }
    if (setting('totp_required_admin', '0') !== '1') {
        return;
    }
    if (($user['totp_secret'] ?? null) !== null && $user['totp_secret'] !== '') {
        return; // already enrolled
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $allowed = [
        '/account/two-factor',
        '/account/two-factor/enable',
        '/account/two-factor/disable',
        '/account/two-factor/recovery',
        '/logout',
    ];
    if (in_array($path, $allowed, true)) {
        return;
    }

    if (is_api_request()) {
        json_err('Two-factor authentication is required for administrator accounts. Enrol at /account/two-factor to continue.', 403);
    }
    flash('warning', 'Two-factor authentication is required for administrator accounts. Please enrol to continue.');
    redirect('/account/two-factor');
}
