<?php
/**
 * Database Connection using PDO
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'clothing_ordering');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Base URL
define('BASE_URL', '/Seller Market/');
define('ADMIN_URL', BASE_URL . 'admin/');
define('CLIENT_URL', BASE_URL . 'client/');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Seller Market/uploads/products/');
define('UPLOAD_URL', BASE_URL . 'uploads/products/');
