# Feature: Date de Réalisation et Intégration Yandex Maps

## Vue d'ensemble

Ajout de la date de réalisation souhaitée pour les projets et préparation de l'intégration Yandex Maps pour la sélection d'adresse.

## Changements Backend

### 1. Migration de Base de Données

**Fichier**: `backend/database/migrations/2026_02_21_142448_add_expected_completion_date_to_projects_table.php`

```php
Schema::table('projects', function (Blueprint $table) {
    $table->date('expected_completion_date')->nullable()->after('address');
});
```

Ajoute le champ `expected_completion_date` (date) à la table `projects`.

### 2. Modèle Project

**Fichier**: `backend/app/Models/Project.php`

**Ajouts**:
- Champ `expected_completion_date` dans `$fillable`
- Cast `'expected_completion_date' => 'date'` dans `$casts`

### 3. ProjectController

**Fichier**: `backend/app/Http/Controllers/Api/V1/ProjectController.php`

**Méthode `store()`**:
```php
$validated = $request->validate([
    // ... autres champs
    'expected_completion_date' => 'nullable|date|after:today',
]);

$project = Project::create([
    // ... autres champs
    'expected_completion_date' => $validated['expected_completion_date'] ?? null,
]);
```

**Méthode `update()`**:
```php
$validated = $request->validate([
    // ... autres champs
    'expected_completion_date' => 'sometimes|nullable|date|after:today',
]);
```

## Changements Frontend

### 1. Modèle Project

**Fichier**: `frontend/lib/shared/models/project_model.dart`

**Classe `Project`**:
```dart
@JsonKey(name: 'expected_completion_date')
final DateTime? expectedCompletionDate;
```

**Classe `CreateProjectRequest`**:
```dart
@JsonKey(name: 'expected_completion_date')
final String? expectedCompletionDate;
```

### 2. Écran de Création de Projet

**Fichier**: `frontend/lib/features/projects/presentation/screens/create_project_screen.dart`

**Ajouts**:

1. **Variable d'état**:
```dart
DateTime? _selectedCompletionDate;
```

2. **Sélecteur de date**:
```dart
InkWell(
  onTap: () async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedCompletionDate ??
          DateTime.now().add(const Duration(days: 7)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      helpText: 'Date de réalisation souhaitée',
    );
    if (picked != null) {
      setState(() {
        _selectedCompletionDate = picked;
      });
    }
  },
  child: InputDecorator(
    decoration: InputDecoration(
      labelText: 'Date de réalisation souhaitée (optionnel)',
      prefixIcon: Icon(Icons.calendar_today_outlined),
      suffixIcon: _selectedCompletionDate != null
          ? IconButton(
              icon: Icon(Icons.clear),
              onPressed: () {
                setState(() {
                  _selectedCompletionDate = null;
                });
              },
            )
          : null,
    ),
    child: Text(
      _selectedCompletionDate != null
          ? 'DD/MM/YYYY'
          : 'Aucune date sélectionnée',
    ),
  ),
)
```

3. **Bouton carte Yandex Maps**:
```dart
suffixIcon: Row(
  mainAxisSize: MainAxisSize.min,
  children: [
    IconButton(
      icon: Icon(Icons.map_outlined),
      onPressed: () {
        // TODO: Ouvrir Yandex Maps
      },
      tooltip: 'Sélectionner sur la carte',
    ),
    IconButton(
      icon: Icon(Icons.my_location),
      onPressed: _getCurrentLocation,
      tooltip: 'Utiliser ma position',
    ),
  ],
)
```

4. **Envoi de la date au backend**:
```dart
final request = CreateProjectRequest(
  // ... autres champs
  expectedCompletionDate: _selectedCompletionDate != null
      ? '${_selectedCompletionDate!.year}-${_selectedCompletionDate!.month.toString().padLeft(2, '0')}-${_selectedCompletionDate!.day.toString().padLeft(2, '0')}'
      : null,
);
```

## Fonctionnalités

### Date de Réalisation

