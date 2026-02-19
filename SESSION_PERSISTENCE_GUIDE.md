# Guide de Persistance de Session - ProsArtisan

## Vue d'ensemble

Le système de persistance de session permet aux utilisateurs de rester connectés même après avoir fermé et rouvert l'application. Cette fonctionnalité améliore considérablement l'expérience utilisateur.

---

## Architecture

### Composants Principaux

1. **SplashScreen** - Écran de démarrage qui vérifie l'authentification
2. **AuthController** - Gère l'état d'authentification
3. **DioClient** - Intercepte les requêtes et gère le token
4. **PreferencesManager** - Gère les préférences utilisateur
5. **FlutterSecureStorage** - Stockage sécurisé des données sensibles

---

## Flux de Démarrage de l'Application

```
App Launch
    ↓
SplashScreen (2 secondes d'animation)
    ↓
AuthController.checkAuthStatus()
    ↓
Token existe? ──NO──→ Onboarding vu? ──NO──→ OnboardingScreen
    │                        │
    │                       YES
    │                        ↓
    │                   LoginScreen
    │
   YES
    ↓
Fetch User Data (/api/auth/me)
    ↓
Success? ──NO──→ Clear Session → LoginScreen
    │
   YES
    ↓
Navigate to Home (selon le rôle)
```

---

## Stockage Sécurisé

### Données Stockées

Toutes les données sont stockées de manière sécurisée avec `FlutterSecureStorage`:

| Clé | Type | Description |
|-----|------|-------------|
| `auth_token` | String | Token JWT d'authentification |
| `user_data` | JSON String | Données de l'utilisateur |
| `has_seen_onboarding` | Boolean | Si l'utilisateur a vu l'onboarding |
| `remember_me` | Boolean | Préférence "Se souvenir de moi" |
| `last_login_email` | String | Dernier email utilisé |

### Sécurité

- **Android**: Utilise EncryptedSharedPreferences
- **iOS**: Utilise Keychain
- **Chiffrement**: AES-256
- **Protection**: Les données sont inaccessibles sans déverrouillage du device

---

## Fichiers Modifiés

### 1. main.dart

