<?php

header('Content-Type: text/plain');

echo "=== Test Cloudflare Default ===\n";
$out1 = shell_exec("curl -lv --connect-timeout 5 https://gateway.ai.cloudflare.com 2>&1");
echo $out1;

echo "\n\n=== Test Cloudflare IPv4 ===\n";
$out2 = shell_exec("curl -4 -lv --connect-timeout 5 https://gateway.ai.cloudflare.com 2>&1");
echo $out2;
