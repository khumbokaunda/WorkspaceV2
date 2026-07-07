<?php
// CSRF verification for all writes. The synchronizer token is minted at login,
// exposed in a meta tag, and attached by app.js to every AJAX write in the
// X-CSRF-Token header. Plain form posts carry it in the _csrf field.

declare(strict_types=1);

function middleware_csrf(?string $arg = null): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    $held = $_SESSION['csrf_token'] ?? '';
    if ($held === '' || !is_string($sent) || !hash_equals($held, $sent)) {
        if (is_api_request()) {
            json_err('Security token mismatch. Refresh the page and try again.', 419);
        }
        flash('danger', 'Security token mismatch. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
    }
}
