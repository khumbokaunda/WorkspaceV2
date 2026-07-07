<?php
// Server-side authorization. rbac:<permission> requires the effective
// permission (role defaults plus per-user overrides) before the controller
// runs. Hiding a nav link is cosmetic; this check is the real gate.

declare(strict_types=1);

function middleware_rbac(?string $permissionKey = null): void
{
    if ($permissionKey === null || user_can($permissionKey)) {
        return;
    }
    render_error(403, 'Access denied', 'You do not have permission to do that.');
}
