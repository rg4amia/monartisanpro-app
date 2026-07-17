# 📱 Système de Notification Telegram - ProsArtisan

## 🎯 Vue d'ensemble

Système complet de logging et notification Telegram pour recevoir en temps réel les erreurs, warnings et événements de votre app Flutter.

## 📦 Fichiers créés

```
frontend_flutter/
├── lib/
│   ├── core/
│   │   ├── services/
│   │   │   └── telegram_logger.dart          # Service de base Telegram
│   │   ├── utils/
│   │   │   ├── error_handler.dart            # Gestionnaire d'erreurs
│   │   │   ├── app_logger.dart               # Logger métier
│   │   │   └── telegram_test_screen.dart     # Écran de test
│   │   ├── network/
│   │   │   └── api_client.dart               # ✅ Modifié (interceptor)
│   │   └── config/
│   │       └── env_config.dart               # ✅ Modifié (credentials)
│   └── main.dart                             # ✅ Modifié (error handler)
├── TELEGRAM_LOGGER_SETUP.md                  # Guide de configuration
├── TELEGRAM_LOGGER_EXAMPLES.md               # Exemples d'utilisation
└── README_TELEGRAM_LOGGER.md                 # Ce fichier
```

## 🚀 Quick Start

### 1. Configuration (5 minutes)

```bash
# 1. Créer un bot Telegram via @BotFather
# 2. Récupérer votre chat_id via @userinfobot
# 3. Configurer les credentials
```

Éditez `lib/core/config/env_config.dart` :

```dart
static const String telegramBotToken = 'VOTRE_TOKEN';
static const String telegramChatId = 'VOTRE_CHAT_ID';
```

### 2. Test (1 minute)

```dart
import 'package:frontend_flutter/core/utils/telegram_test_screen.dart';

// Dans n'importe quel screen
ElevatedButton(
  onPressed: () => Get.to(() => const TelegramTestScreen()),
  child: const Text('Test Telegram'),
)
```

### 3. Utilisation

```dart
import 'package:frontend_flutter/core/utils/app_logger.dart';

// Log une erreur
await AppLogger.error('Message', error: e, context: 'ClassName');

// Log un événement
await AppLogger.event('mission_created', data: {'id': 123});

// Log une transaction
await AppLogger.transaction('acompte', montant: 50000);
```

## 📋 Fonctionnalités

### ✅ Logging automatique

- ❌ **Erreurs Flutter** : Crashes et exceptions non gérées
- 🌐 **Erreurs HTTP** : Toutes les erreurs API (via interceptor Dio)
- ⚠️ **Warnings** : Alertes métier importantes
- 📊 **Événements** : Actions utilisateur et événements métier

### ✅ Logging manuel

- 💰 **Transactions** : Paiements, libérations, remboursements
- 🎯 **Missions** : Création, acceptation, complétion
- 🔐 **KYC** : Soumission, validation, rejet
- 🎫 **J-Codes** : Génération, scan, utilisation
- 📍 **Jalons** : Soumission, validation OTP
- 📍 **Géolocalisation** : Erreurs GPS, permissions
- 👤 **Actions utilisateur** : Clics, navigation, formulaires

### ✅ Formats de notification

```
🔴 ERREUR
📍 Contexte: LoginScreen
⚠️ Erreur: Exception: Invalid credentials
📚 Stack: #0 AuthController.login (...)
🕐 2026-03-09T14:30:00.000
📱 android
```

```
🌐 ERREUR HTTP
📍 Contexte: GET /missions
🔗 URL: https://prosartisan.net/api/v1/missions
📥 Status: 401
💬 Réponse: {"message":"Unauthenticated"}
🕐 2026-03-09T14:30:00.000
```

```
📊 EVENT: mission_created
📍 Contexte: Mission Request
📦 Data:
  • mission_id: 123
  • montant: 50000
  • artisan_id: 456
🕐 2026-03-09T14:30:00.000
```

## 🎨 API du Logger

### AppLogger (recommandé)

```dart
// Erreurs
await AppLogger.error(message, error: e, stackTrace: stack, context: 'Class.method');

// Warnings
await AppLogger.warning(message, context: 'Class');

// Info (debug uniquement)
await AppLogger.info(message, context: 'Class');

// Événements
await AppLogger.event('event_name', data: {...}, context: 'Class');

// Métier
await AppLogger.transaction('type', montant: 50000, data: {...});
await AppLogger.mission('action', missionId: 123, data: {...});
await AppLogger.kyc('action', status: 'actif', data: {...});
await AppLogger.jcode('action', code: 'PA-1234', data: {...});
await AppLogger.jalon('action', jalonId: 123, data: {...});

// Géolocalisation
await AppLogger.geoError(message, error: e);
await AppLogger.permissionDenied('location', context: 'Screen');

// Actions utilisateur
await AppLogger.userAction('action', data: {...});

// Debug (console uniquement)
AppLogger.debug(message);
AppLogger.success(message);
AppLogger.navigation(from, to);
```

### ErrorHandler (bas niveau)

```dart
// Erreur générique
await ErrorHandler.handle(error, stackTrace: stack, context: 'Class');

// Erreur HTTP
await ErrorHandler.handleDioError(dioException, context: 'API Call');

// Warning
await ErrorHandler.logWarning(message, context: 'Class');

// Info
await ErrorHandler.logInfo(message, context: 'Class');

// Événement
await ErrorHandler.logEvent('event', data: {...}, context: 'Class');
```

## 🔧 Configuration avancée

### Variables d'environnement (production)

```bash
flutter run \
  --dart-define=TELEGRAM_BOT_TOKEN=votre_token \
  --dart-define=TELEGRAM_CHAT_ID=votre_chat_id
```