**Changements**:
- Ajout de `WidgetsFlutterBinding.ensureInitialized()`
- Changement de `home: OnboardingScreen()` → `home: SplashScreen()`

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  AppBindings().dependencies();
  runApp(const ProsArtisanApp());
}
```

### 2. splash_screen.dart (NOUVEAU)

**Localisation**: `frontend/lib/features/auth/presentation/screens/splash_screen.dart`

**Fonctionnalités**:
- Animation de démarrage (fade + scale)
- Vérification automatique de l'authentification
- Navigation intelligente selon l'état
- Gestion du premier lancement

**Durée**: 2 secondes d'animation + temps de vérification

### 3. auth_controller.dart

**Améliorations**:

#### checkAuthStatus()
```dart
Future<void> checkAuthStatus() async {
  final token = await _dioClient.getAuthToken();
  
  if (token != null && token.isNotEmpty) {
    await fetchCurrentUser();
    
    if (currentUser.value != null) {
      isAuthenticated.value = true;
    } else {
      await _clearSession();
    }
  }
}
```

#### _clearSession() (NOUVEAU)
```dart
Future<void> _clearSession() async {
  currentUser.value = null;
  isAuthenticated.value = false;
  await _dioClient.clearAuthToken();
  await _storage.delete(key: ApiConstants.userDataKey);
}
```

#### logout() (AMÉLIORÉ)
- Appelle le backend pour invalider le token
- Nettoie la session locale
- Gère les erreurs réseau

### 4. dio_client.dart

**Amélioration de l'intercepteur d'erreurs**:

```dart
@override
void onError(DioException err, ErrorInterceptorHandler handler) {
  if (err.response?.statusCode == 401) {
    // Token expiré ou invalide
    _storage.delete(key: ApiConstants.authTokenKey);
    _storage.delete(key: ApiConstants.userDataKey);
  }
  handler.next(err);
}
```

### 5. preferences_manager.dart (NOUVEAU)

**Localisation**: `frontend/lib/core/storage/preferences_manager.dart`

**Méthodes**:
- `hasSeenOnboarding()` - Vérifie si l'onboarding a été vu
- `setOnboardingSeen()` - Marque l'onboarding comme vu
- `isRememberMeEnabled()` - Vérifie la préférence "Se souvenir"
- `setRememberMe(bool)` - Définit la préférence
- `getLastLoginEmail()` - Récupère le dernier email
- `saveLastLoginEmail(String)` - Sauvegarde l'email
- `clearAll()` - Efface toutes les préférences

### 6. onboarding_screen.dart

**Changement**:
```dart
void _completeOnboarding() {
  PreferencesManager().setOnboardingSeen();
  Get.offAll(() => const LoginScreen());
}
```

---

## Scénarios d'Utilisation

### Scénario 1: Premier Lancement

```
1. Utilisateur ouvre l'app pour la première fois
2. SplashScreen s'affiche
3. Aucun token trouvé
4. has_seen_onboarding = false
5. → Navigation vers OnboardingScreen
6. Utilisateur complète l'onboarding
7. has_seen_onboarding = true
8. → Navigation vers LoginScreen
```

### Scénario 2: Lancement Après Connexion

```
1. Utilisateur ouvre l'app
2. SplashScreen s'affiche
3. Token trouvé dans le stockage
4. Appel API: GET /api/auth/me
5. Réponse 200 OK avec données utilisateur
6. currentUser.value = User
7. isAuthenticated.value = true
8. → Navigation vers HomeScreen (selon rôle)
```

### Scénario 3: Token Expiré

```
1. Utilisateur ouvre l'app
2. SplashScreen s'affiche
3. Token trouvé dans le stockage
4. Appel API: GET /api/auth/me
5. Réponse 401 Unauthorized
6. Intercepteur détecte l'erreur
7. Suppression du token et user_data
8. _clearSession() appelé
9. → Navigation vers LoginScreen
```

### Scénario 4: Déconnexion Manuelle

```
1. Utilisateur clique sur "Déconnexion"
2. AuthController.logout() appelé
3. Appel API: POST /api/auth/logout
4. Suppression locale du token et données
5. currentUser.value = null
6. isAuthenticated.value = false
7. → Navigation vers LoginScreen
```

### Scénario 5: Lancement Après Onboarding

```
1. Utilisateur ouvre l'app (pas connecté)
2. SplashScreen s'affiche
3. Aucun token trouvé
4. has_seen_onboarding = true
5. → Navigation directe vers LoginScreen (skip onboarding)
```

---

## Gestion des Erreurs

### Erreur Réseau au Démarrage

**Problème**: Pas de connexion internet lors de la vérification du token

**Solution**:
```dart
try {
  await fetchCurrentUser();
} catch (e) {
  if (e is DioException && e.type == DioExceptionType.connectionTimeout) {
    // Afficher un message d'erreur
    // Permettre à l'utilisateur de réessayer
  }
}
```

### Token Corrompu

**Problème**: Token présent mais invalide

**Solution**: L'intercepteur détecte le 401 et nettoie automatiquement

### Données Utilisateur Incomplètes

**Problème**: Token valide mais données utilisateur manquantes

**Solution**:
```dart
if (currentUser.value == null) {
  await _clearSession();
}
```

---

## Tests

### Test 1: Persistance de Session

1. Se connecter avec un compte
2. Fermer complètement l'application
3. Rouvrir l'application
4. **Résultat attendu**: L'utilisateur est automatiquement connecté

### Test 2: Expiration de Token

1. Se connecter
2. Modifier manuellement le token dans le stockage (invalide)
3. Rouvrir l'application
4. **Résultat attendu**: Redirection vers LoginScreen

### Test 3: Premier Lancement

1. Désinstaller et réinstaller l'application
2. Ouvrir l'application
3. **Résultat attendu**: OnboardingScreen s'affiche

### Test 4: Déconnexion

1. Se connecter
2. Cliquer sur "Déconnexion"
3. Rouvrir l'application
4. **Résultat attendu**: LoginScreen s'affiche (pas d'auto-login)

### Test 5: Skip Onboarding

1. Voir l'onboarding une fois
2. Se déconnecter
3. Rouvrir l'application
4. **Résultat attendu**: LoginScreen directement (skip onboarding)

---

## Commandes de Test

### Vérifier le Stockage (Debug)

```dart
// Dans n'importe quel écran
final storage = FlutterSecureStorage();

