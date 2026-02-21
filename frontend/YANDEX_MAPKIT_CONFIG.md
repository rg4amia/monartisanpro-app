# Configuration Yandex MapKit - Migration vers 4.30.0-beta

## ✅ Migration effectuée

La migration vers `yandex_maps_mapkit: 4.30.0-beta` a été complétée avec succès !

### Changements principaux

#### 1. Package mis à jour
```yaml
# Avant
yandex_mapkit: ^4.1.0

# Après
yandex_maps_mapkit: 4.30.0-beta
```

#### 2. Imports modifiés
```dart
// Avant
import 'package:yandex_mapkit/yandex_mapkit.dart';

// Après
import 'package:yandex_maps_mapkit/mapkit.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;
```

#### 3. Initialisation dans main.dart
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialisation explicite avec la clé API
  await mapkit_init.initMapkit(apiKey: 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d');

  AppBindings().dependencies();
  runApp(const ProsArtisanApp());
}
```

#### 4. Nouvelle API de la carte

**Avant (yandex_mapkit):**
```dart
YandexMapController? _mapController;
final List<PlacemarkMapObject> _placemarks = [];

YandexMap(
  mapObjects: _placemarks,
  onMapCreated: (controller) async {
    _mapController = controller;
    await _mapController!.moveCamera(
      CameraUpdate.newCameraPosition(
        CameraPosition(
          target: Point(latitude: lat, longitude: lng),
          zoom: 15.0,
        ),
      ),
    );
  },
)
```

**Après (yandex_maps_mapkit):**
```dart
MapWindow? _mapWindow;
MapObjectCollection? _placemarkCollection;

FlutterMapWidget(
  onMapCreated: (mapWindow) {
    _mapWindow = mapWindow;
    _placemarkCollection = mapWindow.map.mapObjects.addCollection();
    
    mapWindow.map.move(
      CameraPosition(
        Point(latitude: lat, longitude: lng),
        zoom: 15.0,
        azimuth: 0.0,
        tilt: 0.0,
      ),
      animation: MapAnimation(
        type: MapAnimationType.Smooth,
        duration: 1.0,
      ),
    );
  },
  onMapDispose: () {
    _placemarkCollection = null;
    _mapWindow = null;
  },
)
```

#### 5. Gestion des Placemarks

**Avant:**
```dart
_placemarks.add(
  PlacemarkMapObject(
    mapId: MapObjectId('marker_1'),
    point: Point(latitude: lat, longitude: lng),
    onTap: (placemark, point) => handleTap(),
  ),
);
```

**Après:**
```dart
final placemark = _placemarkCollection!.addPlacemark()
  ..geometry = Point(latitude: lat, longitude: lng)
  ..opacity = 1.0;

final tapListener = MapObjectTapListenerImpl(
  onMapObjectTapped: (mapObject, point) {
    handleTap();
    return true;
  },
);

placemark.addTapListener(tapListener);
```

## 🛠️ Helper créé

Un helper `MapKitHelper` a été créé pour simplifier l'utilisation :

```dart
// Créer un point
final point = MapKitHelper.createPoint(latitude, longitude);

// Créer une position de caméra
final cameraPosition = MapKitHelper.createCameraPosition(
  latitude: latitude,
  longitude: longitude,
  zoom: 15.0,
);

// Créer une animation
final animation = MapKitHelper.createSmoothAnimation(duration: 1.0);
```

## 📱 Configuration Android/iOS

### AndroidManifest.xml
La clé API peut rester dans le manifest (optionnel car maintenant initialisée dans le code) :
```xml
<meta-data 
    android:name="com.yandex.mapkit.ApiKey" 
    android:value="e8411c6c-7c2d-414b-9cb0-029fc7d5a71d"/>
```

### Info.plist (iOS)
Permissions de localisation déjà configurées ✅

## 🚀 Prochaines étapes

Pour installer les dépendances :
```bash
cd frontend
flutter pub get
```

Pour nettoyer et reconstruire :
```bash
flutter clean
flutter pub get
flutter run
```

## 🎯 Fonctionnalités avancées disponibles

Avec `yandex_maps_mapkit: 4.30.0-beta`, vous avez maintenant accès à :

### 1. Search API
```dart
import 'package:yandex_maps_mapkit/search.dart';

final searchManager = SearchFactory.instance.createSearchManager(
  SearchManagerType.Combined
);
```

### 2. Routing API
```dart
import 'package:yandex_maps_mapkit/directions.dart';

final drivingRouter = DirectionsFactory.instance.createDrivingRouter();
```

### 3. Offline Maps
```dart
import 'package:yandex_maps_mapkit/mapkit.dart';

final offlineMapManager = MapKitFactory.instance.offlineCacheManager;
```

### 4. Custom Styles
```dart
mapWindow.map.setMapStyle(styleJson: customStyleJson);
```

## 📚 Ressources

- [Documentation officielle](https://yandex.ru/dev/mapkit/doc/en/flutter/generated/getting_started)
- [Exemples GitHub](https://github.com/yandex/mapkit-flutter-demo)
- [API Reference](https://yandex.ru/dev/mapkit/doc/en/flutter/generated/full)

## ⚠️ Notes importantes

1. La clé API est maintenant initialisée dans `main.dart`
2. L'API est différente - pas de compatibilité directe avec l'ancienne version
3. Les placemarks sont gérés via des collections au lieu de listes
4. Les listeners doivent être créés et attachés manuellement
5. `FlutterMapWidget` remplace `YandexMap`

## ✅ Fichiers modifiés

- ✅ `frontend/pubspec.yaml` - Package mis à jour
- ✅ `frontend/lib/main.dart` - Initialisation MapKit
- ✅ `frontend/lib/features/search/presentation/screens/map_search_screen.dart` - Nouvelle API
- ✅ `frontend/lib/core/utils/mapkit_helper.dart` - Helper créé
- ✅ `frontend/android/app/src/main/AndroidManifest.xml` - Déjà configuré
- ✅ `frontend/ios/Runner/Info.plist` - Déjà configuré

