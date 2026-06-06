<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fixit_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'FixIt');
define('APP_VERSION', '1.0.0');
define('APP_URL', '');  // e.g. https://example.com/fixit

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('CHUNK_DIR', __DIR__ . '/../uploads/chunks/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);

define('SESSION_LIFETIME', 86400); // 24 hours

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

error_reporting(0);
ini_set('display_errors', 0);
