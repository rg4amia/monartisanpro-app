# Migration de Google Maps vers Yandex MapKit

## Vue d'ensemble

Ce guide détaille la migration complète de Google Maps vers Yandex MapKit dans l'application ProsArtisan.

**Clé API Yandex**: `e8411c6c-7c2d-414b-9cb0-029fc7d5a71d`

---

## Pourquoi Yandex MapKit?

### Avantages

✅ **Gratuit**: Pas de frais pour un usage standard  
✅ **Pas de carte bancaire requise**: Configuration simple  
✅ **Bonne couverture**: Excellente pour l'Afrique et l'Europe  
✅ **Performant**: Rendu fluide et rapide  
✅ **Hors ligne**: Support du mode offline  
✅ **Personnalisable**: Styles de carte personnalisés  

### Comparaison

| Fonctionnalité | Google Maps | Yandex MapKit |
|----------------|-------------|---------------|
| Prix | Payant après quota | Gratuit |
| Configuration | Complexe | Simple |
| Carte bancaire | Requise | Non requise |
| Couverture Afrique | Bonne | Bonne |
| Mode hors ligne | Limité | Complet |

---

## Étape 1: Mise à Jour des Dépendances

### Frontend - pubspec.yaml

**Avant**:
```yaml
dependencies:
  google_maps_flutter: ^2.6.0
```

**Après**:
```yaml
dependencies:
  yandex_mapkit: ^4.1.0
```

### Installation

```bash
cd frontend
flutter pub remove google_maps_flutter
flutter pub add yandex_mapkit
flutter pub get
```

---

## Étape 2: Configuration Android

### android/app/build.gradle

Ajouter dans `android` block:

```gradle
android {
    // ... existing config ...
    
    defaultConfig {
        // ... existing config ...
        minSdkVersion 21  // Yandex MapKit requires min 21
    }
}
```

### android/app/src/main/AndroidManifest.xml

Ajouter la clé API:

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    <application
        android:label="ProsArtisan"
        android:name="${applicationName}"
        android:icon="@mipmap/ic_launcher">
        
        <!-- Yandex MapKit API Key -->
        <meta-data
            android:name="com.yandex.mapkit.ApiKey"
            android:value="e8411c6c-7c2d-414b-9cb0-029fc7d5a71d"/>
        
        <!-- ... rest of config ... -->
    </application>
</manifest>
```

---

## Étape 3: Configuration iOS

### ios/Podfile

Ajouter en haut du fichier:

```ruby
platform :ios, '13.0'  # Yandex MapKit requires iOS 13+
```

### ios/Runner/AppDelegate.swift

Importer et initialiser Yandex MapKit:

```swift
import UIKit
import Flutter
import YandexMapsMobile

@UIApplicationMain
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    // Initialize Yandex MapKit
    YMKMapKit.setApiKey("e8411c6c-7c2d-414b-9cb0-029fc7d5a71d")
    
    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
```

### ios/Runner/Info.plist

Ajouter les permissions de localisation:

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>Nous avons besoin de votre localisation pour trouver des artisans près de vous</string>
<key>NSLocationAlwaysUsageDescription</key>
<string>Nous avons besoin de votre localisation pour trouver des artisans près de vous</string>
```

---

## Étape 4: Initialisation dans main.dart

### frontend/lib/main.dart

```dart
import 'package:yandex_mapkit/yandex_mapkit.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize Yandex MapKit
  AndroidYandexMap.useAndroidViewSurface = false;
  
  // Initialize bindings
  AppBindings().dependencies();

  runApp(const ProsArtisanApp());
}
```

---

## Étape 5: Nouveau MapSearchScreen

### Fichier créé

`frontend/lib/features/search/presentation/screens/map_search_screen_yandex.dart`

### Différences Clés

#### Google Maps → Yandex MapKit

| Google Maps | Yandex MapKit |
|-------------|---------------|
| `GoogleMap` | `YandexMap` |
| `GoogleMapController` | `YandexMapController` |
| `Marker` | `PlacemarkMapObject` |
| `LatLng` | `Point` |
| `CameraPosition` | `CameraPosition` |
| `BitmapDescriptor` | `PlacemarkIcon` |

#### Exemple de Conversion

**Google Maps**:
```dart
GoogleMap(
  initialCameraPosition: CameraPosition(
    target: LatLng(latitude, longitude),
    zoom: 14.0,
  ),
  markers: _markers,
  onMapCreated: (controller) {
    _mapController = controller;
  },
)
```

