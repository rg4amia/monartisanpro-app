# Configuration Telegram Logger

Ce guide explique comment configurer les notifications Telegram pour recevoir les erreurs et logs de votre app Flutter.

## 📱 Étape 1 : Créer un Bot Telegram

1. Ouvrez Telegram et cherchez **@BotFather**
2. Envoyez `/newbot`
3. Donnez un nom à votre bot (ex: "ProsArtisan Debug Bot")
4. Donnez un username (ex: "prosartisan_debug_bot")
5. **Copiez le token** que BotFather vous donne (format: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

## 🆔 Étape 2 : Récupérer votre Chat ID

### Option A : Via @userinfobot (le plus simple)

1. Cherchez **@userinfobot** sur Telegram
2. Envoyez `/start`
3. Le bot vous donnera votre **Chat ID** (ex: `123456789`)

### Option B : Via l'API Telegram

1. Envoyez un message à votre bot (n'importe quoi, ex: "Hello")
2. Ouvrez cette URL dans votre navigateur :

   ```
   https://api.telegram.org/bot<VOTRE_TOKEN>/getUpdates
   ```

3. Cherchez `"chat":{"id":123456789}` dans la réponse JSON
4. Copiez ce nombre (votre Chat ID)

## ⚙️ Étape 3 : Configurer l'App Flutter

### Méthode 1 : Variables d'environnement (recommandé)

Lancez l'app avec :

```bash
flutter run --dart-define=TELEGRAM_BOT_TOKEN=votre_token --dart-define=TELEGRAM_CHAT_ID=votre_chat_id
```

Ou dans `launch.json` (VS Code) :

```json
{
  "configurations": [
    {
      "name": "Flutter Debug",
      "type": "dart",
      "request": "launch",
      "program": "lib/main.dart",
      "args": [
        "--dart-define=TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
        "--dart-define=TELEGRAM_CHAT_ID=123456789"
      ]
    }
  ]
}
```

### Méthode 2 : Fichier de config (développement uniquement)

Éditez `lib/core/config/env_config.dart` :

```dart
static const String telegramBotToken = '123456789:ABCdefGHIjklMNOpqrsTUVwxyz';
static const String telegramChatId = '123456789';
```

⚠️ **ATTENTION** : Ne commitez JAMAIS ces credentials sur Git !

Ajoutez à `.gitignore` :

```
# Telegram credentials
lib/core/config/env_config.local.dart
```

## 🧪 Étape 4 : Tester

### Test manuel

```dart
import 'package:frontend_flutter/core/utils/error_handler.dart';

// Dans n'importe quel controller ou screen
ErrorHandler.logInfo('Test de notification Telegram');
```

### Test automatique

L'app enverra automatiquement les notifications pour :

- ❌ Erreurs Flutter (crashes, exceptions)
- 🌐 Erreurs HTTP (API calls)
- ⚠️ Warnings
- 📊 Événements custom

## 📋 Utilisation

### Log une erreur

```dart
try {
  // Code qui peut échouer
} catch (e, stack) {
  await ErrorHandler.handle(
    e,
    stackTrace: stack,
    context: 'Nom de la fonction',
  );
}
```

### Log une erreur HTTP

```dart
try {
  await dio.get('/api/endpoint');
} on DioException catch (e) {
  await ErrorHandler.handleDioError(
    e,
    context: 'Récupération des missions',
  );
}
```

### Log un warning

```dart
await ErrorHandler.logWarning(
  'KYC non validé',
  context: 'Profile Screen',
);
```

### Log un événement

```dart
await ErrorHandler.logEvent(
  'Mission créée',
  data: {
    'mission_id': 123,
    'montant': 50000,
    'artisan_id': 456,
  },
  context: 'Mission Request',
);
```

## 🎨 Format des notifications

### Erreur

```
🔴 ERREUR

📍 Contexte: Login Screen
⚠️ Erreur:
Exception: Invalid credentials

📚 Stack:
#0 AuthController.login (...)
#1 LoginScreen.build (...)

🕐 2026-03-09T14:30:00.000
📱 android
```

### Erreur HTTP

```
🌐 ERREUR HTTP

📍 Contexte: Récupération des missions
🔗 URL: https://prosartisan.net/api/v1/missions
📤 Méthode: GET
📥 Status: 401

💬 Réponse:
{"message":"Unauthenticated"}

🕐 2026-03-09T14:30:00.000
```

### Warning

```
⚠️ WARNING

📍 Contexte: Profile Screen
💬 KYC non validé

🕐 2026-03-09T14:30:00.000
```

### Événement

```
📊 EVENT: Mission créée

📍 Contexte: Mission Request
📦 Data:
  • mission_id: 123
  • montant: 50000
  • artisan_id: 456

🕐 2026-03-09T14:30:00.000
```

## ⚙️ Configuration avancée

### Désactiver en production

Dans `lib/core/services/telegram_logger.dart` :

```dart
static const bool _enableInDebug = true;
static const bool _enableInRelease = false; // Désactivé en prod
```

### Activer en production (avec prudence)

```dart
static const bool _enableInRelease = true;
```

⚠️ Attention au volume de messages en production !

### Filtrer les logs

Modifiez `ErrorHandler` pour ajouter des conditions :

```dart
static Future<void> handle(
  dynamic error, {
  bool sendToTelegram = true,
}) async {
  if (sendToTelegram && _shouldLog(error)) {
    await _telegram.logError(error);
  }
}

static bool _shouldLog(dynamic error) {
  // Filtrez certaines erreurs
  if (error.toString().contains('Connection timeout')) {
    return false; // Trop fréquent
  }
  return true;
}
```

## 🔒 Sécurité

1. **Ne commitez jamais** vos tokens sur Git
2. Utilisez des **variables d'environnement** en production
3. Créez un **bot séparé** pour chaque environnement (dev/staging/prod)
4. Limitez les **permissions du bot** (pas besoin d'admin)
5. Surveillez le **volume de messages** (rate limits Telegram)

## 🐛 Dépannage

### Le bot ne répond pas

- Vérifiez que vous avez envoyé `/start` au bot
- Vérifiez le token et chat_id
- Testez l'URL manuellement :

  ```
  https://api.telegram.org/bot<TOKEN>/sendMessage?chat_id=<CHAT_ID>&text=Test
  ```

### Trop de notifications

- Désactivez les logs info : `_enableInDebug = false`
- Ajoutez des filtres dans `ErrorHandler`
- Utilisez `showSnackbar: false` pour les erreurs silencieuses

### Messages tronqués

- Les messages Telegram sont limités à 4096 caractères
- Le logger tronque automatiquement les longs messages
- Pour les stack traces complètes, consultez les logs locaux

## 📚 Ressources

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [BotFather](https://t.me/botfather)
- [Dio Error Handling](https://pub.dev/packages/dio#error-handling)
