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
        $val = trim($parts[1]);
        if (preg_match('/^["\'](.*)["\']$/', $val, $matches)) {
            $val = $matches[1];
        }
        $env[trim($parts[0])] = $val;
    }
}

$host = isset($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost';
$port = isset($env['DB_PORT']) ? $env['DB_PORT'] : '3306';
$db = isset($env['DB_DATABASE']) ? $env['DB_DATABASE'] : '';

echo "Live .env DB configs:<br>";
foreach ($lines as $line) {
    if (strpos(trim($line), 'DB_') === 0) {
        $parts = explode('=', $line, 2);
        if ($parts[0] === 'DB_PASSWORD') {
            echo "DB_PASSWORD=***** (length: " . strlen($parts[1]) . ")<br>";
        } else {
            echo htmlspecialchars($line) . "<br>";
        }
    }
}
echo "<br>";
$user = isset($env['DB_USERNAME']) ? $env['DB_USERNAME'] : '';
$pass = isset($env['DB_PASSWORD']) ? $env['DB_PASSWORD'] : '';
$hostsToTry = ['127.0.0.1', 'localhost'];
$connected = false;
$pdo = null;

foreach ($hostsToTry as $tryHost) {
    echo "Connecting to $db on $tryHost:$port as $user...<br>";
    try {
        $pdo = new PDO("mysql:host=$tryHost;port=$port;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully via $tryHost!<br>";
        $connected = true;
        break;
    } catch (Exception $e) {
        echo "Failed via $tryHost: " . $e->getMessage() . "<br>";
    }
}

if ($connected && $pdo) {
    try {
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
        echo "ERROR running statement: " . $e->getMessage() . "<br>";
    }
} else {
    echo "Could not connect using any host.<br>";
}
