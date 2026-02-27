# Tests Unitaires ProsArtisan Flutter

## Configuration

Les tests sont configurés pour utiliser le backend Laravel Herd:
- **URL Backend**: `http://backend-proartisan.test/api/v1`

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

### Tous les tests
```bash
./test_runner.sh
```

Ou directement avec Flutter:
```bash
cd frontend_flutter
flutter test
```

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

## Prérequis

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

1. **Tests avec backend réel**: Les tests utilisent le backend Herd réel, pas de mocks
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

Pour intégrer dans un pipeline CI/CD:

```yaml
# .github/workflows/test.yml
- name: Run Flutter tests
  run: |
    cd frontend_flutter
    flutter pub get
    flutter test --coverage
```

## Contribution

Lors de l'ajout de nouvelles fonctionnalités:
1. Créer les tests unitaires correspondants
2. Ajouter les tests d'intégration si nécessaire
3. Mettre à jour ce README si besoin
4. Vérifier que tous les tests passent avant de commit
