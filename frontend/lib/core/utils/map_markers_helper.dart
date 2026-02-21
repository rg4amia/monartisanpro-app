import 'package:flutter/material.dart';
import 'package:yandex_maps_mapkit/image.dart' as mapkit_image;

/// Helper class for creating custom map markers
class MapMarkersHelper {
  MapMarkersHelper._();

  /// Creates a custom marker icon from a widget
  static Future<mapkit_image.ImageProvider> createMarkerFromWidget(
    Widget widget, {
    double width = 48,
    double height = 48,
  }) async {
    // Note: This is a placeholder. In production, you would use a package like
    // 'screenshot' or render the widget to an image
    // For now, we'll use default markers
    throw UnimplementedError(
      'Custom marker creation requires additional implementation',
    );
  }

  /// Gets the asset path for user position marker
  static String getUserPositionMarkerAsset() {
    return 'assets/markers/user_position.png';
  }

  /// Gets the asset path for artisan marker
  static String getArtisanMarkerAsset({required bool isNearby}) {
    return isNearby
        ? 'assets/markers/artisan_nearby.png'
        : 'assets/markers/artisan_regular.png';
  }

  /// Gets the asset path for cluster marker
  static String getClusterMarkerAsset() {
    return 'assets/markers/cluster.png';
  }
}
