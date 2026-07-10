<?php
// Instance-level module enablement gate. This is the hard gate: a route whose
// module is disabled for this install returns not found, so a disabled module
// leaks nothing, not even that it exists. The separate module visibility
// middleware handles per-role and per-user visibility on top of this.
//
// The router injects this ahead of everything else for any route that belongs
// to an optional module (a route carrying module:<key> or an rbac permission
// that maps to an optional module).

declare(strict_types=1);

function middleware_module_enabled(?string $moduleKey = null): void
{
    if ($moduleKey === null || module_enabled($moduleKey)) {
        return;
    }
    render_error(404, 'Not found', 'The page you are looking for does not exist.');
}
