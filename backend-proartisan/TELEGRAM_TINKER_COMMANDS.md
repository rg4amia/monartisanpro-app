# 🧪 Commandes Tinker pour tester Telegram

## Méthode 1 : Test direct HTTP (recommandé pour déboguer)

```php
php artisan tinker

// Test simple
$botToken = env('TELEGRAM_BOT_TOKEN');
$chatId = env('TELEGRAM_CHAT_ID');

$lines = [
    '<b>🧪 Test ProsArtisan</b>',
    '',
    '<b>Message:</b> Test depuis Tinker',
    '<b>Heure:</b> ' . now()->toDateTimeString(),
];

$response = Http::asForm()->timeout(3)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
    'chat_id' => $chatId,
    'text' => implode("\n", $lines),
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true,
]);

echo $response->status();
echo $response->body();
```

## Méthode 2 : Via le système de logging Laravel

```php
php artisan tinker

// Test simple
Log::channel('telegram_bot')->error('Test depuis Tinker');

// Test avec contexte
Log::channel('telegram_bot')->error('Erreur de test', [
    'user_id' => 123,
    'action' => 'test_action',
    'data' => ['key' => 'value'],
]);

// Test avec exception
$exception = new \Exception('Test exception', 500);
Log::channel('telegram_bot')->error('Exception de test', [
    'exception' => $exception,
]);
```

## Méthode 3 : Utiliser le script de test

```bash
# Option 1: Via Tinker
php artisan tinker
require 'test_telegram.php';
testTelegramDirect();
testTelegramLogger();
testTelegramWithException();

# Option 2: Directement
php test_telegram.php
```

## Méthode 4 : Test rapide one-liner

```bash
# Test direct depuis la ligne de commande
php artisan tinker --execute="Http::asForm()->timeout(3)->post('https://api.telegram.org/bot'.env('TELEGRAM_BOT_TOKEN').'/sendMessage', ['chat_id' => env('TELEGRAM_CHAT_ID'), 'text' => '🧪 Test ProsArtisan - '.now(), 'parse_mode' => 'HTML'])->body()"
```

## Vérification de la configuration

```php
php artisan tinker

// Vérifier les variables d'environnement
echo "Bot Token: " . env('TELEGRAM_BOT_TOKEN') . "\n";
echo "Chat ID: " . env('TELEGRAM_CHAT_ID') . "\n";

// Vérifier la configuration du logger
config('logging.channels.telegram_bot');

// Tester la connexion Telegram API
$botToken = env('TELEGRAM_BOT_TOKEN');
$response = Http::get("https://api.telegram.org/bot{$botToken}/getMe");
echo $response->body();
```

## Déboguer les problèmes

```php
php artisan tinker

// 1. Vérifier que le handler est bien chargé
$handler = new \App\Logging\TelegramBotHandler(
    env('TELEGRAM_BOT_TOKEN'),
    env('TELEGRAM_CHAT_ID'),
    \Monolog\Level::Error,
    true,
    config('app.name'),
    config('app.env')
);

// 2. Tester manuellement l'envoi
use Monolog\LogRecord;
use Monolog\Level;

$record = new LogRecord(
    datetime: new \DateTimeImmutable(),
    channel: 'telegram_bot',
    level: Level::Error,
    message: 'Test manuel',
    context: [],
);

$handler->handle($record);

// 3. Vérifier les logs Laravel
tail -f storage/logs/laravel.log
```

## Erreurs courantes

### Erreur 400 : Bad Request

- Vérifier que le `TELEGRAM_CHAT_ID` est correct
- Vérifier que le bot a bien été ajouté au chat/groupe

### Erreur 401 : Unauthorized

- Vérifier que le `TELEGRAM_BOT_TOKEN` est correct
- Vérifier qu'il n'y a pas d'espaces ou de guillemets en trop

### Timeout

- Vérifier la connexion internet
- Augmenter le timeout : `->timeout(10)`

### Message non reçu

- Vérifier que vous avez démarré une conversation avec le bot
- Pour un groupe : vérifier que le bot est membre du groupe
- Utiliser `/start` dans le chat avec le bot

## Obtenir le Chat ID

```php
php artisan tinker

// Envoyer un message au bot, puis récupérer les updates
$botToken = env('TELEGRAM_BOT_TOKEN');
$response = Http::get("https://api.telegram.org/bot{$botToken}/getUpdates");
echo $response->body();

// Le chat_id sera dans la réponse
```

## Test complet de bout en bout

```php
php artisan tinker

// 1. Vérifier la config
echo "✓ Bot Token: " . (env('TELEGRAM_BOT_TOKEN') ? 'OK' : 'MANQUANT') . "\n";
echo "✓ Chat ID: " . (env('TELEGRAM_CHAT_ID') ? 'OK' : 'MANQUANT') . "\n";

// 2. Tester l'API Telegram
$botToken = env('TELEGRAM_BOT_TOKEN');
$me = Http::get("https://api.telegram.org/bot{$botToken}/getMe")->json();
echo "✓ Bot: " . ($me['result']['username'] ?? 'ERREUR') . "\n";

// 3. Envoyer un message de test
$response = Http::asForm()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'text' => '✅ Test ProsArtisan réussi - ' . now(),
]);
echo "✓ Envoi: " . ($response->successful() ? 'OK' : 'ÉCHEC') . "\n";

// 4. Tester via le logger
Log::channel('telegram_bot')->error('✅ Test logger réussi');
echo "✓ Logger: OK\n";
```
