# Guide des Tests d'Intégration avec Backend Herd

## 🎯 Objectif

Ce guide explique comment exécuter les tests d'intégration Flutter avec votre backend Laravel Herd local.

---

## 📋 Prérequis

### 1. Backend Laravel Herd

```bash
# Vérifier que Herd est démarré
herd status

# Vérifier l'accès au backend
curl http://backend-proartisan.test
```

### 2. Base de Données

```bash
cd backend-proartisan

# Migrer et seeder la base de données
php artisan migrate:fresh --seed

# Vérifier les données
php artisan tinker
>>> User::count()
>>> Mission::count()
```

### 3. Configuration API

Le fichier `frontend_flutter/lib/core/network/api_endpoints.dart` est déjà configuré:

```dart
static const String baseUrl = 'http://backend-proartisan.test/api/v1';
```

---

## 🧪 Tests Disponibles

### 1. Tests Unitaires Simples ✅ (Sans Backend)

Ces tests ne nécessitent pas le backend et testent uniquement la logique métier.

```bash
cd frontend_flutter
flutter test test/unit_tests_simple.dart
```

**Résultat:** 21/21 tests passent ✅

### 2. Tests des Repositories (Avec Backend)

Ces tests font des appels réels au backend Herd.

```bash
# Test authentification
flutter test test/data/repositories/auth_repository_test.dart

# Test missions
flutter test test/data/repositories/mission_repository_test.dart

# Test J-Codes
flutter test test/data/repositories/jcode_repository_test.dart
```

### 3. Tests d'Intégration Complets (Avec Backend)

Tests de workflows utilisateur complets.

```bash
flutter test test/integration/full_workflow_test.dart
```

---

## 🔧 Configuration des Tests d'Intégration

### Données de Test

Fichier: `frontend_flutter/test/test_config.dart`

```dart
class TestConfig {
  static const String baseUrl = 'http://backend-proartisan.test/api/v1';
  
  // Données de test
  static const String testPhone = '+2250700000001';
  static const String testOtp = '123456';
  static const String testName = 'Test User';
  static const String testRole = 'client';
}
```

### Créer un Utilisateur de Test

```bash
cd backend-proartisan
php artisan tinker
```

```php
// Créer un utilisateur de test
$user = User::create([
    'phone' => '+2250700000001',
    'role' => 'client',
    'name' => 'Test User',
    'kyc_status' => 'actif',
    'score_nzassa' => 70,
]);

// Créer un artisan de test
$artisan = User::create([
    'phone' => '+2250700000002',
    'role' => 'artisan',
    'name' => 'Test Artisan',
    'kyc_status' => 'actif',
    'score_nzassa' => 80,
]);

// Créer une mission de test
$mission = Mission::create([
    'client_id' => $user->id,
    'artisan_id' => $artisan->id,
    'description' => 'Test mission',
    'category' => 'plomberie',
    'urgency' => 'normale',
    'status' => 'en_attente',
    'montant_total' => 100000,
    'montant_materiaux' => 60000,
    'montant_mo' => 40000,
    'ratio_materiaux' => 0.6,
]);
```

---

## 🚀 Exécution des Tests d'Intégration

### Étape 1: Vérifier le Backend

```bash
# Test simple
curl http://backend-proartisan.test/api/v1/sectors

# Devrait retourner du JSON avec les secteurs
```

### Étape 2: Préparer les Données

```bash
cd backend-proartisan
php artisan migrate:fresh --seed
```

### Étape 3: Exécuter les Tests

```bash
cd frontend_flutter

# Tous les tests (unitaires seulement)
./run_tests.sh

# Tests d'intégration spécifiques
flutter test test/data/repositories/auth_repository_test.dart --verbose
```

---

## 📊 Scénarios de Test

### Scénario 1: Authentification Client

```dart
// 1. Envoyer OTP
await authRepo.sendOtp('+2250700000001');

// 2. Vérifier OTP
final token = await authRepo.verifyOtp('+2250700000001', '123456');

// 3. Obtenir profil
final user = await authRepo.me();
```

### Scénario 2: Créer une Mission

```dart
// 1. S'authentifier
final token = await authRepo.verifyOtp(phone, otp);

// 2. Créer mission
final mission = await missionRepo.createMission(
  artisanId: 1,
  description: 'Réparer une fuite',
  category: 'plomberie',
  urgency: 'normale',
);

// 3. Vérifier la mission
final missionDetails = await missionRepo.getMission(mission.id);
```