**Caractéristiques**:
- ✅ Champ optionnel
- ✅ Sélection via DatePicker natif
- ✅ Date minimale: aujourd'hui
- ✅ Date maximale: 1 an dans le futur
- ✅ Format d'affichage: DD/MM/YYYY
- ✅ Format d'envoi: YYYY-MM-DD (ISO 8601)
- ✅ Bouton pour effacer la date sélectionnée
- ✅ Validation backend: date doit être après aujourd'hui

**Interface Utilisateur**:
- Icône calendrier
- Texte indicatif quand aucune date n'est sélectionnée
- Affichage de la date formatée quand sélectionnée
- Bouton X pour effacer

### Yandex Maps (Préparation)

**Statut**: Interface préparée, implémentation à venir

**Bouton ajouté**:
- Icône carte (map_outlined)
- Tooltip: "Sélectionner sur la carte"
- Position: à côté du bouton "Ma position"

**À implémenter**:
1. Créer un écran de sélection avec Yandex Maps
2. Permettre de déplacer un marqueur sur la carte
3. Récupérer les coordonnées et l'adresse
4. Retourner les données à l'écran de création

## Dépendances

### Déjà installées:
- ✅ `yandex_mapkit: ^4.1.0` - Pour Yandex Maps
- ✅ `geolocator: ^11.0.0` - Pour la géolocalisation
- ✅ `geocoding: ^2.1.1` - Pour le géocodage

## API

### Endpoint: POST /api/v1/projects

**Request Body**:
```json
{
  "trade_id": 1,
  "title": "Rénovation salle de bain",
  "description": "Description détaillée...",
  "latitude": 5.3599517,
  "longitude": -4.0082563,
  "address": "Cocody, Angré",
  "expected_completion_date": "2026-03-15"
}
```

**Response**:
```json
{
  "id": 1,
  "client_id": 1,
  "trade_id": 1,
  "title": "Rénovation salle de bain",
  "description": "Description détaillée...",
  "location": {
    "latitude": 5.3599517,
    "longitude": -4.0082563
  },
  "address": "Cocody, Angré",
  "expected_completion_date": "2026-03-15",
  "status": "pending",
  "created_at": "2026-02-21T14:30:00.000000Z",
  "updated_at": "2026-02-21T14:30:00.000000Z"
}
```

## Validation

### Backend
- `expected_completion_date`: nullable, date, after:today

### Frontend
- Date minimale: aujourd'hui
- Date maximale: 1 an dans le futur
- Format: YYYY-MM-DD pour l'API

## Tests

### À tester:

**Date de réalisation**:
- [ ] Ouvrir le sélecteur de date
- [ ] Sélectionner une date future
- [ ] Vérifier l'affichage formaté
- [ ] Effacer la date sélectionnée
- [ ] Créer un projet avec date
- [ ] Créer un projet sans date
- [ ] Vérifier la validation backend

**Bouton carte**:
- [ ] Vérifier que le bouton s'affiche
- [ ] Cliquer sur le bouton
- [ ] Vérifier le message "à venir"

## Prochaines Étapes

### 1. Implémenter Yandex Maps (Priorité Haute)

**Créer**: `frontend/lib/features/projects/presentation/screens/map_location_picker_screen.dart`

```dart
class MapLocationPickerScreen extends StatefulWidget {
  final Position? initialPosition;
  final String? initialAddress;
  
  const MapLocationPickerScreen({
    super.key,
    this.initialPosition,
    this.initialAddress,
  });
  
  @override
  State<MapLocationPickerScreen> createState() => _MapLocationPickerScreenState();
}

class _MapLocationPickerScreenState extends State<MapLocationPickerScreen> {
  late YandexMapController _mapController;
  Point? _selectedPoint;
  String? _selectedAddress;
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Sélectionner l\'adresse'),
        actions: [
          TextButton(
            onPressed: () {
              if (_selectedPoint != null) {
                Get.back(result: {
                  'latitude': _selectedPoint!.latitude,
                  'longitude': _selectedPoint!.longitude,
                  'address': _selectedAddress,
                });
              }
            },
            child: const Text('Confirmer'),
          ),
        ],
      ),
      body: Stack(
        children: [
          YandexMap(
            onMapCreated: (controller) {
              _mapController = controller;
            },
            onMapTap: (point) async {
              setState(() {
                _selectedPoint = point;
              });
              // Géocodage inverse pour obtenir l'adresse
              _selectedAddress = await _getAddressFromCoordinates(point);
            },
          ),
          // Marqueur au centre
          Center(
            child: Icon(
              Icons.location_pin,
              size: 48,
              color: Colors.red,
            ),
          ),
          // Carte d'information en bas
          if (_selectedAddress != null)
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text(_selectedAddress!),
                ),
              ),
            ),
        ],
      ),
    );
  }
  
  Future<String?> _getAddressFromCoordinates(Point point) async {
    // Implémenter le géocodage inverse
    try {
      final placemarks = await placemarkFromCoordinates(
        point.latitude,
        point.longitude,
      );
      if (placemarks.isNotEmpty) {
        final place = placemarks.first;
        return '${place.street}, ${place.locality}';
      }
    } catch (e) {
      print('Erreur géocodage: $e');
    }
    return null;
  }
}
```

