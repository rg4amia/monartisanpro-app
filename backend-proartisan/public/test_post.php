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

$url = "https://gateway.ai.cloudflare.com/v1/e783721275850a879e994e607f4149c0/prosartisan_gateway/google-ai-studio/v1beta/models/gemini-3.5-flash:generateContent?key=" . $geminiKey;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello, answer with exactly one word: Success.']
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$err = curl_error($ch);
curl_close($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "=== POST Test ===\n";
echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Total Time: " . $info['total_time'] . "s\n";
echo "Error: " . $err . "\n";
echo "Response:\n" . $response . "\n\n";
echo "Log:\n" . $verboseLog . "\n";