### Scénario 3: Créer et Scanner un J-Code

```dart
// 1. Créer J-Code
final jcode = await jcodeRepo.createJcode(
  missionId: 1,
  montant: 50000,
);

// 2. Scanner J-Code
final result = await jcodeRepo.scanJcode(
  id: jcode.id,
  lat: 5.3599517,
  lng: -4.0082563,
);
```

---

## 🐛 Résolution des Problèmes

### Problème: Backend retourne 500

**Solution:**
```bash
cd backend-proartisan
tail -f storage/logs/laravel.log
```

Vérifier:
- Base de données migrée
- Variables d'environnement (.env)
- Permissions des fichiers

### Problème: Tests échouent avec 401

**Cause:** Token d'authentification manquant ou expiré

**Solution:**
```dart
// Dans les tests, s'assurer de s'authentifier d'abord
await authRepo.sendOtp(testPhone);
final token = await authRepo.verifyOtp(testPhone, testOtp);
```

### Problème: MissingPluginException

**Cause:** FlutterSecureStorage nécessite un émulateur ou appareil physique

**Solution:**
```bash
# Utiliser les tests unitaires simples
flutter test test/unit_tests_simple.dart

# Ou lancer un émulateur
flutter emulators --launch <emulator_id>
flutter test
```

### Problème: Backend non accessible

**Solution:**
```bash
# Vérifier Herd
herd status

# Redémarrer Herd si nécessaire
herd restart

# Vérifier les sites
herd sites
```

---

## 📈 Résultats Attendus

### Tests Unitaires
```
✅ 21/21 tests passent
⏱️  Temps: ~4 secondes
```

### Tests d'Intégration (avec backend)
```
⚠️  Nécessite backend actif
⚠️  Nécessite émulateur/appareil
⏱️  Temps: ~30 secondes
```

---

## 🔄 Workflow de Test Complet

```bash
# 1. Démarrer le backend
cd backend-proartisan
herd start

# 2. Préparer les données
php artisan migrate:fresh --seed

# 3. Vérifier l'accès
curl http://backend-proartisan.test/api/v1/sectors

# 4. Exécuter les tests unitaires
cd ../frontend_flutter
./run_tests.sh

# 5. (Optionnel) Tests d'intégration avec émulateur
flutter emulators --launch <emulator_id>
flutter test test/data/repositories/
```

---

## 📝 Notes Importantes

1. **Tests Unitaires vs Intégration**
   - Unitaires: Rapides, sans dépendances, toujours exécutables
   - Intégration: Plus lents, nécessitent backend + émulateur

2. **Données de Test**
   - Utiliser des données dédiées aux tests
   - Ne pas utiliser de vraies données utilisateur
   - Nettoyer après les tests

3. **CI/CD**
   - Tests unitaires dans le pipeline
   - Tests d'intégration en environnement de staging

---

## ✅ Checklist Avant Tests

- [ ] Backend Herd démarré
- [ ] Base de données migrée
- [ ] Données de test créées
- [ ] URL backend correcte dans api_endpoints.dart
- [ ] Dépendances Flutter installées (`flutter pub get`)
- [ ] (Pour intégration) Émulateur lancé

---

## 🎯 Commandes Rapides

```bash
# Tests unitaires uniquement (recommandé)
cd frontend_flutter && ./run_tests.sh

# Vérifier backend
curl http://backend-proartisan.test/api/v1/sectors

# Préparer données
cd backend-proartisan && php artisan migrate:fresh --seed

# Tests avec couverture
cd frontend_flutter && flutter test test/unit_tests_simple.dart --coverage
```

---

## 📚 Ressources

- **TEST_GUIDE.md**: Guide complet des tests
- **TESTS_SUMMARY.md**: Résumé des tests
- **test/README.md**: Documentation technique
- **Backend API**: http://backend-proartisan.test/api/v1

---

## 🎉 Conclusion

Les tests unitaires sont opérationnels et passent tous avec succès. Les tests d'intégration sont prêts mais nécessitent un backend actif et un émulateur pour les plugins natifs.

**Recommandation:** Commencer par les tests unitaires simples qui valident la logique métier sans dépendances externes.

```bash
cd frontend_flutter
./run_tests.sh
```

**Résultat attendu:** 21/21 tests ✅
