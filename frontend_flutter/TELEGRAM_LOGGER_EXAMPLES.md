# Exemples d'utilisation du Telegram Logger

Ce document présente des exemples concrets d'utilisation du système de logging Telegram dans l'app ProsArtisan.

## 📚 Import

```dart
import 'package:frontend_flutter/core/utils/app_logger.dart';
import 'package:frontend_flutter/core/utils/error_handler.dart';
```

## 🎯 Cas d'usage par module

### 1. Authentification

```dart
// Login réussi
await AppLogger.event(
  'login_success',
  data: {
    'user_id': user.id,
    'role': user.role,
  },
  context: 'Auth',
);

// Login échoué
await AppLogger.warning(
  'Tentative de login avec identifiants incorrects',
  context: 'Auth',
);

// KYC soumis
await AppLogger.kyc(
  'submitted',
  status: 'en_attente',
  data: {
    'user_id': userId,
    'document_type': 'cni',
  },
);
```

### 2. Missions

```dart
// Mission créée
await AppLogger.mission(
  'created',
  missionId: mission.id,
  data: {
    'client_id': clientId,
    'montant': mission.montant,
    'categorie': mission.categorie,
  },
);

// Mission acceptée par artisan
await AppLogger.mission(
  'accepted',
  missionId: missionId,
  data: {
    'artisan_id': artisanId,
    'score_nzassa': artisan.score,
  },
);

// Mission terminée
await AppLogger.mission(
  'completed',
  missionId: missionId,
  data: {
    'duree_jours': duree,
    'note_finale': note,
  },
);
```

### 3. Paiements

```dart
// Acompte payé
await AppLogger.transaction(
  'acompte',
  montant: 50000,
  data: {
    'mission_id': missionId,
    'provider': 'wave',
    'reference': transactionRef,
  },
);

// Libération jalon
await AppLogger.transaction(
  'liberation_jalon',
  montant: 25000,
  data: {
    'mission_id': missionId,
    'jalon_id': jalonId,
    'artisan_id': artisanId,
  },
);

// Erreur de paiement
await AppLogger.error(
  'Échec du paiement Wave',
  error: e,
  context: 'PaymentController.processWavePayment',
);
```

### 4. J-Codes

```dart
// J-Code généré
await AppLogger.jcode(
  'generated',
  code: jcode.code,
  data: {
    'mission_id': missionId,
    'montant': jcode.montant,
    'fournisseur_id': fournisseurId,
  },
);

// J-Code scanné
await AppLogger.jcode(
  'scanned',
  code: jcode.code,
  data: {
    'fournisseur_id': fournisseurId,
    'distance_metres': distance,
  },
);

// J-Code bloqué (GPS)
await AppLogger.warning(
  'J-Code scan bloqué: distance > 100m',
  context: 'JCodeController',
);
```

### 5. Jalons

```dart
// Jalon soumis
await AppLogger.jalon(
  'submitted',
  jalonId: jalonId,
  data: {
    'mission_id': missionId,
    'ordre': jalon.ordre,
    'photos_count': photos.length,
  },
);

// OTP validé
await AppLogger.jalon(
  'otp_validated',
  jalonId: jalonId,
  data: {
    'client_id': clientId,
    'montant_libere': jalon.montant,
  },
);

// Jalon rejeté
await AppLogger.jalon(
  'rejected',
  jalonId: jalonId,
  data: {
    'raison': raison,
  },
);
```

### 6. Géolocalisation

```dart
// Position récupérée
AppLogger.debug('Position: ${position.latitude}, ${position.longitude}');

// Erreur GPS
await AppLogger.geoError(
  'Impossible de récupérer la position',
  error: e,
);

// Permission refusée
await AppLogger.permissionDenied(
  'location',
  context: 'ArtisanMapScreen',
);

// Distance calculée
await AppLogger.info(
  'Distance artisan: ${distance}m',
  context: 'ArtisanSearch',
);
```

### 7. Erreurs HTTP

```dart
// Les erreurs HTTP sont automatiquement loggées par l'interceptor
// Mais vous pouvez ajouter du contexte :

try {
  await apiClient.get('/missions/$missionId');
} on DioException catch (e) {
  // Déjà loggé automatiquement
  // Ajoutez juste le contexte métier si nécessaire
  if (e.response?.statusCode == 404) {
    await AppLogger.warning(
      'Mission $missionId introuvable',
      context: 'MissionController',
    );
  }
}
```

### 8. Navigation

```dart
// Log simple (console uniquement)
AppLogger.navigation('LoginScreen', 'MainTabScreen');

// Avec événement Telegram
await AppLogger.userAction(
  'navigation',
  data: {
    'from': 'LoginScreen',
    'to': 'MainTabScreen',
  },
);
```

### 9. Actions utilisateur

