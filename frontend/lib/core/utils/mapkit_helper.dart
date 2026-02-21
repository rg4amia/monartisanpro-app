import 'package:yandex_maps_mapkit/mapkit.dart';

/// Helper class pour faciliter l'utilisation de Yandex MapKit
class MapKitHelper {
  /// Crée un Point à partir de coordonnées latitude/longitude
  static Point createPoint(double latitude, double longitude) {
    return Point(latitude: latitude, longitude: longitude);
  }

  /// Crée une CameraPosition
  static CameraPosition createCameraPosition({
    required double latitude,
    required double longitude,
    double zoom = 15.0,
    double azimuth = 0.0,
    double tilt = 0.0,
  }) {
    return CameraPosition(
      createPoint(latitude, longitude),
      zoom: zoom,
      azimuth: azimuth,
      tilt: tilt,
    );
  }

  /// Crée une animation de caméra smooth
  static Animation createSmoothAnimation({double duration = 1.0}) {
    return Animation(type: AnimationType.Smooth, duration: duration);
  }

  /// Crée une animation de caméra linéaire
  static Animation createLinearAnimation({double duration = 1.0}) {
    return Animation(type: AnimationType.Linear, duration: duration);
  }
}
