<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parse the production .env file
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die("No .env file found at: " . $envFile);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    $parts = explode('=', $line, 2);
    if (count($parts) === 2) {
        $env[trim($parts[0])] = trim($parts[1]);
    }
}

$host = isset($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost';
$port = isset($env['DB_PORT']) ? $env['DB_PORT'] : '3306';
$db = isset($env['DB_DATABASE']) ? $env['DB_DATABASE'] : '';
$user = isset($env['DB_USERNAME']) ? $env['DB_USERNAME'] : '';
$pass = isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '';

// Hostinger DB_HOST optimization
if ($host === '127.0.0.1') {
    $host = 'localhost';
}

echo "Connecting to $db on $host:$port as $user...<br>";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully!<br>";
    
    // Check if deleted_at exists
    $q = $pdo->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
    $col = $q->fetch(PDO::FETCH_ASSOC);
    if ($col) {
        echo "Column deleted_at already exists!<br>";
    } else {
        echo "Adding deleted_at column...<br>";
        $pdo->exec("ALTER TABLE users ADD deleted_at TIMESTAMP NULL DEFAULT NULL");
        echo "Column deleted_at added successfully!<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}
