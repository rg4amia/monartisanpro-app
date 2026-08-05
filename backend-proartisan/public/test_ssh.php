<?php

header('Content-Type: text/plain');

echo "=== SSH Test: Curl Google API ===\n";
$out1 = shell_exec("curl -4 -lv --connect-timeout 15 https://generativelanguage.googleapis.com 2>&1");
echo $out1;

echo "\n\n=== SSH Test: Curl Cloudflare ===\n";
$out2 = shell_exec("curl -4 -lv --connect-timeout 15 https://gateway.ai.cloudflare.com 2>&1");
echo $out2;
