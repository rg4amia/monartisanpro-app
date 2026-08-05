<?php

header('Content-Type: text/plain');

function testHost($url) {
    echo "Testing: $url ... ";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $time = microtime(true) - $start;
    
    if (curl_errno($ch)) {
        echo "FAIL: " . curl_error($ch) . " in " . round($time, 3) . "s\n";
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "SUCCESS: HTTP $httpCode in " . round($time, 3) . "s\n";
    }
    curl_close($ch);
}

testHost("https://www.google.com");
testHost("https://api.github.com");
testHost("https://api.openai.com");
testHost("https://generativelanguage.googleapis.com");
testHost("https://huggingface.co");
