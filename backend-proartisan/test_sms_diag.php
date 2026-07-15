<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

echo "=== DIAGNOSTIC OTP / SMS PROSARTISAN ===" . PHP_EOL;
echo PHP_EOL;

// 1. Config base de données
echo "--- BASE DE DONNÉES ---" . PHP_EOL;
$dbDriver  = config('database.default');
$dbHost    = config("database.connections.{$dbDriver}.host", 'N/A');
$dbName    = config("database.connections.{$dbDriver}.database", 'N/A');
echo "Driver : {$dbDriver}" . PHP_EOL;
echo "Host   : {$dbHost}" . PHP_EOL;
echo "DB     : {$dbName}" . PHP_EOL;
echo PHP_EOL;

// 2. Config SMS
echo "--- CONFIG SMS ---" . PHP_EOL;
$provider  = config('services.sms.provider');
$baseUrl   = config('services.sms.base_url');
$token     = config('services.sms.api_token');
echo "Provider  : {$provider}" . PHP_EOL;
echo "Base URL  : {$baseUrl}" . PHP_EOL;
echo "Token (15): " . substr($token, 0, 15) . "..." . PHP_EOL;
echo PHP_EOL;

// 3. Canal OTP configuré (table settings)
echo "--- CANAL OTP (settings) ---" . PHP_EOL;
$channel = \App\Models\Setting::getValueByKey('otp_delivery_channel', 'sms');
echo "Canal actif : {$channel}" . PHP_EOL;
echo PHP_EOL;

// 4. Derniers OTPs en base
echo "--- 5 DERNIERS OTPs EN BASE ---" . PHP_EOL;
$otps = DB::table('otps')->orderByDesc('created_at')->limit(5)
    ->get(['id', 'phone', 'code', 'expires_at', 'used_at', 'created_at']);
foreach ($otps as $o) {
    $status = $o->used_at ? "UTILISÉ" : (strtotime($o->expires_at) < time() ? "EXPIRÉ" : "VALIDE");
    echo "ID:{$o->id} | {$o->phone} | code:{$o->code} | {$status} | créé:{$o->created_at}" . PHP_EOL;
}
echo PHP_EOL;

// 5. Test envoi réel via SmsPro Africa
echo "--- TEST ENVOI SMS (SmsPro Africa) ---" . PHP_EOL;
$smsService = $app->make(\App\Services\SmsService::class);
$testPhone  = '+2250141498409';
$testMsg    = 'Test diagnostic ProsArtisan ' . date('H:i:s');
$result     = $smsService->send($testPhone, $testMsg);
echo "Résultat envoi vers {$testPhone} :" . PHP_EOL;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL;

// 6. Laravel logs OTP récents
echo "--- DERNIÈRES ERREURS LOG OTP ---" . PHP_EOL;
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $otpLines = array_filter($lines, fn($l) => str_contains($l, '[OTP]') || str_contains($l, 'OTP') || str_contains($l, 'smspro'));
    $recent = array_slice($otpLines, -10);
    foreach ($recent as $line) {
        echo trim($line) . PHP_EOL;
    }
} else {
    echo "Log file introuvable : {$logFile}" . PHP_EOL;
}
