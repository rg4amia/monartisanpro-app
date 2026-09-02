# Guide de Tests ProsArtisan Flutter

## ✅ Tests Réussis

Tous les tests unitaires passent avec succès! 21 tests exécutés.

## Configuration Backend

L'application Flutter est configurée pour utiliser votre backend Laravel Herd:

```
URL: http://backend-proartisan.test/api/v1
```

## Types de Tests

### 1. Tests Unitaires Simples ✅

Tests des modèles et de la logique métier sans dépendances natives.

```bash
flutter test test/unit_tests_simple.dart
```

**Couverture:**

- Configuration API et endpoints
- Modèles (UserModel, JcodeModel, MissionModel)
- Sérialisation/désérialisation JSON
- Logique métier (Score ProsArtisan, KYC, J-Codes)
- Validation des règles business

### 2. Tests d'Intégration (Nécessitent le backend)

Tests avec appels réels au backend Laravel Herd.

**Note:** Ces tests nécessitent:

- Backend Laravel Herd démarré
- Base de données migrée et seedée
- Configuration des plugins natifs (FlutterSecureStorage)

```bash
# Vérifier que le backend est accessible
curl http://backend-proartisan.test/api/v1/sectors

# Exécuter les tests d'intégration
flutter test test/data/repositories/
flutter test test/integration/
```

## Résultats des Tests

### Tests Unitaires Simples

```
✅ API Configuration Tests (4 tests)
✅ UserModel Tests (5 tests)
✅ JcodeModel Tests (5 tests)
✅ MissionModel Tests (3 tests)
✅ Business Logic Tests (4 tests)

Total: 21 tests passés
```

## Structure des Tests

```
test/
├── unit_tests_simple.dart          ✅ Tests sans dépendances natives
├── data/
│   ├── models/
│   │   └── user_model_test.dart    Tests modèles
│   └── repositories/
│       ├── auth_repository_test.dart
│       ├── mission_repository_test.dart
│       └── jcode_repository_test.dart
├── integration/
│   └── full_workflow_test.dart     Tests workflow complets
├── helpers/
│   └── test_helpers.dart           Utilitaires
└── test_config.dart                Configuration
```

## Exécution des Tests

### Tous les tests unitaires simples

```bash
cd frontend_flutter
flutter test test/unit_tests_simple.dart
```

### Tests spécifiques

```bash
# Tests d'un groupe spécifique
flutter test test/unit_tests_simple.dart --name "UserModel"

# Tests avec verbose
flutter test test/unit_tests_simple.dart --verbose
```

### Avec couverture de code

```bash
flutter test test/unit_tests_simple.dart --coverage
genhtml coverage/lcov.info -o coverage/html
open coverage/html/index.html
```

## Tests Validés

### ✅ Configuration API

- URL backend Herd correcte
- Endpoints auth, missions, J-Codes configurés

### ✅ Modèles de Données

- **UserModel**: Sérialisation, KYC, Score ProsArtisan, Golden Marker
- **JcodeModel**: Statuts (actif, utilisé, expiré), format PA-XXXX
- **MissionModel**: Montants, ratios, règle référent (>2M FCFA)

### ✅ Logique Métier

- Score ProsArtisan >= 700 = Golden Marker
- Statuts KYC: en_attente, en_cours, actif, refuse
- Format J-Code: PA-XXXX
- Wallets non-négatifs
- Missions > 2M nécessitent un référent

## Tests d'Intégration avec Backend

Pour exécuter les tests d'intégration avec votre backend Herd:

### Prérequis

1. **Backend démarré**

   ```bash
   # Vérifier Herd
   curl http://backend-proartisan.test/api/v1/sectors
   ```

2. **Base de données**

   ```bash
   cd backend-proartisan
   php artisan migrate:fresh --seed
   ```

3. **Données de test**
   - Créer un utilisateur test
   - Créer quelques missions
   - Créer des J-Codes actifs

### Exécution

```bash
# Tests repositories
flutter test test/data/repositories/auth_repository_test.dart
flutter test test/data/repositories/mission_repository_test.dart
flutter test test/data/repositories/jcode_repository_test.dart

# Tests workflow complets
flutter test test/integration/full_workflow_test.dart
```

## Données de Test

Configurées dans `test_config.dart`:

```dart
baseUrl: 'http://backend-proartisan.test/api/v1'
testPhone: '+2250700000001'
testOtp: '123456'
testName: 'Test User'
testRole: 'client'
```

## Dépannage

### Backend non accessible

```bash
# Vérifier Herd
herd status

# Tester l'URL
curl http://backend-proartisan.test/api/v1/sectors
```

### Tests échouent avec MissingPluginException

Les tests d'intégration nécessitent un émulateur ou appareil physique pour FlutterSecureStorage.

Solution: Utiliser les tests unitaires simples qui ne dépendent pas des plugins natifs.

### Erreur 500 du backend

Vérifier les logs Laravel:

```bash
cd backend-proartisan
tail -f storage/logs/laravel.log
```

## CI/CD

Pour intégrer dans un pipeline:

```yaml
# .github/workflows/test.yml
- name: Run Flutter unit tests
  run: |
    cd frontend_flutter
    flutter pub get
    flutter test test/unit_tests_simple.dart --coverage
```

## Prochaines Étapes

1. ✅ Tests unitaires des modèles
2. ⏳ Tests d'intégration avec backend réel
3. ⏳ Tests E2E avec émulateur
4. ⏳ Tests de performance
5. ⏳ Tests de sécurité

## Contribution

Lors de l'ajout de nouvelles fonctionnalités:

1. Créer les tests unitaires dans `unit_tests_simple.dart`
2. Ajouter les tests d'intégration si nécessaire
3. Vérifier que tous les tests passent
4. Mettre à jour ce guide

## Support

Pour toute question sur les tests:

- Consulter `test/README.md`
- Vérifier la configuration dans `test_config.dart`
- Examiner les exemples dans `unit_tests_simple.dart`
