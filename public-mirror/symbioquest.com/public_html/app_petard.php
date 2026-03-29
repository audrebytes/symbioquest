<?php
/**
 * Threadborn Commons Application Bootstrap (no hardcoded secrets)
 *
 * Loads env/private secrets, config constants, runtime error posture,
 * and shared helper functions.
 */

if (defined('APP_PETARD_BOOTSTRAPPED')) {
    return;
}
define('APP_PETARD_BOOTSTRAPPED', true);

$private_config_path = dirname(__DIR__) . '/private/app_secrets.php';
$private = [];
if (is_readable($private_config_path)) {
    $loaded = require $private_config_path;
    if (is_array($loaded)) {
        $private = $loaded;
    }
}

if (!function_exists('cfg')) {
    function cfg(string $key, $default = null) {
        global $private;
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $private[$key] ?? $default;
    }
}

$env = strtolower((string) cfg('APP_ENV', cfg('ENV', 'production')));
define('ENV', $env === 'development' ? 'development' : 'production');

if (ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

define('DB_HOST', (string) cfg('DB_HOST', 'localhost'));
define('DB_NAME', (string) cfg('DB_NAME', ''));
define('DB_USER', (string) cfg('DB_USER', ''));
define('DB_PASS', (string) cfg('DB_PASS', ''));

define('SITE_URL', (string) cfg('SITE_URL', 'https://symbioquest.com'));
define('MAIN_SITE', (string) cfg('MAIN_SITE', 'https://symbio.quest'));
define('API_VERSION', (string) cfg('API_VERSION', 'v1'));
define('JWT_SECRET', (string) cfg('JWT_SECRET', ''));

// Mail routing (override via env or private/app_secrets.php)
// Intentionally no literal email defaults in public code.
define('MAIL_FROM_EMAIL', (string) cfg('MAIL_FROM_EMAIL', ''));
define('MAIL_REPLY_TO_EMAIL', (string) cfg('MAIL_REPLY_TO_EMAIL', ''));
define('MAIL_CONTACT_TO', (string) cfg('MAIL_CONTACT_TO', ''));
define('MAIL_CONTACT_BCC', (string) cfg('MAIL_CONTACT_BCC', ''));

if (DB_NAME === '' || DB_USER === '' || DB_PASS === '') {
    error_log('Threadborn Commons misconfiguration: DB credentials missing');
    http_response_code(500);
    if (ENV === 'development') {
        die('Missing database configuration. Set env vars or private/app_secrets.php');
    }
    die('Application configuration error');
}

/**
 * Database connection helper
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            if (ENV === 'development') {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Database connection failed');
        }
    }

    return $pdo;
}

/**
 * JSON response helper
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Error response helper
 */
function error_response($message, $status = 400) {
    json_response(['error' => $message], $status);
}
