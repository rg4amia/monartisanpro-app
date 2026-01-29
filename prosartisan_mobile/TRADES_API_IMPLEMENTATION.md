# Implémentation de l'API des Métiers - ProSartisan Mobile

## Vue d'ensemble

Cette implémentation permet à l'application mobile Flutter de récupérer les secteurs d'activité et métiers depuis l'API backend Laravel.

## Architecture

### Backend (Laravel)
- **Endpoint**: `GET /api/v1/reference/trades`
- **Contrôleur**: `ReferenceDataController@index`
- **Modèles**: `Sector` et `Trade`
- **Relation**: Un secteur a plusieurs métiers (`hasMany`)

### Mobile (Flutter)
- **Modèles**: `Sector` et `Trade` avec sérialisation JSON
- **Repository**: `ReferenceDataRepository` pour les appels API
- **Contrôleur**: `ReferenceDataController` avec GetX pour la gestion d'état
- **Widget**: `TradeSelectorWidget` pour l'interface utilisateur

## Structure des fichiers

```
lib/
├── shared/
│   ├── models/
│   │   ├── sector.dart
│   │   ├── sector.g.dart
│   │   ├── trade.dart
│   │   └── trade.g.dart
│   ├── data/repositories/
│   │   └── reference_data_repository.dart
│   ├── controllers/
│   │   └── reference_data_controller.dart
│   ├── widgets/
│   │   └── trade_selector_widget.dart
│   └── bindings/
│       └── reference_data_binding.dart
├── features/demo/presentation/pages/
│   └── trade_selection_demo_page.dart
└── core/constants/
    └── api_constants.dart (mis à jour)
```

## Fonctionnalités

### 1. Récupération des données
- Charge tous les secteurs avec leurs métiers associés
- Gestion des erreurs réseau et API
- États de chargement avec indicateurs visuels

### 2. Filtrage et recherche
- Filtrage par secteur d'activité
- Recherche textuelle par nom ou code de métier
- Mise à jour en temps réel des résultats

### 3. Interface utilisateur
- Sélecteur de secteur (dropdown)
- Barre de recherche avec effacement
- Liste des métiers avec informations détaillées
- Indication visuelle du métier sélectionné

### 4. Gestion d'état
- Utilisation de GetX pour la réactivité
- États observables pour les listes et le chargement
- Gestion des erreurs avec messages utilisateur

## Utilisation

### 1. Configuration
```bash
cd prosartisan_mobile
./setup_trades_api.sh
```

### 2. Intégration dans une page
```dart
import 'package:get/get.dart';
import '../shared/widgets/trade_selector_widget.dart';
import '../shared/controllers/reference_data_controller.dart';
import '../shared/bindings/reference_data_binding.dart';

class MyPage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GetBuilder<ReferenceDataController>(
      init: Get.put(ReferenceDataController()),
      builder: (controller) => TradeSelectorWidget(
        onTradeSelected: (trade) {
          print('Métier sélectionné: ${trade.name}');
        },
      ),
    );
  }
}
```

### 3. Utilisation du contrôleur
```dart
final controller = Get.find<ReferenceDataController>();

// Récupérer tous les métiers
await controller.loadAllTrades();

// Filtrer par secteur
controller.filterTradesBySector(sectorId);

// Rechercher
controller.searchTrades('plomberie');

// Accéder aux données
List<Trade> trades = controller.filteredTrades;
List<Sector> sectors = controller.sectors;
```

## API Response Format

### Secteurs avec métiers
```json
{
  "data": [
    {
      "id": 1,
      "code": "BAT",
      "name": "Bâtiment",
      "created_at": "2026-01-29T10:00:00Z",
      "updated_at": "2026-01-29T10:00:00Z",
      "trades": [
        {
          "id": 1,
          "code": "PLOMB",
          "name": "Plomberie",
          "sector_id": 1,
          "created_at": "2026-01-29T10:00:00Z",
          "updated_at": "2026-01-29T10:00:00Z"
        }
      ]
    }
  ]
}
```

## Tests

### Page de démonstration
- Accessible via `Design System Demo > Tester la récupération des métiers`
- Route: `/trade-selection-demo`
- Teste toutes les fonctionnalités de sélection

### Vérifications
1. **Backend actif**: Vérifier que l'API Laravel répond sur `/api/v1/reference/trades`
2. **Données seeded**: S'assurer que les secteurs et métiers sont en base
3. **Connectivité**: Vérifier la configuration réseau de l'émulateur/appareil

## Dépendances ajoutées

```yaml
dependencies:
  json_annotation: ^4.9.0

dev_dependencies:
  json_serializable: ^6.8.0
```

## Commandes utiles

```bash
# Installer les dépendances
flutter pub get

# Générer les fichiers JSON
flutter packages pub run build_runner build

# Analyser le code
flutter analyze

# Lancer l'application
flutter run
```

## Troubleshooting

### Erreur de compilation
- Vérifier que les fichiers `.g.dart` sont générés
- Relancer `flutter packages pub run build_runner build --delete-conflicting-outputs`

### Erreur réseau
- Vérifier l'URL de base dans `ApiConstants.baseUrl`
- S'assurer que le backend est accessible depuis l'émulateur
- Vérifier les permissions réseau dans `android/app/src/main/AndroidManifest.xml`

### Données vides
- Vérifier que les seeders ont été exécutés sur le backend
- Tester l'endpoint directement avec curl ou Postman
- Vérifier les logs Laravel pour les erreurs

## Prochaines étapes

1. **Cache local**: Implémenter un cache SQLite pour les données de référence
2. **Synchronisation**: Ajouter une synchronisation périodique
3. **Offline**: Gérer le mode hors ligne
4. **Optimisation**: Pagination pour de grandes listes de métiers