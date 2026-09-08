# Yandex Maps - Guide de dépannage

## Problèmes d'affichage de la carte

### 1. Carte blanche ou ne se charge pas

**Causes possibles :**

- Clé API invalide ou manquante
- Permissions de localisation non accordées
- Problème de connexion internet
- Configuration Android/iOS incorrecte

**Solutions :**

#### Configurer les clés (aucune clé n'est commitée)

Toutes les clés Yandex sont injectées **au build**, jamais écrites en dur.

1. Copier le gabarit et renseigner les valeurs :

```bash
cd frontend_flutter
cp env.example.json env.json      # env.json est gitignoré
```

2. Sur poste dev Android, ajouter aussi la clé MapKit à `android/local.properties`
   (gitignoré, lue par `build.gradle.kts` → `manifestPlaceholders`) :

```properties
yandex.mapkit.apiKey=VOTRE_CLE_MAPKIT
```

3. Lancer / builder avec le fichier de defines :

```bash
flutter run  --dart-define-from-file=env.json
flutter build apk --release --dart-define-from-file=env.json
```

- `AndroidManifest.xml` utilise le placeholder `${YANDEX_MAPKIT_API_KEY}`.
- `ios/Runner/Info.plist` utilise `$(YANDEX_MAPKIT_API_KEY)` (à définir via un
  `.xcconfig` ou les build settings Xcode pour les builds iOS).
- `main.dart` lit `EnvConfig.yandexMapKitApiKey` (issu de `--dart-define`).
- **CI** : le workflow `mobile-ci.yml` génère `env.json` + `local.properties`
  depuis les secrets GitHub `YANDEX_MAPKIT_API_KEY`,
  `YANDEX_DISTANCE_MATRIX_API_KEY`, `YANDEX_GEOLOCATION_API_KEY`.
- Le backend stocke les mêmes clés dans `backend-proartisan/.env`
  (`config/services.php` → `services.yandex.*`) ; la Distance Matrix y pilote le
  calcul du coût de livraison (`GoogleMapsService`).

#### Vérifier les permissions

**Android** (`AndroidManifest.xml`) :

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION"/>
```

**iOS** (`Info.plist`) :

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>Nous avons besoin de votre position pour trouver des artisans près de vous</string>
<key>NSLocationAlwaysUsageDescription</key>
<string>Nous avons besoin de votre position pour trouver des artisans près de vous</string>
```

### 2. Marqueurs ne s'affichent pas

**Causes :**

- Collections non initialisées
- Coordonnées GPS invalides
- Problème de rendu des icônes

**Solution :**
Vérifier que les collections sont créées après `onMapCreated` :

```dart
void _onMapCreated(mk.MapWindow mapWindow) {
  _mapWindow = mapWindow;
  _artisanCollection = mapWindow.map.mapObjects.addCollection();
  _userCollection = mapWindow.map.mapObjects.addCollection();
  
  // Puis ajouter les marqueurs
  _plotArtisans();
}
```

### 3. Erreur "MapKit not initialized"

**Cause :** MapKit n'est pas initialisé avant l'utilisation du widget YandexMap

**Solution :**
S'assurer que `initMapkit()` est appelé dans `main()` avant `runApp()` :

```dart
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  await mapkit_init.initMapkit(
    apiKey: EnvConfig.yandexMapKitApiKey, // --dart-define-from-file=env.json
  );
  
  runApp(const App());
}
```

### 4. Caméra ne se déplace pas

**Cause :** Position invalide ou MapWindow non initialisé

**Solution :**

```dart
void _moveCamera(double lat, double lng, double zoom) {
  final mw = _mapWindow;
  if (mw == null) return; // Vérifier que la carte est prête
  
  final position = mk.CameraPosition(
    mk.Point(latitude: lat, longitude: lng),
    zoom: zoom,
    azimuth: 0.0,
    tilt: 0.0,
  );
  
  mw.map.move(position);
}
```

### 5. Tap listeners ne fonctionnent pas

**Cause :** Les listeners sont garbage collectés (problème FFI)

**Solution :**
Conserver les listeners dans une liste de classe :

```dart
class _MyMapState extends State<MyMap> {
  final List<_TapListener> _tapListeners = [];
  
  void _addMarker() {
    final listener = _TapListener((obj, point) {
      // Gérer le tap
      return true;
    });
    _tapListeners.add(listener); // IMPORTANT : garder en mémoire
    placemark.addTapListener(listener);
  }
}
```

## Commandes de débogage

### Vérifier les logs Android

```bash
flutter run --verbose
# ou
adb logcat | grep -i yandex
```

### Nettoyer et rebuilder

```bash
flutter clean
flutter pub get
flutter run
```

### Vérifier la version du package

```yaml
# pubspec.yaml
dependencies:
  yandex_maps_mapkit: ^4.7.1
```

## Ressources

- [Documentation officielle Yandex MapKit](https://yandex.com/dev/maps/mapkit/)
- [Package Flutter](https://pub.dev/packages/yandex_maps_mapkit)
- [Exemples GitHub](https://github.com/Unact/yandex_maps_mapkit)

## Notes spécifiques ProsArtisan

- **Clé API** : ne jamais l'écrire en clair dans le dépôt. Elle est injectée au
  build via `--dart-define=YANDEX_MAPKIT_API_KEY=…` (voir `EnvConfig.yandexMapKitApiKey`)
  et stockée comme secret CI/CD. La clé de production doit être **restreinte**
  (package Android / bundle id iOS) et distincte de celle de développement.
  Toute clé ayant transité en clair dans l'historique Git doit être **révoquée
  puis remplacée** dans la console Yandex.
- **Serveur d'itinéraire livreur (OSRM)** : `--dart-define=OSRM_BASE_URL=…`
  (défaut = serveur de démo public, non destiné à la production).
- Position par défaut (Abidjan) : `5.3484, -4.0169`
- Zoom par défaut : `14.0`
- Rayon de recherche artisans : `2000m` (2 km)
