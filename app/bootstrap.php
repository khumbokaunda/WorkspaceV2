<?php
// Application boot. Included by public/index.php on every request and by
// the cron scripts. Sets up config, timezone, hardened session, database
// connection and helpers. Nothing here emits output.

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Meridian is not configured. Copy config.example.php to config.php and fill in your values.');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set($GLOBALS['config']['app']['timezone'] ?? 'Africa/Blantyre');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

require APP_ROOT . '/app/helpers/helpers.php';

// Hardened session, only for web requests. Cron scripts define MERIDIAN_CLI.
if (!defined('MERIDIAN_CLI')) {
    $secure = (bool)($GLOBALS['config']['app']['https'] ?? false);
    session_name('meridian_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now  = time();
    $idle = (int)($GLOBALS['config']['app']['session_idle'] ?? 1800);
    $max  = (int)($GLOBALS['config']['app']['session_max'] ?? 43200);

    if (isset($_SESSION['user_id'])) {
        $expired = (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $idle)
                || (isset($_SESSION['login_time'])   && ($now - $_SESSION['login_time'])   > $max);
        if ($expired) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Your session expired. Please sign in again.'];
        }
    }
    $_SESSION['last_activity'] = $now;
}

// Database connection.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $dbc = $GLOBALS['config']['db'];
    $GLOBALS['db'] = new mysqli($dbc['host'], $dbc['user'], $dbc['pass'], $dbc['name'], (int)$dbc['port']);
    $GLOBALS['db']->set_charset($dbc['charset'] ?? 'utf8mb4');
    // Force autocommit on. Some server configurations disable it globally,
    // which would let an INSERT report success and then roll back silently
    // when the connection closes at the end of the request. Each request is
    // its own short-lived connection, so committing per statement is correct.
    $GLOBALS['db']->autocommit(true);
    // Align the database session clock with the application timezone so
    // NOW() and PHP time comparisons agree regardless of the server clock.
    $GLOBALS['db']->query("SET time_zone = '" . (new DateTime())->format('P') . "'");
} catch (mysqli_sql_exception $ex) {
    error_log('DB connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check config.php and the database server.');
}
