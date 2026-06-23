<?php
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $database = parse_url($databaseUrl);
    $host = $database['host'] ?? 'localhost';
    $port = $database['port'] ?? 3306;
    $dbname = ltrim($database['path'] ?? '/carfliphq', '/');
    $username = $database['user'] ?? 'root';
    $password = $database['pass'] ?? '';
} else {
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306;
    $dbname = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'carfliphq';
    $username = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed.');
}
?>
