# 🚀 Quick Start - Telegram Logger (5 minutes)

## Étape 1 : Créer le bot (2 min)

1. Ouvrez Telegram
2. Cherchez **@BotFather**
3. Envoyez `/newbot`
4. Nom : `ProsArtisan Debug Bot`
5. Username : `prosartisan_debug_bot`
6. **Copiez le token** : `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`

## Étape 2 : Récupérer votre Chat ID (1 min)

1. Cherchez **@userinfobot** sur Telegram
2. Envoyez `/start`
3. **Copiez votre ID** : `123456789`

## Étape 3 : Configurer l'app (1 min)

Éditez `lib/core/config/env_config.dart` :

```dart
static const String telegramBotToken = '123456789:ABCdefGHIjklMNOpqrsTUVwxyz';
static const String telegramChatId = '123456789';
```

## Étape 4 : Tester (1 min)

### Option A : Écran de test

```dart
import 'package:frontend_flutter/core/utils/telegram_test_screen.dart';

// Ajoutez un bouton dans votre app
ElevatedButton(
  onPressed: () => Get.to(() => const TelegramTestScreen()),
  child: const Text('Test Telegram'),
)
```

### Option B : Code direct

```dart
import 'package:frontend_flutter/core/utils/app_logger.dart';

await AppLogger.info('Test de notification Telegram');
```

## ✅ C'est tout !

Vous recevrez maintenant automatiquement :
- ❌ Toutes les erreurs de l'app
- 🌐 Toutes les erreurs HTTP
- ⚠️ Les warnings importants
- 📊 Les événements que vous loggez

## 📱 Utilisation quotidienne

```dart
// Dans vos controllers
import 'package:frontend_flutter/core/utils/app_logger.dart';

// Log une erreur
try {
  // code
} catch (e, stack) {
  await AppLogger.error('Message', error: e, stackTrace: stack);
}

// Log un événement
await AppLogger.mission('created', missionId: 123);
await AppLogger.transaction('acompte', montant: 50000);
await AppLogger.kyc('submitted', status: 'en_attente');
```

## 🎯 Exemples concrets

### Mission créée
```dart
await AppLogger.mission('created', 
  missionId: mission.id,
  data: {
    'client_id': clientId,
    'montant': mission.montant,
  },
);
```

### Paiement effectué
```dart
await AppLogger.transaction('acompte',
  montant: 50000,
  data: {
    'mission_id': missionId,
    'provider': 'wave',
  },
);
```

### Erreur HTTP
```dart
// Automatique ! Rien à faire, l'interceptor s'en charge
```

### Erreur custom
```dart
try {
  // code risqué
} catch (e, stack) {
  await AppLogger.error(
    'Erreur lors de X',
    error: e,
    stackTrace: stack,
    context: 'ClassName.methodName',
  );
}
```

## 📚 Documentation complète

- [README_TELEGRAM_LOGGER.md](README_TELEGRAM_LOGGER.md) - Vue d'ensemble
- [TELEGRAM_LOGGER_SETUP.md](TELEGRAM_LOGGER_SETUP.md) - Configuration détaillée
- [TELEGRAM_LOGGER_EXAMPLES.md](TELEGRAM_LOGGER_EXAMPLES.md) - Exemples par module

## 🔒 Sécurité

⚠️ **IMPORTANT** : Ne commitez JAMAIS vos tokens sur Git !

Ajoutez à `.gitignore` :
```
lib/core/config/env_config.local.dart
```

En production, utilisez des variables d'environnement :
```bash
flutter run \
  --dart-define=TELEGRAM_BOT_TOKEN=votre_token \
  --dart-define=TELEGRAM_CHAT_ID=votre_chat_id
```

## 🎉 Vous êtes prêt !

Votre app envoie maintenant toutes les erreurs et événements importants directement sur Telegram.
