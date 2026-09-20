#!/usr/bin/env php
<?php

/**
 * Script pour récupérer votre TELEGRAM_CHAT_ID
 *
 * Usage:
 * 1. Envoyez un message à votre bot dans Telegram
 * 2. Exécutez : php get_chat_id.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  📱 Récupération du TELEGRAM_CHAT_ID\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$botToken = env('TELEGRAM_BOT_TOKEN');

if (blank($botToken)) {
 echo "❌ TELEGRAM_BOT_TOKEN manquant dans .env\n\n";
 exit(1);
}

echo "🔍 Bot Token trouvé : " . substr($botToken, 0, 15) . "...\n\n";

// Vérifier que le bot existe
echo "📡 Vérification du bot...\n";
try {
 $response = Http::get("https://api.telegram.org/bot{$botToken}/getMe");

 if (!$response->successful()) {
  echo "❌ Erreur : Token invalide\n";
  echo "Réponse : " . $response->body() . "\n\n";
  exit(1);
 }

 $botInfo = $response->json();
 echo "✅ Bot trouvé : @" . $botInfo['result']['username'] . "\n";
 echo "   Nom : " . $botInfo['result']['first_name'] . "\n\n";
} catch (\Throwable $e) {
 echo "❌ Erreur de connexion : " . $e->getMessage() . "\n\n";
 exit(1);
}

// Récupérer les updates
echo "📥 Récupération des messages...\n\n";
try {
 $response = Http::get("https://api.telegram.org/bot{$botToken}/getUpdates");

 if (!$response->successful()) {
  echo "❌ Erreur lors de la récupération des updates\n";
  echo "Réponse : " . $response->body() . "\n\n";
  exit(1);
 }

 $updates = $response->json();

 if (empty($updates['result'])) {
  echo "⚠️  Aucun message trouvé.\n\n";
  echo "📝 Instructions :\n";
  echo "   1. Ouvrez Telegram\n";
  echo "   2. Recherchez votre bot : @" . ($botInfo['result']['username'] ?? 'votre_bot') . "\n";
  echo "   3. Cliquez sur 'Start' ou 'Démarrer'\n";
  echo "   4. Envoyez n'importe quel message (ex: 'Hello')\n";
  echo "   5. Relancez ce script : php get_chat_id.php\n\n";
  exit(0);
 }

 echo "✅ " . count($updates['result']) . " message(s) trouvé(s)\n\n";
 echo "═══════════════════════════════════════════════════════════\n";
 echo "  💬 Conversations détectées\n";
 echo "═══════════════════════════════════════════════════════════\n\n";

 $chatIds = [];

 foreach ($updates['result'] as $update) {
  if (isset($update['message']['chat'])) {
   $chat = $update['message']['chat'];
   $chatId = $chat['id'];

   if (!in_array($chatId, $chatIds)) {
    $chatIds[] = $chatId;

    echo "Chat ID : " . $chatId . "\n";
    echo "Type    : " . $chat['type'] . "\n";

    if ($chat['type'] === 'private') {
     echo "Nom     : " . ($chat['first_name'] ?? '') . " " . ($chat['last_name'] ?? '') . "\n";
     if (isset($chat['username'])) {
      echo "Username: @" . $chat['username'] . "\n";
     }
    } else {
     echo "Nom     : " . ($chat['title'] ?? 'Groupe') . "\n";
    }

    if (isset($update['message']['text'])) {
     echo "Message : " . substr($update['message']['text'], 0, 50) . "\n";
    }

    echo "\n";
   }
  }
 }

 if (count($chatIds) === 1) {
  $chatId = $chatIds[0];
  echo "═══════════════════════════════════════════════════════════\n";
  echo "  ✅ Configuration recommandée\n";
  echo "═══════════════════════════════════════════════════════════\n\n";
  echo "Ajoutez cette ligne dans votre fichier .env :\n\n";
  echo "TELEGRAM_CHAT_ID={$chatId}\n\n";

  // Test d'envoi
  echo "═══════════════════════════════════════════════════════════\n";
  echo "  🧪 Test d'envoi\n";
  echo "═══════════════════════════════════════════════════════════\n\n";

  $testResponse = Http::asForm()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
   'chat_id' => $chatId,
   'text' => "✅ Configuration ProsArtisan réussie !\n\nVotre Chat ID : {$chatId}\nHeure : " . now()->toDateTimeString(),
   'parse_mode' => 'HTML',
  ]);

  if ($testResponse->successful()) {
   echo "✅ Message de test envoyé avec succès !\n";
   echo "   Vérifiez votre Telegram.\n\n";
  } else {
   echo "⚠️  Échec de l'envoi du message de test\n";
   echo "   Status : " . $testResponse->status() . "\n";
   echo "   Erreur : " . $testResponse->body() . "\n\n";
  }
 } elseif (count($chatIds) > 1) {
  echo "═══════════════════════════════════════════════════════════\n";
  echo "  ⚠️  Plusieurs conversations détectées\n";
  echo "═══════════════════════════════════════════════════════════\n\n";
  echo "Choisissez le Chat ID que vous souhaitez utiliser et ajoutez-le dans .env :\n\n";
  echo "TELEGRAM_CHAT_ID=<votre_chat_id>\n\n";
 }
} catch (\Throwable $e) {
 echo "❌ Erreur : " . $e->getMessage() . "\n\n";
 exit(1);
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  📚 Prochaines étapes\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "1. Mettez à jour TELEGRAM_CHAT_ID dans .env\n";
echo "2. Testez avec : php test_telegram.php\n";
echo "3. Ou via Tinker : php artisan tinker\n";
echo "   Log::channel('telegram_bot')->error('Test');\n\n";

exit(0);