// Lire le token
final token = await storage.read(key: 'auth_token');
print('Token: $token');

// Lire les données utilisateur
final userData = await storage.read(key: 'user_data');
print('User Data: $userData');

// Vérifier onboarding
final hasSeenOnboarding = await storage.read(key: 'has_seen_onboarding');
print('Has Seen Onboarding: $hasSeenOnboarding');
```

### Effacer le Stockage (Debug)

```dart
final storage = FlutterSecureStorage();
await storage.deleteAll();
print('All storage cleared');
```

### Simuler Token Expiré

```dart
// Remplacer le token par un invalide
final storage = FlutterSecureStorage();
await storage.write(key: 'auth_token', value: 'invalid_token_123');
```

---

## Optimisations Futures

### 1. Refresh Token

Implémenter un système de refresh token pour renouveler automatiquement les tokens expirés:

```dart
Future<bool> refreshToken() async {
  final refreshToken = await _storage.read(key: 'refresh_token');
  if (refreshToken != null) {
    final response = await _dio.post('/auth/refresh', data: {
      'refresh_token': refreshToken,
    });
    // Sauvegarder le nouveau token
  }
}
```

### 2. Biométrie

Ajouter l'authentification biométrique pour plus de sécurité:

```dart
import 'package:local_auth/local_auth.dart';

Future<bool> authenticateWithBiometrics() async {
  final localAuth = LocalAuthentication();
  return await localAuth.authenticate(
    localizedReason: 'Authentifiez-vous pour accéder à l'application',
  );
}
```

### 3. Mode Hors Ligne

Permettre l'accès limité en mode hors ligne:

```dart
if (token != null && !hasInternet) {
  // Charger les données en cache
  // Permettre la navigation limitée
}
```

### 4. Session Timeout

Déconnecter automatiquement après une période d'inactivité:

```dart
class SessionManager {
  Timer? _inactivityTimer;
  
  void resetTimer() {
    _inactivityTimer?.cancel();
    _inactivityTimer = Timer(Duration(minutes: 30), () {
      // Auto logout
    });
  }
}
```

---

## Sécurité

### Bonnes Pratiques Implémentées

✅ Stockage sécurisé avec chiffrement
✅ Token dans les headers (pas dans l'URL)
✅ Nettoyage automatique en cas d'erreur 401
✅ Pas de stockage de mot de passe
✅ Validation du token à chaque démarrage

### Recommandations Supplémentaires

- [ ] Implémenter le refresh token
- [ ] Ajouter un timeout de session
- [ ] Logger les tentatives de connexion
- [ ] Implémenter la détection de jailbreak/root
- [ ] Ajouter un PIN code optionnel

---

## Dépannage

### Problème: L'utilisateur n'est pas auto-connecté

**Causes possibles**:
1. Token supprimé ou corrompu
2. Backend retourne 401
3. Erreur réseau

**Solution**:
```bash
# Vérifier les logs
flutter logs | grep "Token"
flutter logs | grep "authenticated"
```

### Problème: Boucle infinie sur SplashScreen

**Cause**: Erreur dans checkAuthStatus()

**Solution**:
```dart
// Ajouter un timeout
Future.delayed(Duration(seconds: 5), () {
  if (mounted) {
    Get.offAll(() => const LoginScreen());
  }
});
```

### Problème: Onboarding s'affiche à chaque fois

**Cause**: has_seen_onboarding pas sauvegardé

**Solution**:
```dart
// Vérifier dans onboarding_screen.dart
await PreferencesManager().setOnboardingSeen();
```

---

## Logs Utiles

### Démarrage Réussi avec Auto-Login

```
I/flutter: Token found, fetching user data...
I/flutter: User authenticated: John Doe
I/flutter: Auto-login successful for: John Doe
[Navigation vers HomeScreen]
```

### Démarrage Sans Session

```
I/flutter: No token found, user not authenticated
I/flutter: Onboarding already seen, skipping...
[Navigation vers LoginScreen]
```

### Token Expiré

```
I/flutter: Token found, fetching user data...
I/flutter: Failed to fetch user: Unauthorized
I/flutter: Token invalid, clearing session...
[Navigation vers LoginScreen]
```

---

**Date de création**: 18 février 2026
**Version**: 1.0
**Statut**: Implémenté et testé
