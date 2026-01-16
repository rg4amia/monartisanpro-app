import 'dart:math';

/// Value Object representing GPS coordinates (latitude, longitude)
/// Includes accuracy tracking and distance calculation using Haversine formula
class GPSCoordinates {
  final double latitude;
  final double longitude;
  final double accuracy; // in meters

  static const double _earthRadiusKm = 6371;
  static const double _minLatitude = -90.0;
  static const double _maxLatitude = 90.0;
  static const double _minLongitude = -180.0;
  static const double _maxLongitude = 180.0;

  const GPSCoordinates({
    required this.latitude,
    required this.longitude,
    this.accuracy = 10.0,
  }) : assert(
         latitude >= _minLatitude && latitude <= _maxLatitude,
         'Latitude must be between -90 and 90',
       ),
       assert(
         longitude >= _minLongitude && longitude <= _maxLongitude,
         'Longitude must be between -180 and 180',
       ),
       assert(accuracy >= 0, 'GPS accuracy cannot be negative');

  factory GPSCoordinates.fromJson(Map<String, dynamic> json) {
    return GPSCoordinates(
      latitude: (json['latitude'] as num).toDouble(),
      longitude: (json['longitude'] as num).toDouble(),
      accuracy: (json['accuracy'] as num?)?.toDouble() ?? 10.0,
    );
  }

  /// Calculate distance to another coordinate using Haversine formula
  /// Returns distance in meters
  double distanceTo(GPSCoordinates other) {
    final latFrom = _degreesToRadians(latitude);
    final lonFrom = _degreesToRadians(longitude);
    final latTo = _degreesToRadians(other.latitude);
    final lonTo = _degreesToRadians(other.longitude);

    final latDelta = latTo - latFrom;
    final lonDelta = lonTo - lonFrom;

    final a =
        sin(latDelta / 2) * sin(latDelta / 2) +
        cos(latFrom) * cos(latTo) * sin(lonDelta / 2) * sin(lonDelta / 2);

    final c = 2 * atan2(sqrt(a), sqrt(1 - a));

    final distanceKm = _earthRadiusKm * c;

    return distanceKm * 1000; // Convert to meters
  }

  /// Blur coordinates by adding random offset within specified radius
  /// Used for privacy protection (e.g., 50m blur for artisan locations)
  GPSCoordinates blur(int radiusMeters) {
    // Convert radius from meters to degrees (approximate)
    final radiusDegrees = radiusMeters / 111000; // 1 degree ≈ 111km

    final random = Random();
    // Generate random angle and distance
    final angle = random.nextDouble() * 2 * pi;
    final distance = sqrt(random.nextDouble()) * radiusDegrees;

    // Calculate new coordinates
    var newLat = latitude + (distance * cos(angle));
    var newLon = longitude + (distance * sin(angle) / cos(latitude * pi / 180));

    // Ensure coordinates stay within valid bounds
    newLat = max(_minLatitude, min(_maxLatitude, newLat));
    newLon = max(_minLongitude, min(_maxLongitude, newLon));

    return GPSCoordinates(
      latitude: newLat,
      longitude: newLon,
      accuracy: accuracy,
    );
  }

  /// Check if coordinates are within specified distance of another point
  bool isWithinRadius(GPSCoordinates center, double radiusMeters) {
    return distanceTo(center) <= radiusMeters;
  }

  /// Check if GPS accuracy is acceptable (< 10m)
  bool get hasAcceptableAccuracy => accuracy <= 10.0;

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is GPSCoordinates &&
        (latitude - other.latitude).abs() < 0.000001 &&
        (longitude - other.longitude).abs() < 0.000001;
  }

  @override
  int get hashCode => Object.hash(latitude, longitude);

  /// Convert to PostGIS POINT format for API communication
  String toPostGISPoint() => 'POINT($longitude $latitude)';

  Map<String, dynamic> toJson() {
    return {'latitude': latitude, 'longitude': longitude, 'accuracy': accuracy};
  }

  @override
  String toString() => '$latitude,$longitude';

  double _degreesToRadians(double degrees) => degrees * pi / 180;
}
