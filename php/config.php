<?php
declare(strict_types=1);

// Load .env file from project root if present
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k);
        $_v = trim($_v, " \t\n\r\0\x0B\"'");
        if (!isset($_ENV[$_k])) {
            putenv("$_k=$_v");
            $_ENV[$_k] = $_v;
        }
    }
    unset($_envFile, $_line, $_k, $_v);
}

// Database
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'STRATOSPHERE');
define('DB_USER',    getenv('DB_USER')    ?: 'STRATOS');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME',     (int)(getenv('SESSION_LIFETIME')     ?: 3600));   // 1h
define('CSRF_TOKEN_LENGTH',    32);

// Login rate-limiting
define('LOGIN_MAX_ATTEMPTS',   (int)(getenv('LOGIN_MAX_ATTEMPTS')   ?: 5));
define('LOGIN_LOCKOUT_SECONDS',(int)(getenv('LOGIN_LOCKOUT_SECONDS')?: 900));    // 15 min

// App
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
