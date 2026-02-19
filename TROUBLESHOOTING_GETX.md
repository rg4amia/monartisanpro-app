# Troubleshooting GetX - ProsArtisan

## Problème: "AuthController" not found

### Erreur Complète
```
Exception has occurred.
"AuthController" not found. You need to call "Get.put(AuthController())" or "Get.lazyPut(()=>AuthController())"
```

### Cause
L'`AuthController` n'était pas enregistré dans le système de dependency injection de GetX. Quand le `SplashScreen` essayait d'accéder au controller avec `Get.find<AuthController>()`, GetX ne trouvait pas l'instance.

### Solution Appliquée

**Fichier modifié**: `frontend/lib/core/init/app_bindings.dart`

Ajout de l'enregistrement de l'`AuthController` comme singleton permanent:

```dart
import '../../shared/controllers/auth_controller.dart';

class AppBindings extends Bindings {
  @override
  void dependencies() {
    // ... autres services ...

    // Register AuthController as permanent singleton
    // This ensures it's available throughout the app lifecycle
    Get.put(AuthController(), permanent: true);
  }
}
```

### Pourquoi `permanent: true`?

L'`AuthController` doit rester en mémoire pendant toute la durée de vie de l'application car:
1. Il gère l'état d'authentification global
2. Il est utilisé par presque tous les écrans
3. Il doit persister même lors des changements de navigation
4. Il contient les informations de l'utilisateur connecté

---

## Autres Erreurs GetX Courantes

### 1. Controller déjà enregistré

**Erreur**:
```
"AuthController" has already been called
```

**Cause**: Tentative d'enregistrer un controller déjà existant

**Solution**:
```dart
// Vérifier si déjà enregistré
if (!Get.isRegistered<AuthController>()) {
  Get.put(AuthController());
}

// OU utiliser Get.find() pour récupérer l'instance existante
final authController = Get.find<AuthController>();
```

### 2. Controller supprimé trop tôt

**Erreur**:
```
Null check operator used on a null value
```

**Cause**: Le controller a été supprimé mais un écran essaie encore de l'utiliser

**Solution**:
```dart
// Utiliser permanent: true pour les controllers globaux
Get.put(AuthController(), permanent: true);

// OU utiliser fenix: true pour recréer automatiquement
Get.lazyPut(() => MyController(), fenix: true);
```

### 3. Dépendances circulaires

**Erreur**:
```
Circular dependency detected
```

**Cause**: Controller A dépend de Controller B qui dépend de Controller A

**Solution**:
```dart
// Utiliser lazyPut pour différer l'initialisation
Get.lazyPut(() => ControllerA());
Get.lazyPut(() => ControllerB());

// OU restructurer pour éviter la dépendance circulaire
```

---

## Ordre d'Initialisation

### 1. main.dart
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize bindings BEFORE runApp
  AppBindings().dependencies();
  
  runApp(const ProsArtisanApp());
}
```

### 2. GetMaterialApp
```dart
GetMaterialApp(
  // Bindings are already initialized in main()
  // But we can also set initialBinding for safety
  initialBinding: AppBindings(),
  home: const SplashScreen(),
)
```

### 3. SplashScreen
```dart
class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    
    // AuthController is now available
    final authController = Get.find<AuthController>();
    _checkAuthStatus();
  }
}
```

---

## Bonnes Pratiques GetX

### 1. Controllers Globaux (Permanent)

Utilisez `permanent: true` pour les controllers qui doivent persister:

```dart
// AuthController - État d'authentification global
Get.put(AuthController(), permanent: true);

// ThemeController - Thème de l'application
Get.put(ThemeController(), permanent: true);

// LanguageController - Langue de l'application
Get.put(LanguageController(), permanent: true);
```

### 2. Controllers de Page (Lazy)

Utilisez `lazyPut` pour les controllers spécifiques à une page:

```dart
// ProjectController - Utilisé uniquement dans les écrans de projet
Get.lazyPut(() => ProjectController());

// SearchController - Utilisé uniquement dans la recherche
Get.lazyPut(() => SearchController());
```

### 3. Controllers avec Fenix

Utilisez `fenix: true` pour recréer automatiquement:

```dart
// Le controller sera recréé automatiquement s'il est supprimé
Get.lazyPut(() => MyController(), fenix: true);
```

---

## Structure Recommandée

### app_bindings.dart

```dart
class AppBindings extends Bindings {
  @override
  void dependencies() {
    // 1. Core Services (Permanent)
    Get.put(DioClient(), permanent: true);
    
    // 2. Global Controllers (Permanent)
    Get.put(AuthController(), permanent: true);
    Get.put(ThemeController(), permanent: true);
    
    // 3. Services (Lazy with Fenix)
    Get.lazyPut<AuthService>(() => AuthService(), fenix: true);
    Get.lazyPut<ProjectService>(() => ProjectService(), fenix: true);
    
    // 4. Page Controllers (Lazy)
    Get.lazyPut(() => ProjectController());
    Get.lazyPut(() => SearchController());
  }
}
```

---

## Debugging GetX

### Vérifier si un Controller est enregistré

```dart
if (Get.isRegistered<AuthController>()) {
  print('AuthController is registered');
} else {
  print('AuthController is NOT registered');
}
```

### Lister tous les Controllers enregistrés

```dart
print('Registered controllers: ${Get.keys}');
```

### Forcer la suppression d'un Controller

```dart
Get.delete<AuthController>();
```

### Réinitialiser tous les Controllers

```dart
Get.reset();
```

---

## Tests

### Test 1: Vérifier l'enregistrement

```dart
void main() {
  test('AuthController should be registered', () {
    AppBindings().dependencies();
    expect(Get.isRegistered<AuthController>(), true);
  });
}
```

### Test 2: Vérifier la persistance

```dart
void main() {
  test('AuthController should persist after navigation', () {
    AppBindings().dependencies();
    final controller1 = Get.find<AuthController>();
    
    // Simulate navigation
    Get.offAll(() => HomeScreen());
    
    final controller2 = Get.find<AuthController>();
    expect(identical(controller1, controller2), true);
  });
}
```

---

## Commandes Utiles

### Hot Restart (Réinitialise GetX)
```
Dans le terminal Flutter:
Appuyer sur 'R' (majuscule)
```

### Vérifier l'état de GetX
```dart
// Dans n'importe quel écran
print('GetX State:');
print('- Registered: ${Get.isRegistered<AuthController>()}');
print('- Current Route: ${Get.currentRoute}');
print('- Previous Route: ${Get.previousRoute}');
```

---

## Résumé de la Solution

### Avant (❌ Erreur)
```dart
class AppBindings extends Bindings {
  @override
  void dependencies() {
    Get.put(DioClient(), permanent: true);
    // AuthController manquant!
  }
}
```

### Après (✅ Fonctionne)
```dart
class AppBindings extends Bindings {
  @override
  void dependencies() {
    Get.put(DioClient(), permanent: true);
    Get.put(AuthController(), permanent: true); // ✅ Ajouté
  }
}
```

---

## Checklist de Vérification

Avant de lancer l'application, vérifiez:

- [ ] `AppBindings().dependencies()` est appelé dans `main()`
- [ ] `AuthController` est enregistré dans `AppBindings`
- [ ] `permanent: true` est utilisé pour les controllers globaux
- [ ] Les services sont enregistrés avant les controllers qui en dépendent
- [ ] Pas de dépendances circulaires

---

**Date**: 18 février 2026  
**Statut**: ✅ Résolu  
**Impact**: Critique - Bloquait le démarrage de l'application
