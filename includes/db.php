<?php
/**
 * Database connection and application paths.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'clothing_ordering');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the database settings and try again.');
}

// Detect the project URL automatically, whether the folder is named
// "Seller Market", "angels-market", or something else under the web root.
$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';

$baseUrl = '/';
if ($documentRoot !== '' && strncasecmp($projectRoot, $documentRoot, strlen($documentRoot)) === 0) {
    $relativePath = str_replace('\\', '/', substr($projectRoot, strlen($documentRoot)));
    $baseUrl = '/' . trim($relativePath, '/') . '/';
    if ($baseUrl === '//') {
        $baseUrl = '/';
    }
}

define('BASE_URL', $baseUrl);
define('ADMIN_URL', BASE_URL . 'admin/');
define('CLIENT_URL', BASE_URL . 'client/');
define('UPLOAD_PATH', $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL . 'uploads/products/');
