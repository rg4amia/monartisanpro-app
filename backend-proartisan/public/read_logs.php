<?php
$logFile = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logFile)) {
    $content = file($logFile);
    $lastLines = array_slice($content, -100);
    echo "<pre>" . htmlspecialchars(implode("", $lastLines)) . "</pre>";
} else {
    echo "Log file does not exist at: " . htmlspecialchars($logFile);
}
