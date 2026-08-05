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

function test_model($model, $key) {
    $url = "https://gateway.ai.cloudflare.com/v1/e783721275850a879e994e607f4149c0/prosartisan_gateway/google-ai-studio/v1beta/models/{$model}:generateContent?key=" . $key;
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Hello. Answer with exactly one word: Success.']
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
        'User-Agent: Mozilla/5.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [
        'code' => $info['http_code'],
        'time' => $info['total_time'],
        'body' => $response
    ];
}

$models = ['gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-3.5-flash'];

foreach ($models as $m) {
    echo "=== Test Model: {$m} ===\n";
    $res = test_model($m, $geminiKey);
    echo "HTTP Code: " . $res['code'] . "\n";
    echo "Time: " . $res['time'] . "s\n";
    echo "Response: " . substr(trim($res['body']), 0, 300) . "\n\n";
}