### Activer/désactiver par environnement

```dart
// lib/core/config/env_config.dart
static const bool telegramLoggerDebug = true;    // Dev
static const bool telegramLoggerRelease = false; // Prod
```

### Filtrer les logs

```dart
// Dans error_handler.dart
static bool _shouldLog(dynamic error) {
  if (error.toString().contains('Connection timeout')) {
    return false; // Trop fréquent
  }
  return true;
}
```

## 📚 Documentation complète

- **[TELEGRAM_LOGGER_SETUP.md](TELEGRAM_LOGGER_SETUP.md)** : Guide de configuration détaillé
- **[TELEGRAM_LOGGER_EXAMPLES.md](TELEGRAM_LOGGER_EXAMPLES.md)** : Exemples d'utilisation par module

## 🧪 Tests

### Écran de test intégré

```dart
import 'package:frontend_flutter/core/utils/telegram_test_screen.dart';

Get.to(() => const TelegramTestScreen());
```

Fonctionnalités :

- ✅ Test Info
- ⚠️ Test Warning
- ❌ Test Error
- 📊 Test Event
- 💰 Test Transaction
- 🔐 Test KYC
- 🎯 Test Mission
- 🎫 Test J-Code
- 📍 Test Géolocalisation
- 💥 Test Crash

### Tests manuels

```dart
// Test simple
await AppLogger.info('Test notification');

// Test avec données
await AppLogger.event('test', data: {
  'timestamp': DateTime.now().toIso8601String(),
});
```

## 🔒 Sécurité

### ✅ À faire

- Utiliser des variables d'environnement en production
- Ne jamais commiter les tokens sur Git
- Créer un bot séparé par environnement
- Filtrer les données sensibles

### ❌ À ne JAMAIS logger

- Mots de passe
- Tokens d'authentification
- Numéros de CNI complets
- Données bancaires
- Informations personnelles sensibles

### Exemple de filtrage

```dart
await AppLogger.event('login_success', data: {
  'user_id': userId,
  'phone': phone.substring(0, 4) + '****', // ✅ Masqué
  // 'password': password, // ❌ JAMAIS
});
```

## 🎯 Cas d'usage recommandés

### Toujours logger

- ❌ Erreurs critiques (crashes, exceptions)
- 🌐 Erreurs HTTP (automatique)
- 💰 Transactions financières
- 🔐 Opérations KYC
- 🎯 Création/modification de missions
- 🎫 Génération/scan de J-Codes
- 📍 Validation de jalons

### Logger en debug uniquement

- ℹ️ Informations de debug
- 🧭 Navigation entre écrans
- 👤 Actions utilisateur simples
- 🔍 Logs de développement

### Ne pas logger

- Boucles fréquentes
- Données non pertinentes
- Informations sensibles
- Logs trop verbeux

## 📊 Métriques

Le système permet de tracker :

- **Stabilité** : Taux d'erreurs, crashes
- **Performance** : Erreurs HTTP, timeouts
- **Métier** : Missions, transactions, KYC
- **UX** : Actions utilisateur, navigation
- **Technique** : Permissions, géolocalisation

## 🐛 Dépannage

### Le bot ne répond pas

1. Vérifiez le token et chat_id
2. Envoyez `/start` au bot
3. Testez manuellement :

   ```
   https://api.telegram.org/bot<TOKEN>/sendMessage?chat_id=<CHAT_ID>&text=Test
   ```

### Trop de notifications

1. Désactivez les logs info : `telegramLoggerDebug = false`
2. Ajoutez des filtres dans `ErrorHandler`
3. Utilisez `showSnackbar: false` pour les erreurs silencieuses

### Messages tronqués

- Limite Telegram : 4096 caractères
- Le logger tronque automatiquement
- Consultez les logs locaux pour les détails complets

## 🔄 Intégration dans le code existant

### Avant

```dart
try {
  await apiClient.post('/missions', data: data);
} catch (e) {
  print('Error: $e');
}
```

### Après

```dart
try {
  final response = await apiClient.post('/missions', data: data);
  
  await AppLogger.mission('created', 
    missionId: response.data['id'],
    data: {'montant': data['montant']},
  );
  
} on DioException catch (e) {
  // Déjà loggé automatiquement par l'interceptor
  
} catch (e, stack) {
  await AppLogger.error('Erreur création mission',
    error: e,
    stackTrace: stack,
    context: 'MissionController.create',
  );
}
```

## 📱 Ajout dans Settings

```dart
// lib/modules/settings/views/settings_screen.dart

ListTile(
  leading: const Icon(Icons.bug_report),
  title: const Text('Test Telegram Logger'),
  subtitle: const Text('Tester les notifications'),
  onTap: () => Get.to(() => const TelegramTestScreen()),
)
```

## 🚀 Prochaines étapes

1. ✅ Configurer le bot Telegram
2. ✅ Tester avec l'écran de test
3. ✅ Intégrer dans les controllers existants
4. ✅ Monitorer les notifications
5. ✅ Ajuster les filtres si nécessaire

## 📞 Support

- [Documentation Telegram Bot API](https://core.telegram.org/bots/api)
- [BotFather](https://t.me/botfather)
- [Dio Error Handling](https://pub.dev/packages/dio#error-handling)

## 📝 Changelog

### v1.0.0 (2026-03-09)

- ✅ Service TelegramLogger
- ✅ ErrorHandler avec gestion Dio
- ✅ AppLogger avec méthodes métier
- ✅ Interceptor HTTP automatique
- ✅ Écran de test intégré
- ✅ Documentation complète
- ✅ Exemples d'utilisation
