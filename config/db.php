<?php
// Configure these through environment variables or config/local.php (never commit secrets).
$localConfig = __DIR__ . '/local.php';
$local = is_file($localConfig) ? require $localConfig : [];
$DB_HOST = $local['host'] ?? getenv('DOMS_DB_HOST') ?: 'localhost';
$DB_NAME = $local['name'] ?? getenv('DOMS_DB_NAME') ?: 'library_db';
$DB_USER = $local['user'] ?? getenv('DOMS_DB_USER') ?: 'root';
$DB_PASS = $local['pass'] ?? getenv('DOMS_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Doms Library database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the server configuration and PHP error log.');
}