**Intégrer dans create_project_screen.dart**:
```dart
IconButton(
  icon: const Icon(Icons.map_outlined),
  onPressed: () async {
    final result = await Get.to(() => MapLocationPickerScreen(
      initialPosition: _selectedLocation,
      initialAddress: _addressController.text,
    ));
    
    if (result != null) {
      setState(() {
        _selectedLocation = Position(
          latitude: result['latitude'],
          longitude: result['longitude'],
          timestamp: DateTime.now(),
          accuracy: 0,
          altitude: 0,
          heading: 0,
          speed: 0,
          speedAccuracy: 0,
          altitudeAccuracy: 0,
          headingAccuracy: 0,
        );
        _addressController.text = result['address'] ?? '';
      });
    }
  },
  tooltip: 'Sélectionner sur la carte',
),
```

### 2. Configuration Yandex Maps

**Android** (`android/app/src/main/AndroidManifest.xml`):
```xml
<application>
    <meta-data
        android:name="com.yandex.mapkit.ApiKey"
        android:value="YOUR_YANDEX_API_KEY"/>
</application>
```

**iOS** (`ios/Runner/AppDelegate.swift`):
```swift
import YandexMapsMobile

@UIApplicationMain
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    YMKMapKit.setApiKey("YOUR_YANDEX_API_KEY")
    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
```

### 3. Obtenir une clé API Yandex Maps

1. Aller sur https://developer.tech.yandex.ru/
2. Créer un compte
3. Créer une nouvelle clé API pour MapKit
4. Ajouter la clé dans les configurations Android et iOS

## Migration Backend

Pour appliquer les changements en base de données:

```bash
cd backend
php artisan migrate
```

## Régénération des Modèles Flutter

Pour régénérer les fichiers de sérialisation JSON:

```bash
cd frontend
flutter pub run build_runner build --delete-conflicting-outputs
```

## Bénéfices

### Pour les Utilisateurs
- ✅ Meilleure planification des projets
- ✅ Communication claire des délais
- ✅ Sélection précise de l'adresse sur carte
- ✅ Expérience utilisateur améliorée

### Pour les Artisans
- ✅ Connaissance des délais attendus
- ✅ Meilleure organisation du travail
- ✅ Localisation précise des chantiers

### Pour le Système
- ✅ Données structurées pour analytics
- ✅ Filtrage par date de réalisation
- ✅ Notifications basées sur les dates
- ✅ Géolocalisation précise

## Statut

- ✅ Backend: Modèle et contrôleur mis à jour
- ✅ Frontend: Modèle mis à jour
- ✅ Frontend: Interface date de réalisation implémentée
- ⏳ Frontend: Intégration Yandex Maps à implémenter
- ⏳ Backend: Migration à exécuter (DB non accessible)
- ⏳ Configuration: Clé API Yandex Maps à ajouter

## Documentation

- [Yandex MapKit Flutter](https://pub.dev/packages/yandex_mapkit)
- [Yandex Maps API](https://yandex.com/dev/maps/)
- [Flutter DatePicker](https://api.flutter.dev/flutter/material/showDatePicker.html)
