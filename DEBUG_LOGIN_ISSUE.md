# Debug: Problème de Navigation après Login

## Symptômes
- Le login réussit (Status Code: 200)
- Les données utilisateur sont reçues correctement
- L'utilisateur reste sur l'écran de login au lieu d'être redirigé

## Modifications Apportées

### 1. Ajout de logs détaillés dans `LoginScreen`

**Fichier**: `frontend/lib/features/auth/presentation/screens/login_screen.dart`

Ajout de logs pour tracer:
- Le nom et rôle de l'utilisateur après login
- Le processus de navigation
- Le rôle après traitement (toLowerCase, trim)

### 2. Ajout de `toString()` dans le modèle User

**Fichier**: `frontend/lib/shared/models/user_model.dart`

```dart
@override
String toString() {
  return 'User(id: $id, name: $name, email: $email, role: $role, kycStatus: $kycStatus)';
}
```

### 3. Amélioration de la gestion du SearchController

**Fichier**: `frontend/lib/features/home/presentation/screens/home_screen.dart`

Initialisation du controller dans `initState()` avec gestion d'erreurs.

## Comment Tester

### Étape 1: Clean et Rebuild
```bash
cd frontend
flutter clean
flutter pub get
flutter pub run build_runner build --delete-conflicting-outputs
```

### Étape 2: Lancer l'application
```bash
flutter run
```

### Étape 3: Tenter de se connecter

Utilisez les credentials de test:
- Email: `client@test.com`
- Password: `password`

### Étape 4: Observer les logs

Dans la console, vous devriez voir:
```
User logged in: [Nom], Role: client
Navigating to home for role: client
Role key after processing: client
Navigating to HomeScreen
```

## Problèmes Potentiels et Solutions

### Problème 1: Le user est null après login

**Symptôme**: Log affiche "ERROR: Login success but user is null"

**Cause**: Le parsing JSON échoue ou la structure de réponse ne correspond pas

**Solution**:
1. Vérifier la réponse du backend dans les logs
2. Vérifier que `auth_response.g.dart` est bien généré
3. Vérifier la structure JSON retournée par `/api/auth/login`

**Test backend**:
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"client@test.com","password":"password"}'
```

### Problème 2: Navigation ne se déclenche pas

**Symptôme**: Logs montrent "Navigating to HomeScreen" mais rien ne se passe

**Cause**: Erreur dans le HomeScreen qui empêche le chargement

**Solution**:
1. Vérifier les logs pour des erreurs après "Navigating to HomeScreen"
2. Vérifier que toutes les dépendances du HomeScreen sont disponibles
3. Tester la navigation directement:

```dart
// Dans login_screen.dart, remplacer temporairement:
Get.offAll(() => Scaffold(
  appBar: AppBar(title: Text('Test')),
  body: Center(child: Text('Navigation works!')),
));
```

### Problème 3: SearchController échoue à s'initialiser

**Symptôme**: Erreur lors du chargement du HomeScreen

**Cause**: Dépendances manquantes pour ArtisanSearchController

**Solution**:
1. Vérifier que `SearchService` est bien enregistré dans `AppBindings`
2. Vérifier les logs pour "Error initializing search controller"
3. Initialiser le controller avant la navigation:

```dart
// Dans login_screen.dart, avant la navigation:
Get.put(artisan_search.ArtisanSearchController());
```

### Problème 4: Le rôle ne correspond pas

**Symptôme**: Log montre "Role not matched, using default HomeScreen"

**Cause**: Le rôle dans la base de données ne correspond pas exactement

**Solution**:
1. Vérifier le log "Role key after processing"
2. Vérifier dans la base de données:

```sql
SELECT id, name, email, role FROM users WHERE email = 'client@test.com';
```

3. S'assurer que le rôle est exactement "client" (pas "Client" ou "CLIENT")

## Checklist de Débogage

- [ ] Les logs montrent "User logged in: ..."
- [ ] Le rôle affiché est correct
- [ ] Les logs montrent "Navigating to HomeScreen"
- [ ] Aucune erreur après la tentative de navigation
- [ ] Le token est bien sauvegardé (vérifier avec Flutter DevTools)
- [ ] Le backend retourne bien un objet user complet
- [ ] Les fichiers .g.dart sont à jour

## Commandes Utiles

### Voir les logs en temps réel
```bash
flutter logs
```

### Vérifier l'état de GetX
Dans le code, ajouter temporairement:
```dart
print('Registered controllers: ${Get.isRegistered<AuthController>()}');
print('Current route: ${Get.currentRoute}');
```

### Forcer un hot restart
```bash
# Dans le terminal où flutter run est actif
# Appuyer sur 'R' (majuscule)
```

### Vérifier le stockage sécurisé
```dart
final storage = FlutterSecureStorage();
final token = await storage.read(key: 'auth_token');
print('Stored token: $token');
```

## Solution Temporaire de Contournement

Si le problème persiste, vous pouvez temporairement contourner en naviguant manuellement:

```dart
// Dans login_screen.dart, après le login réussi:
if (success) {
  // Force navigation
  WidgetsBinding.instance.addPostFrameCallback((_) {
    Get.offAll(() => const HomeScreen());
  });
}
```

## Prochaines Étapes

1. Relancer l'application avec `flutter run`
2. Tenter de se connecter
3. Copier TOUS les logs de la console
4. Identifier à quelle étape le processus s'arrête
5. Appliquer la solution correspondante

## Logs Attendus (Succès)

```
I/flutter: User logged in: Test Client, Role: client
I/flutter: Navigating to home for role: client
I/flutter: Role key after processing: client
I/flutter: Navigating to HomeScreen
[Navigation vers HomeScreen réussie]
```

## Logs Attendus (Échec)

```
I/flutter: User logged in: Test Client, Role: client
I/flutter: Navigating to home for role: client
I/flutter: Role key after processing: client
I/flutter: Navigating to HomeScreen
[ERROR] Exception caught: ...
```

---

**Date**: 18 février 2026
**Statut**: En cours de débogage
