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
        // Log the rejection so a token mismatch, which is otherwise silent
        // (it returns a clean 4xx without a database error), is visible in
        // the log. The usual cause is a page cached with an old token.
        error_log(sprintf(
            'CSRF rejected: %s %s | token %s',
            $_SERVER['REQUEST_METHOD'] ?? '?',
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '?',
            $held === '' ? 'no session token' : ($sent === '' ? 'none sent' : 'mismatch')
        ));
        if (is_api_request()) {
            json_err('Security token mismatch. Your page is out of date. Reload it (Ctrl and F5) and try again.', 419);
        }
        flash('danger', 'Security token mismatch. Reload the page and try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
    }
}
