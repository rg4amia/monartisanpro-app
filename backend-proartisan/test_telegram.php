<?php

/**
 * Script de test Telegram pour ProsArtisan
 * 
 * Usage dans Tinker:
 * php artisan tinker
 * require 'test_telegram.php';
 * 
 * Ou directement:
 * php test_telegram.php
 */

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Test 1: Envoi direct via HTTP (sans passer par le logger)
function testTelegramDirect()
{
    $botToken = env('TELEGRAM_BOT_TOKEN');
    $chatId = env('TELEGRAM_CHAT_ID');
    
    if (blank($botToken) || blank($chatId)) {
        echo "❌ TELEGRAM_BOT_TOKEN ou TELEGRAM_CHAT_ID manquant dans .env\n";
        return false;
    }
    
    echo "🔍 Test envoi direct Telegram...\n";
    echo "Bot Token: " . substr($botToken, 0, 10) . "...\n";
    echo "Chat ID: {$chatId}\n\n";
    
    $lines = [
        '<b>🧪 Test ProsArtisan API</b>',
        '',
        '<b>App:</b> ' . config('app.name'),
        '<b>Env:</b> ' . config('app.env'),
        '<b>Message:</b>',
        '<code>Test d\'envoi Telegram depuis Tinker</code>',
        '<b>Heure UTC:</b> ' . now('UTC')->toDateTimeString(),
    ];
    
    try {
        $response = Http::asForm()
            ->timeout(3)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        
        if ($response->successful()) {
            echo "✅ Message envoyé avec succès !\n";
            echo "Réponse: " . $response->body() . "\n";
            return true;
        } else {
            echo "❌ Échec de l'envoi\n";
            echo "Status: " . $response->status() . "\n";
            echo "Réponse: " . $response->body() . "\n";
            return false;
        }
    } catch (\Throwable $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 2: Envoi via le système de logging Laravel
function testTelegramLogger()
{
    echo "\n🔍 Test via Laravel Logger...\n";
    
    try {
        Log::channel('telegram_bot')->error('Test depuis Tinker', [
            'test' => true,
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        echo "✅ Log envoyé (vérifiez Telegram)\n";
        return true;
    } catch (\Throwable $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 3: Envoi avec exception simulée
function testTelegramWithException()
{
    echo "\n🔍 Test avec exception simulée...\n";
    
    try {
        $exception = new \Exception('Test exception depuis Tinker', 500);
        
        Log::channel('telegram_bot')->error('Erreur de test', [
            'exception' => $exception,
            'user_id' => 123,
            'action' => 'test_tinker',
        ]);
        
        echo "✅ Log avec exception envoyé (vérifiez Telegram)\n";
        return true;
    } catch (\Throwable $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
        return false;
    }
}

// Exécution des tests si appelé directement
if (php_sapi_name() === 'cli' && !defined('LARAVEL_START')) {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  🧪 Tests Telegram ProsArtisan\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    testTelegramDirect();
    testTelegramLogger();
    testTelegramWithException();
    
    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "  ✅ Tests terminés\n";
    echo "═══════════════════════════════════════════════════════════\n";
}
