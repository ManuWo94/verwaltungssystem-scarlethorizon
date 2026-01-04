<?php
/**
 * Configuration file for Department of Justice application
 */

// Database configuration
define('DB_HOST', $_ENV['PGHOST'] ?? 'localhost');
define('DB_USER', $_ENV['PGUSER'] ?? 'postgres');
define('DB_PASS', $_ENV['PGPASSWORD'] ?? 'postgres');
define('DB_NAME', $_ENV['PGDATABASE'] ?? 'justice_db');
define('DB_PORT', $_ENV['PGPORT'] ?? '5432');

// JSON storage path
define('JSON_PATH', __DIR__ . '/../data/');

// Application settings
define('APP_NAME', 'Department of Justice');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_URL', $_ENV['REPL_SLUG'] ? 'https://' . $_ENV['REPL_SLUG'] . '.' . $_ENV['REPL_OWNER'] . '.repl.co' : 'http://localhost:5000');

// File upload settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB

// Session settings
define('SESSION_EXPIRY', 86400); // 24 hours

// Default settings
define('DEFAULT_THEME', 'light');
define('DEFAULT_LANGUAGE', 'de');