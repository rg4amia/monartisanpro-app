# Tests Unitaires ProsArtisan Flutter

## Configuration

Deux familles de tests coexistent :

- **Tests unitaires et widget** (la grande majorité) : autonomes, sans réseau.
  `flutter test` les exécute, en local comme en CI.
- **Tests d'intégration**, étiquetés `@Tags(['integration'])`
  (`test/data/repositories/*_repository_test.dart`,
  `test/integration/full_workflow_test.dart`) : ils appellent le backend
  Laravel Herd réel (`http://backend-proartisan.test/api/v1`). `dart_test.yaml`
  les **ignore par défaut** (comptés `~` dans le résultat) : sans backend, ils
  échouaient tous et masquaient les vraies régressions.

## Structure des Tests

```
test/
├── core/
│   └── network/
│       └── api_client_test.dart          # Tests du client API
├── data/
│   ├── models/
│   │   └── user_model_test.dart          # Tests des modèles
│   └── repositories/
│       ├── auth_repository_test.dart     # Tests authentification
│       ├── mission_repository_test.dart  # Tests missions
│       └── jcode_repository_test.dart    # Tests J-Codes
├── integration/
│   └── full_workflow_test.dart           # Tests d'intégration complets
├── helpers/
│   └── test_helpers.dart                 # Utilitaires de test
└── test_config.dart                      # Configuration des tests
```

## Exécution des Tests

### Tests unitaires et widget (par défaut)

```bash
cd frontend_flutter
flutter test
```

### Tests d'intégration (backend Herd démarré)

```bash
flutter test --tags integration --run-skipped
```

### Tout d'un coup

```bash
./frontend_flutter/test_runner.sh   # depuis la racine du dépôt
```

Le script lance les tests unitaires, puis les tests d'intégration si le
backend Herd répond.

### Tests spécifiques

```bash
# Tests d'un fichier spécifique
flutter test test/data/repositories/auth_repository_test.dart

# Tests d'un groupe spécifique
flutter test --name "AuthRepository"

# Tests avec verbose
flutter test --verbose
```

### Avec couverture de code

```bash
flutter test --coverage
genhtml coverage/lcov.info -o coverage/html
open coverage/html/index.html
```

## Prérequis des tests d'intégration

1. **Backend Laravel Herd démarré**
   - Assurez-vous que `http://backend-proartisan.test` est accessible
   - Vérifiez avec: `curl http://backend-proartisan.test/api/v1/sectors`

2. **Dépendances Flutter installées**

   ```bash
   flutter pub get
   ```

3. **Base de données configurée**
   - Les tests utilisent le backend réel
   - Assurez-vous que la base de données est migrée et seedée

## Types de Tests

### Tests Unitaires

- **Models**: Sérialisation/désérialisation JSON
- **API Client**: Configuration et intercepteurs
- **Token Storage**: Gestion sécurisée des tokens

### Tests d'Intégration

- **Auth Repository**: Envoi OTP, vérification, inscription
- **Mission Repository**: CRUD missions, jalons, estimations
- **JCode Repository**: Création, scan, validation GPS

### Tests de Workflow Complet

- Workflow client: Auth → Créer mission → Suivre
- Workflow artisan: Auth → Accepter mission → Créer J-Code
- Workflow fournisseur: Auth → Scanner J-Code

## Gestion des Erreurs

Les tests vérifient les codes HTTP suivants:

- `200-299`: Succès
- `401`: Non authentifié
- `404`: Ressource non trouvée
- `422`: Erreur de validation

## Données de Test

Configurées dans `test_config.dart`:

- Téléphone: `+2250700000001`
- OTP: `123456`
- Nom: `Test User`
- Rôle: `client`

## Notes Importantes

1. **Tests avec backend réel**: les tests d'intégration utilisent le backend Herd réel, pas de mocks ; tout nouveau test de ce type doit porter `@Tags(['integration'])`, sans quoi il casserait `flutter test` et la CI
2. **Nettoyage**: Chaque test nettoie les données après exécution
3. **Authentification**: Certains tests nécessitent un token valide
4. **Données temporaires**: Les tests créent des données temporaires qui peuvent persister

## Dépannage

### Backend non accessible

```bash
# Vérifier que Herd est démarré
herd status

# Vérifier l'URL
curl http://backend-proartisan.test/api/v1/sectors
```

### Tests échouent avec 401

- Vérifiez que le token est valide
- Vérifiez la configuration de Sanctum dans le backend

### Tests échouent avec timeout

- Augmentez le timeout dans `test_config.dart`
- Vérifiez la performance du backend

## CI/CD

`.github/workflows/mobile-ci.yml` exécute `flutter analyze --fatal-infos`
puis `flutter test` sur toute la suite : aucun dossier à déclarer, un nouveau
fichier de test est exécuté d'office. Les tests d'intégration y restent
ignorés (aucun backend Laravel en CI).

## Contribution

Lors de l'ajout de nouvelles fonctionnalités:

1. Créer les tests unitaires correspondants
2. Ajouter les tests d'intégration si nécessaire
3. Mettre à jour ce README si besoin
4. Vérifier que tous les tests passent avant de commit