**Yandex MapKit**:
```dart
YandexMap(
  mapObjects: _placemarks,
  onMapCreated: (controller) {
    _mapController = controller;
    _mapController!.moveCamera(
      CameraUpdate.newCameraPosition(
        CameraPosition(
          target: Point(
            latitude: latitude,
            longitude: longitude,
          ),
          zoom: 14.0,
        ),
      ),
    );
  },
)
```

---

## Étape 6: Création des Markers

### Google Maps (Ancien)

```dart
Marker(
  markerId: MarkerId('id'),
  position: LatLng(lat, lng),
  icon: BitmapDescriptor.defaultMarkerWithHue(
    BitmapDescriptor.hueYellow,
  ),
  onTap: () => _onMarkerTap(),
)
```

### Yandex MapKit (Nouveau)

```dart
PlacemarkMapObject(
  mapId: MapObjectId('id'),
  point: Point(latitude: lat, longitude: lng),
  icon: PlacemarkIcon.single(
    PlacemarkIconStyle(
      image: BitmapDescriptor.fromAssetImage(
        'assets/markers/marker.png',
      ),
      scale: 0.5,
    ),
  ),
  onTap: (placemark, point) => _onMarkerTap(),
)
```

---

## Étape 7: Création des Assets de Markers

### Créer les dossiers

```bash
mkdir -p frontend/assets/markers
```

### Markers nécessaires

1. **marker_default.png** - Marker bleu standard
2. **marker_nearby.png** - Marker jaune pour artisans proches

### Tailles recommandées

- **1x**: 48x48 px
- **2x**: 96x96 px  
- **3x**: 144x144 px

### Ajouter dans pubspec.yaml

```yaml
flutter:
  assets:
    - assets/markers/
```

---

## Étape 8: Remplacement du Fichier

### Option 1: Renommer (Recommandé)

```bash
cd frontend/lib/features/search/presentation/screens

# Sauvegarder l'ancien
mv map_search_screen.dart map_search_screen_google.dart.bak

# Utiliser le nouveau
mv map_search_screen_yandex.dart map_search_screen.dart
```

### Option 2: Remplacer le contenu

Copier le contenu de `map_search_screen_yandex.dart` dans `map_search_screen.dart`

---

## Étape 9: Tests

### Test 1: Affichage de la Carte

1. Lancer l'application
2. Naviguer vers la recherche sur carte
3. **Attendu**: Carte Yandex s'affiche

### Test 2: Position Utilisateur

1. Autoriser la localisation
2. Vérifier le centrage sur la position
3. **Attendu**: Carte centrée sur l'utilisateur

### Test 3: Markers

1. Rechercher des artisans
2. Vérifier l'affichage des markers
3. **Attendu**: Markers bleus et jaunes visibles

### Test 4: Interaction

1. Cliquer sur un marker
2. Vérifier le bottom sheet
3. **Attendu**: Informations de l'artisan affichées

### Test 5: Navigation

1. Cliquer sur "Ma position"
2. Vérifier l'animation
3. **Attendu**: Carte recentrée avec animation

---

## Étape 10: Nettoyage

### Supprimer les anciennes dépendances

```bash
cd frontend
flutter clean
flutter pub get
```

### Supprimer les fichiers Google Maps

```bash
# Sauvegarder si besoin
rm frontend/lib/features/search/presentation/screens/map_search_screen_google.dart.bak
```

---

## Fonctionnalités Avancées

### 1. Styles de Carte Personnalisés

```dart
YandexMap(
  mapType: MapType.vector,  // ou MapType.hybrid
  nightModeEnabled: true,   // Mode nuit
  // ...
)
```

### 2. Clustering de Markers

```dart
ClusterizedPlacemarkCollection(
  mapId: MapObjectId('clusters'),
  placemarks: _placemarks,
  radius: 60,
  minZoom: 10,
  onClusterTap: (cluster) {
    // Handle cluster tap
  },
)
```

### 3. Itinéraires

```dart
// Demander un itinéraire
final drivingRouter = YandexDriving();
final result = await drivingRouter.requestRoutes(
  points: [
    RequestPoint(
      point: Point(latitude: startLat, longitude: startLng),
      requestPointType: RequestPointType.wayPoint,
    ),
    RequestPoint(
      point: Point(latitude: endLat, longitude: endLng),
      requestPointType: RequestPointType.wayPoint,
    ),
  ],
  drivingOptions: const DrivingOptions(
    initialAzimuth: 0,
    routesCount: 1,
  ),
);
```

### 4. Géocodage

