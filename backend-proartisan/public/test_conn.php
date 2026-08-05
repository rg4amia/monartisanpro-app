<?php

header('Content-Type: text/plain');

function testUrl($url, $forceIpv4 = false) {
    echo "Testing connection to: $url (Force IPv4: " . ($forceIpv4 ? "YES" : "NO") . ")\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($forceIpv4) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    
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
    echo "--------------------------------------------------\n";
}

testUrl("https://www.google.com");
testUrl("https://www.google.com", true);
testUrl("https://generativelanguage.googleapis.com");
testUrl("https://generativelanguage.googleapis.com", true);
testUrl("https://api.github.com");
testUrl("https://api.github.com", true);
