<?php
// module:<key> middleware. Requires the module to be visible to this user
// per module_visibility (role default plus per-user override), so hiding a
// module in the admin screen also blocks direct URL access.

declare(strict_types=1);

function middleware_module(?string $moduleKey = null): void
{
    if ($moduleKey === null || module_visible($moduleKey)) {
        return;
    }
    render_error(403, 'Access denied', 'This area is not available to your account.');
}
