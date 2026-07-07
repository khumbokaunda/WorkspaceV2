<?php
// Front controller. The single web-reachable PHP entry point. Everything else
// lives above the document root and is structurally unreachable over HTTP.

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/app/router.php';

route_dispatch();
