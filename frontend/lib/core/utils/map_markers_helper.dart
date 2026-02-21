import 'package:flutter/material.dart';
import 'package:yandex_maps_mapkit/mapkit.dart';

/// Helper simplifié pour créer des marqueurs pour Yandex Maps
///
/// Note: Cette version utilise l'API de base de Yandex MapKit.
/// Pour des marqueurs personnalisés complexes, il faudra utiliser
/// la méthode setIcon() sur les placemarks après leur création.
class MapMarkersHelper {
  /// Crée un Point à partir de coordonnées
  static Point createPoint(double latitude, double longitude) {
    return Point(latitude: latitude, longitude: longitude);
  }

  /// Retourne le chemin d'asset approprié pour un artisan
  static String getArtisanMarkerAsset({required bool isNearby}) {
    return isNearby
        ? 'assets/markers/nearby-artisan.svg'
        : 'assets/markers/artisan-marker.svg';
  }

  /// Retourne le chemin d'asset pour la position utilisateur
  static String getUserPositionMarkerAsset() {
    return 'assets/markers/user-position.svg';
  }

  /// Retourne le chemin d'asset pour un marqueur générique
  static String getGenericMarkerAsset() {
    return 'assets/markers/marker.png';
  }

  /// Ancre pour un marqueur d'artisan (pointe vers le bas)
  static const artisanAnchor = Offset(0.5, 1.0);

  /// Ancre pour un marqueur centré
  static const centerAnchor = Offset(0.5, 0.5);
}