```dart
// Bouton cliqué
await AppLogger.userAction(
  'button_click',
  data: {
    'button': 'create_mission',
    'screen': 'HomeScreen',
  },
);

// Formulaire soumis
await AppLogger.userAction(
  'form_submit',
  data: {
    'form': 'mission_request',
    'fields_count': 5,
  },
);

// Photo prise
await AppLogger.userAction(
  'photo_captured',
  data: {
    'type': 'jalon_proof',
    'jalon_id': jalonId,
  },
);
```

### 10. Erreurs critiques

```dart
// Erreur avec stack trace
try {
  // Code qui peut crasher
  final result = await riskyOperation();
} catch (e, stack) {
  await AppLogger.error(
    'Opération critique échouée',
    error: e,
    stackTrace: stack,
    context: 'CriticalController.riskyOperation',
  );
  
  // Afficher un message à l'utilisateur
  Get.snackbar('Erreur', 'Une erreur est survenue');
}

// Erreur sans stack
await AppLogger.error(
  'Validation échouée',
  error: 'Le montant doit être > 0',
  context: 'DevisController.validate',
);
```

## 🧪 Test rapide

### Dans n'importe quel screen

```dart
import 'package:frontend_flutter/core/utils/telegram_test_screen.dart';

// Ajouter un bouton de test
ElevatedButton(
  onPressed: () => Get.to(() => const TelegramTestScreen()),
  child: const Text('Test Telegram'),
)
```

### Ou directement dans le code

```dart
// Test simple
await AppLogger.info('Test de notification');

// Test avec données
await AppLogger.event(
  'test_event',
  data: {
    'timestamp': DateTime.now().toIso8601String(),
    'user': 'dev',
  },
);
```

## 🎨 Bonnes pratiques

### ✅ À faire

```dart
// Contexte clair
await AppLogger.error(
  'Erreur',
  error: e,
  context: 'ClassName.methodName',
);

// Données structurées
await AppLogger.event(
  'mission_created',
  data: {
    'mission_id': 123,
    'montant': 50000,
    'client_id': 456,
  },
);

// Messages descriptifs
await AppLogger.warning(
  'KYC non validé: impossible de créer une mission',
  context: 'MissionController',
);
```

### ❌ À éviter

```dart
// Contexte vague
await AppLogger.error('Erreur', error: e);

// Trop de logs
for (var item in items) {
  await AppLogger.info('Processing $item'); // ❌ Spam
}

// Données sensibles
await AppLogger.event('login', data: {
  'password': password, // ❌ JAMAIS
  'token': token, // ❌ JAMAIS
});

// Messages non descriptifs
await AppLogger.error('Oops'); // ❌ Pas clair
```

## 🔍 Filtrage des logs

### Par environnement

```dart
// Info uniquement en debug
if (kDebugMode) {
  await AppLogger.info('Debug info');
}

// Toujours logger les erreurs
await AppLogger.error('Critical error', error: e);
```

### Par criticité

```dart
// Critique → Telegram + Console
await AppLogger.error('Erreur critique', error: e);

// Important → Telegram + Console
await AppLogger.warning('Attention');

// Info → Console uniquement (debug)
await AppLogger.info('Info');

// Debug → Console uniquement
AppLogger.debug('Debug');
```

## 📊 Métriques recommandées

### Événements métier à logger

- ✅ Création/modification/suppression de missions
- ✅ Transactions financières (tous types)
- ✅ Soumission/validation KYC
- ✅ Génération/scan de J-Codes
- ✅ Soumission/validation de jalons
- ✅ Erreurs HTTP (automatique)
- ✅ Erreurs de géolocalisation
- ✅ Permissions refusées
- ✅ Crashes (automatique)

### À ne PAS logger

- ❌ Mots de passe
- ❌ Tokens d'authentification
- ❌ Données personnelles sensibles (CNI, etc.)
- ❌ Logs trop fréquents (boucles)
- ❌ Données de debug non pertinentes

## 🚀 Intégration dans les controllers existants

### Avant

```dart
Future<void> createMission() async {
  try {
    final response = await apiClient.post('/missions', data: data);
    Get.snackbar('Succès', 'Mission créée');
  } catch (e) {
    print('Error: $e');
    Get.snackbar('Erreur', 'Échec');
  }
}
```

### Après

```dart
Future<void> createMission() async {
  try {
    final response = await apiClient.post('/missions', data: data);
    
    await AppLogger.mission(
      'created',
      missionId: response.data['id'],
      data: {
        'montant': data['montant'],
        'categorie': data['categorie'],
      },
    );
    
    Get.snackbar('Succès', 'Mission créée');
    
  } on DioException catch (e) {
    // Déjà loggé par l'interceptor
    Get.snackbar('Erreur', 'Échec de création');
    
  } catch (e, stack) {
    await AppLogger.error(
      'Erreur inattendue lors de la création',
      error: e,
      stackTrace: stack,
      context: 'MissionController.createMission',
    );
  }
}
```

## 📱 Accès rapide au test screen

Ajoutez dans votre `SettingsScreen` :

```dart
ListTile(
  leading: const Icon(Icons.bug_report),
  title: const Text('Test Telegram Logger'),
  onTap: () => Get.to(() => const TelegramTestScreen()),
)
```
