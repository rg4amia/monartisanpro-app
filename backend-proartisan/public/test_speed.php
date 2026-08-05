<?php

header('Content-Type: text/plain');

function test_url($url, $ipv4 = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6); // Max transfer time 6s
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Max connect time 3s
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Log verbose output to a string stream
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    if ($ipv4) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    } else {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
    }
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    return [
        'info' => $info,
        'error' => $err,
        'log' => $verboseLog,
        'response_len' => strlen($response)
    ];
}

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

echo "=== Test Cloudflare IPv4 ===\n";
$r4 = test_url($url, true);
echo "Connect Time: " . $r4['info']['connect_time'] . "s\n";
echo "Total Time: " . $r4['info']['total_time'] . "s\n";
echo "HTTP Code: " . $r4['info']['http_code'] . "\n";
echo "Error: " . $r4['error'] . "\n";
echo "Response Length: " . $r4['response_len'] . "\n";
echo "Log:\n" . $r4['log'] . "\n\n";

echo "=== Test Cloudflare IPv6 ===\n";
$r6 = test_url($url, false);
echo "Connect Time: " . $r6['info']['connect_time'] . "s\n";
echo "Total Time: " . $r6['info']['total_time'] . "s\n";
echo "HTTP Code: " . $r6['info']['http_code'] . "\n";
echo "Error: " . $r6['error'] . "\n";
echo "Response Length: " . $r6['response_len'] . "\n";
echo "Log:\n" . $r6['log'] . "\n\n";
