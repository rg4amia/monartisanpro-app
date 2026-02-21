# Guide de Migration - Yandex MapKit 4.1.0 → 4.30.0-beta

## ✅ Migration Complétée

La migration de `yandex_mapkit: ^4.1.0` vers `yandex_maps_mapkit: 4.30.0-beta` a été effectuée avec succès !

## 📦 Installation

```bash
cd frontend
flutter pub get
```

## 🔄 Changements Principaux

### 1. Imports

**Avant:**
```dart
import 'package:yandex_mapkit/yandex_mapkit.dart';
```

**Après:**
```dart
import 'package:yandex_maps_mapkit/mapkit.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;
```

### 2. Initialisation (main.dart)

**Avant:**
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  AndroidYandexMap.useAndroidViewSurface = true;
  runApp(const MyApp());
}
```

**Après:**
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await mapkit_init.initMapkit(apiKey: 'VOTRE_CLE_API');
  runApp(const MyApp());
}
```

### 3. Widget de Carte

**Avant:**
```dart
YandexMap(
  mapObjects: _placemarks,
  onMapCreated: (controller) async {
    _mapController = controller;
  },
)
```

**Après:**
```dart
FlutterMapWidget(
  onMapCreated: (mapWindow) {
    _mapWindow = mapWindow;
    _placemarkCollection = mapWindow.map.mapObjects.addCollection();
  },
  onMapDispose: () {
    _placemarkCollection = null;
  },
)
```

### 4. Déplacement de Caméra

**Avant:**
```dart
await _mapController!.moveCamera(
  CameraUpdate.newCameraPosition(
    CameraPosition(
      target: Point(latitude: lat, longitude: lng),
      zoom: 15.0,
    ),
  ),
);
```

**Après:**
```dart
_mapWindow!.map.move(
  MapKitHelper.createCameraPosition(
    latitude: lat,
    longitude: lng,
    zoom: 15.0,
  ),
  animation: MapKitHelper.createSmoothAnimation(),
);
```

### 5. Gestion des Markers (Placemarks)

**Avant:**
```dart
final List<PlacemarkMapObject> _placemarks = [];

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
MapObjectCollection? _placemarkCollection;
final _placemarkTapListeners = <MapObjectTapListener>[];

// Ajouter un marker
final placemark = _placemarkCollection!.addPlacemark()
  ..geometry = MapKitHelper.createPoint(lat, lng)
  ..opacity = 1.0;

// Ajouter un listener de tap
final tapListener = MapObjectTapListenerImpl(
  onMapObjectTapped: (mapObject, point) {
    handleTap();
    return true;
  },
);
placemark.addTapListener(tapListener);
_placemarkTapListeners.add(tapListener);

// Nettoyer
_placemarkCollection!.clear();
_placemarkTapListeners.clear();
```

## 🛠️ Helper Créé

Un helper `MapKitHelper` a été créé pour simplifier l'utilisation :

```dart
import 'package:frontend/core/utils/mapkit_helper.dart';

// Créer un point
final point = MapKitHelper.createPoint(latitude, longitude);

// Créer une position de caméra
final cameraPosition = MapKitHelper.createCameraPosition(
  latitude: latitude,
  longitude: longitude,
  zoom: 15.0,
);

// Créer une animation smooth
final animation = MapKitHelper.createSmoothAnimation(duration: 1.0);

// Créer une animation linéaire
final animation = MapKitHelper.createLinearAnimation(duration: 0.5);
```

## 📝 Checklist de Migration

- [x] Mise à jour du package dans `pubspec.yaml`
- [x] Modification de `main.dart` pour l'initialisation
- [x] Mise à jour de `map_search_screen.dart`
- [x] Création du helper `MapKitHelper`
- [x] Exécution de `flutter pub get`
- [x] Vérification des diagnostics (aucune erreur)

## 🚀 Prochaines Étapes

### 1. Tester l'application

```bash
flutter run
```

### 2. Nettoyer si nécessaire

```bash
flutter clean
flutter pub get
flutter run
```

### 3. Fonctionnalités avancées disponibles

Avec la nouvelle version, vous pouvez maintenant utiliser :

#### Search API
```dart
import 'package:yandex_maps_mapkit/search.dart';

final searchManager = SearchFactory.instance.createSearchManager(
  SearchManagerType.Combined
);
```

#### Routing API
```dart
import 'package:yandex_maps_mapkit/directions.dart';

final drivingRouter = DirectionsFactory.instance.createDrivingRouter();
```

#### Offline Maps
```dart
final offlineMapManager = MapKitFactory.instance.offlineCacheManager;
```

## ⚠️ Points d'Attention

1. **API non compatible** : L'ancienne API n'est pas compatible avec la nouvelle
2. **Collections de markers** : Les markers sont maintenant gérés via des collections
3. **Listeners manuels** : Les tap listeners doivent être créés et attachés manuellement
4. **Nettoyage** : Pensez à nettoyer les listeners et collections dans `dispose()`

## 📚 Ressources

- [Documentation officielle](https://yandex.ru/dev/mapkit/doc/en/flutter/generated/getting_started)
- [Exemples GitHub](https://github.com/yandex/mapkit-flutter-demo)
- [API Reference](https://yandex.ru/dev/mapkit/doc/en/flutter/generated/full)

## 🐛 Dépannage

### Erreur : "Target of URI doesn't exist"
```bash
flutter clean
flutter pub get
```

### Erreur de build Android
```bash
cd android
./gradlew clean
cd ..
flutter run
```

### Erreur de build iOS
```bash
cd ios
pod deintegrate
pod install
cd ..
flutter run
```

## ✅ Fichiers Modifiés

1. `frontend/pubspec.yaml` - Package mis à jour
2. `frontend/lib/main.dart` - Initialisation MapKit
3. `frontend/lib/features/search/presentation/screens/map_search_screen.dart` - Nouvelle API
4. `frontend/lib/core/utils/mapkit_helper.dart` - Helper créé (nouveau fichier)
5. `frontend/YANDEX_MAPKIT_CONFIG.md` - Documentation mise à jour
6. `frontend/MIGRATION_GUIDE.md` - Ce guide (nouveau fichier)

## 🎉 Résultat

Votre application utilise maintenant la dernière version beta de Yandex MapKit avec :
- ✅ API moderne et performante
- ✅ Accès aux fonctionnalités avancées (search, routing, offline)
- ✅ Code plus maintenable avec le helper
- ✅ Aucune erreur de compilation
