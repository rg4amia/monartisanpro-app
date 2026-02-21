# Map Markers for ProsArtisan

Ce dossier contient les marqueurs personnalisés pour les cartes de l'application ProsArtisan.

## Fichiers SVG disponibles

### `user-position.svg`
- **Description**: Marqueur de position utilisateur avec animation de pulsation
- **Couleur**: Bleu principal (#4F46E5)
- **Animation**: Cercles pulsants pour indiquer la position actuelle
- **Dimensions**: 64x64 pixels
- **Utilisation**: Position actuelle de l'utilisateur sur la carte

### `artisan-marker.svg`
- **Description**: Marqueur d'artisan standard
- **Couleur**: Vert (#10B981)
- **Icône**: Outil (build)
- **Dimensions**: 48x64 pixels
- **Utilisation**: Artisans normaux (> 2km)

### `nearby-artisan.svg`
- **Description**: Marqueur d'artisan proche (< 2km)
- **Couleur**: Or (#F59E0B)
- **Badge**: "2km" en rouge
- **Dimensions**: 48x64 pixels
- **Utilisation**: Artisans à moins de 2km

### `marker-fine.svg` (existant)
- **Description**: Marqueur générique existant
- **Couleur**: Rouge avec point noir
- **Dimensions**: 256x256 pixels

### `marker.png` (existant)
- **Description**: Marqueur PNG existant

## Utilisation dans Flutter

### Installation du package
Ajoutez `flutter_svg` dans `pubspec.yaml`:
```yaml
dependencies:
  flutter_svg: ^2.0.10
```

### Widget MapMarkers
Utilisez la classe `MapMarkers` pour créer des marqueurs programmatiquement:

```dart
import 'package:frontend/shared/widgets/map_markers.dart';

// Marqueur de position utilisateur
MapMarkers.userPosition(size: 64.0);

// Marqueur d'artisan standard
MapMarkers.artisanMarker(size: 48.0);

// Marqueur d'artisan proche
MapMarkers.artisanMarker(isNearby: true);

// Marqueur d'artisan avec note
MapMarkers.artisanMarker(rating: 5);

// Marqueur de cluster
MapMarkers.clusterMarker(count: 8);

// Marqueur SVG
MapMarkers.userPositionSvg();
MapMarkers.artisanMarkerSvg(isNearby: true);

// Marqueur avec image
MapMarkers.imageMarker(
  imageUrl: 'https://example.com/avatar.jpg',
  size: 48.0,
);

// Marqueur de catégorie
MapMarkers.categoryMarker(
  icon: Icons.build,
  color: Colors.blue,
);
```

### Exemple d'utilisation sur une carte
```dart
import 'package:flutter/material.dart';
import 'package:frontend/shared/widgets/map_markers.dart';

class MapScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        // Carte (Google Maps, Yandex Maps, etc.)
        
        // Position utilisateur
        Positioned(
          left: userPosition.x,
          top: userPosition.y,
          child: MapMarkers.userPosition(),
        ),
        
        // Artisans
        ...artisans.map((artisan) {
          return Positioned(
            left: artisan.position.x,
            top: artisan.position.y,
            child: MapMarkers.artisanMarker(
              isNearby: artisan.distance < 2000,
              rating: artisan.rating,
            ),
          );
        }),
        
        // Clusters
        ...clusters.map((cluster) {
          return Positioned(
            left: cluster.position.x,
            top: cluster.position.y,
            child: MapMarkers.clusterMarker(count: cluster.count),
          );
        }),
      ],
    );
  }
}
```

## Personnalisation

### Tailles recommandées
- **Position utilisateur**: 64px
- **Artisans**: 48px
- **Clusters**: 40px
- **Catégories**: 40px

### Couleurs
Utilisez les couleurs de `AppColors` pour la cohérence:
- `AppColors.lightAccentPrimary` (#4F46E5) - Position utilisateur
- `AppColors.success` (#10B981) - Artisans normaux
- `AppColors.warning` (#F59E0B) - Artisans proches
- `AppColors.error` (#EF4444) - Badges
- `AppColors.starRating` (#FBBF24) - Étoiles de notation

### Animations
Le marqueur `user-position.svg` inclut des animations CSS:
- Cercles pulsants pour indiquer la position
- Effet de fondu pour les cercles extérieurs
- Animation infinie de 2 secondes

## Bonnes pratiques

1. **Utilisez les SVG** pour les marqueurs statiques (meilleure qualité)
2. **Utilisez les widgets programmatiques** pour les marqueurs dynamiques (notes, badges)
3. **Adaptez la taille** selon le niveau de zoom de la carte
4. **Utilisez les clusters** pour regrouper les marqueurs proches
5. **Testez les performances** avec un grand nombre de marqueurs

## Exemple complet
Voir `map_markers_example.dart` pour un exemple complet d'utilisation.

## Support
Pour toute question sur les marqueurs, consultez la documentation de `flutter_svg` et les exemples fournis.