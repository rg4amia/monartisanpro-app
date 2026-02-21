class MapConstants {
  // Yandex MapKit API Key
  static const String yandexMapApiKey = 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d';

  // Map configuration
  static const double defaultZoom = 14.0;
  static const double minZoom = 5.0;
  static const double maxZoom = 18.0;

  // Search radius options (in meters)
  static const List<double> radiusOptions = [
    1000, // 1km
    2000, // 2km
    5000, // 5km
    10000, // 10km
    20000, // 20km
    50000, // 50km
  ];

  // Nearby threshold (in meters)
  static const double nearbyThreshold = 2000; // 2km
}
