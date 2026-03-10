# Yandex Maps - Guide de dépannage

## Problèmes d'affichage de la carte

### 1. Carte blanche ou ne se charge pas

**Causes possibles :**
- Clé API invalide ou manquante
- Permissions de localisation non accordées
- Problème de connexion internet
- Configuration Android/iOS incorrecte

**Solutions :**

#### Vérifier la clé API
La clé API doit être configurée dans 3 endroits :

1. **AndroidManifest.xml** (`android/app/src/main/AndroidManifest.xml`) :
```xml
<meta-data 
    android:name="com.yandex.maps.api_key" 
    android:value="VOTRE_CLE_API"/>
```

2. **main.dart** :
```dart
await mapkit_init.initMapkit(
  apiKey: 'VOTRE_CLE_API',
);
```

3. **Info.plist** (iOS) :
```xml
<key>YMKMapKitApiKey</key>
<string>VOTRE_CLE_API</string>
```

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
    apiKey: 'VOTRE_CLE_API',
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

- Clé API actuelle : `e8411c6c-7c2d-414b-9cb0-029fc7d5a71d`
- Position par défaut (Abidjan) : `5.3484, -4.0169`
- Zoom par défaut : `14.0`
- Rayon de recherche artisans : `2000m` (2 km)
