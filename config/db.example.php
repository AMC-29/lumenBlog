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
    // Never leak credentials or raw DB errors to visitors.
    http_response_code(500);
    die('Database connection failed. Please check config/db.php and try again.');
}
