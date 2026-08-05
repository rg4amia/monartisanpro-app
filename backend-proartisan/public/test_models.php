<?php

header('Content-Type: text/plain');

// Manually parse .env
$envFile = dirname(__DIR__) . '/.env';
$geminiKey = '';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2) + [null, null];
        if ($name !== null && trim($name) === 'GEMINI_API_KEY') {
            $geminiKey = trim($value);
            break;
        }
    }
}

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $geminiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "=== Available Models ===\n";
echo "HTTP Code: " . $info['http_code'] . "\n";
echo $response;
