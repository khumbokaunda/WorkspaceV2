<?php
// Router. Matches method plus path against the route table, runs the route's
// middleware pipeline in order, then dispatches to the controller function.

declare(strict_types=1);

function route_dispatch(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path   = rtrim($path, '/') ?: '/';

    // Method override for HTML forms that need PATCH or DELETE.
    if ($method === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper((string)$_POST['_method']);
        if (in_array($override, ['PATCH', 'DELETE'], true)) {
            $method = $override;
        }
    }

    $routes = require APP_ROOT . '/app/config/routes.php';
    $pathMatched = false;

    foreach ($routes as [$routeMethod, $routePath, $handler, $middleware]) {
        $pattern = preg_replace('/\{token\}/', '([A-Za-z0-9_\-]+)', $routePath);
        $pattern = preg_replace('/\{[a-z]+\}/', '(\d+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $path, $matches)) {
            continue;
        }
        $pathMatched = true;
        if ($routeMethod !== $method) {
            continue;
        }

        $params = array_slice($matches, 1);

        // Every authenticated route also enforces the two-factor requirement
        // for admins. The totp middleware whitelists the enrolment routes and
        // logout, so injecting it after auth everywhere creates no loop.
        if (in_array('auth', $middleware, true) && !in_array('totp', $middleware, true)) {
            $pos = (int)array_search('auth', $middleware, true);
            array_splice($middleware, $pos + 1, 0, 'totp');
        }

        foreach ($middleware as $mw) {
            [$mwName, $mwArg] = array_pad(explode(':', $mw, 2), 2, null);
            $mwFile = APP_ROOT . '/app/middleware/' . $mwName . '.php';
            require_once $mwFile;
            $mwFn = 'middleware_' . $mwName;
            $mwFn($mwArg); // Middleware short-circuits with redirect or json_err on failure.
        }

        [$controllerFile, $fn] = explode('@', $handler, 2);
        require_once APP_ROOT . '/app/controllers/' . $controllerFile . '.php';
        $fn(...$params);
        return;
    }

    if ($pathMatched) {
        render_error(405, 'Method not allowed', 'That method is not allowed for this address.');
    }
    render_error(404, 'Not found', 'The page you are looking for does not exist.');
}
