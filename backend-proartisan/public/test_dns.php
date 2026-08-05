<?php

header('Content-Type: text/plain');

$host = 'generativelanguage.googleapis.com';
echo "Resolving host: $host\n";

$start = microtime(true);
$ip = gethostbyname($host);
$time = microtime(true) - $start;

if ($ip === $host) {
    echo "FAIL: Could not resolve DNS in " . round($time, 3) . "s\n";
} else {
    echo "SUCCESS: Resolved to IP $ip in " . round($time, 3) . "s\n";
}