```dart
// Adresse → Coordonnées
final searchManager = YandexSearch();
final result = await searchManager.searchByText(
  searchText: 'Cocody, Abidjan',
  geometry: Geometry.fromBoundingBox(
    BoundingBox(
      southWest: Point(latitude: minLat, longitude: minLng),
      northEast: Point(latitude: maxLat, longitude: maxLng),
    ),
  ),
  searchOptions: const SearchOptions(
    searchType: SearchType.geo,
    resultPageSize: 10,
  ),
);
```

---

## Dépannage

### Problème 1: Carte ne s'affiche pas

**Cause**: Clé API non configurée

**Solution**:
```bash
# Vérifier AndroidManifest.xml
grep "com.yandex.mapkit.ApiKey" android/app/src/main/AndroidManifest.xml

# Vérifier AppDelegate.swift
grep "setApiKey" ios/Runner/AppDelegate.swift
```

### Problème 2: Erreur de compilation Android

**Cause**: minSdkVersion trop bas

**Solution**:
```gradle
// android/app/build.gradle
defaultConfig {
    minSdkVersion 21  // Au minimum 21
}
```

### Problème 3: Markers ne s'affichent pas

**Cause**: Assets non déclarés

**Solution**:
```yaml
# pubspec.yaml
flutter:
  assets:
    - assets/markers/
```

Puis:
```bash
flutter clean
flutter pub get
```

### Problème 4: Crash sur iOS

**Cause**: Clé API non initialisée

**Solution**:
```swift
// ios/Runner/AppDelegate.swift
YMKMapKit.setApiKey("e8411c6c-7c2d-414b-9cb0-029fc7d5a71d")
```

---

## Performance

### Optimisations

1. **Lazy Loading**: Charger les markers par région
2. **Clustering**: Grouper les markers proches
3. **Cache**: Mettre en cache les tiles de carte
4. **Debouncing**: Limiter les mises à jour pendant le déplacement

### Exemple de Clustering

```dart
final List<ClusterizedPlacemarkCollection> _clusters = [
  ClusterizedPlacemarkCollection(
    mapId: MapObjectId('artisans_cluster'),
    placemarks: _placemarks,
    radius: 60,
    minZoom: 10,
    onClusterAdded: (self, cluster) {
      return cluster.copyWith(
        appearance: cluster.appearance.copyWith(
          opacity: 1.0,
          icon: PlacemarkIcon.single(
            PlacemarkIconStyle(
              image: BitmapDescriptor.fromAssetImage(
                'assets/markers/cluster.png',
              ),
              scale: 0.5,
            ),
          ),
        ),
      );
    },
    onClusterTap: (self, cluster) {
      // Zoom sur le cluster
    },
  ),
];
```

---

## Checklist de Migration

- [ ] Mise à jour de `pubspec.yaml`
- [ ] Configuration Android (`AndroidManifest.xml`, `build.gradle`)
- [ ] Configuration iOS (`AppDelegate.swift`, `Info.plist`, `Podfile`)
- [ ] Initialisation dans `main.dart`
- [ ] Création des assets de markers
- [ ] Remplacement de `MapSearchScreen`
- [ ] Tests sur Android
- [ ] Tests sur iOS
- [ ] Vérification des performances
- [ ] Documentation mise à jour

---

## Ressources

### Documentation Officielle

- [Yandex MapKit Flutter](https://pub.dev/packages/yandex_mapkit)
- [Yandex MapKit Docs](https://yandex.com/dev/maps/mapkit/)
- [GitHub Demo](https://github.com/yandex/mapkit-flutter-demo)

### Exemples de Code

- [Navigation Demo](https://github.com/yandex/mapkit-flutter-demo/tree/master/navikit-demo)
- [Map Objects](https://github.com/yandex/mapkit-flutter-demo/tree/master/mapkit-demo)

### Support

- [Stack Overflow](https://stackoverflow.com/questions/tagged/yandex-mapkit)
- [GitHub Issues](https://github.com/Unact/yandex_mapkit/issues)

---

## Commandes Utiles

### Installation Complète

```bash
# Frontend
cd frontend
flutter pub remove google_maps_flutter
flutter pub add yandex_mapkit
flutter clean
flutter pub get

# iOS (si nécessaire)
cd ios
pod install
cd ..

# Rebuild
flutter run
```

### Vérification

```bash
# Vérifier les dépendances
flutter pub deps | grep mapkit

# Vérifier la configuration
flutter doctor -v
```

---

**Date**: 18 février 2026  
**Version**: 1.0  
**Statut**: ✅ Prêt pour Migration  
**Clé API**: `e8411c6c-7c2d-414b-9cb0-029fc7d5a71d`
