<?php
/**
 * Database connection (PDO + MySQL)
 * -------------------------------------------------
 * Edit the four constants below to match your host's database
 * credentials. On free hosts (InfinityFree, 000webhost, etc.)
 * these are shown in your control panel under "MySQL Databases".
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'lumen_blog');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_URL', 'http://localhost/lumen-blog');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    if (str_contains(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/api/')) {
        header('Content-Type: application/json; charset=UTF-8');
        die('{"ok":false,"error":"A server error occurred."}');
    }
    die('Database connection failed. Please check config/db.php and try again.');
}
