import 'package:flutter_test/flutter_test.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';

void main() {
  group('GPSCoordinates', () {
    test('creates valid coordinates', () {
      const coords = GPSCoordinates(
        latitude: 5.3600,
        longitude: -4.0083,
        accuracy: 5.0,
      );

      expect(coords.latitude, equals(5.3600));
      expect(coords.longitude, equals(-4.0083));
      expect(coords.accuracy, equals(5.0));
    });

    test('creates from JSON', () {
      final json = {'latitude': 5.3600, 'longitude': -4.0083, 'accuracy': 8.0};

      final coords = GPSCoordinates.fromJson(json);

      expect(coords.latitude, equals(5.3600));
      expect(coords.longitude, equals(-4.0083));
      expect(coords.accuracy, equals(8.0));
    });

    test('rejects invalid latitude', () {
      expect(
        () => GPSCoordinates(latitude: 91.0, longitude: -4.0083),
        throwsAssertionError,
      );
    });

    test('rejects invalid longitude', () {
      expect(
        () => GPSCoordinates(latitude: 5.3600, longitude: 181.0),
        throwsAssertionError,
      );
    });

    test('rejects negative accuracy', () {
      expect(
        () => GPSCoordinates(
          latitude: 5.3600,
          longitude: -4.0083,
          accuracy: -5.0,
        ),
        throwsAssertionError,
      );
    });

    test('calculates distance using Haversine', () {
      // Abidjan coordinates
      const abidjan = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);
      // Yamoussoukro coordinates (approximately 230km from Abidjan)
      const yamoussoukro = GPSCoordinates(latitude: 6.8276, longitude: -5.2893);

      final distance = abidjan.distanceTo(yamoussoukro);

      // Distance should be approximately 230,000 meters (230 km)
      expect(distance, greaterThan(200000));
      expect(distance, lessThan(250000));
    });

    test('blurs coordinates within radius', () {
      const original = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);

      final blurred = original.blur(50); // 50 meter blur

      final distance = original.distanceTo(blurred);

      // Blurred coordinates should be within 50 meters
      expect(distance, lessThanOrEqualTo(50));
    });

    test('checks if within radius', () {
      const center = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);
      const nearby = GPSCoordinates(
        latitude: 5.3610,
        longitude: -4.0093,
      ); // ~1.5km away
      const faraway = GPSCoordinates(
        latitude: 6.8276,
        longitude: -5.2893,
      ); // ~230km away

      expect(nearby.isWithinRadius(center, 2000), isTrue); // 2km radius
      expect(faraway.isWithinRadius(center, 2000), isFalse);
    });

    test('checks acceptable accuracy', () {
      const accurate = GPSCoordinates(
        latitude: 5.3600,
        longitude: -4.0083,
        accuracy: 5.0,
      );
      const inaccurate = GPSCoordinates(
        latitude: 5.3600,
        longitude: -4.0083,
        accuracy: 15.0,
      );

      expect(accurate.hasAcceptableAccuracy, isTrue);
      expect(inaccurate.hasAcceptableAccuracy, isFalse);
    });

    test('converts to PostGIS point', () {
      const coords = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);

      final point = coords.toPostGISPoint();

      expect(point, equals('POINT(-4.0083 5.36)'));
    });

    test('converts to JSON', () {
      const coords = GPSCoordinates(
        latitude: 5.3600,
        longitude: -4.0083,
        accuracy: 5.0,
      );

      final json = coords.toJson();

      expect(json['latitude'], equals(5.3600));
      expect(json['longitude'], equals(-4.0083));
      expect(json['accuracy'], equals(5.0));
    });

    test('equals comparison', () {
      const coords1 = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);
      const coords2 = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);
      const coords3 = GPSCoordinates(latitude: 5.3601, longitude: -4.0083);

      expect(coords1, equals(coords2));
      expect(coords1, isNot(equals(coords3)));
    });

    test('converts to string', () {
      const coords = GPSCoordinates(latitude: 5.3600, longitude: -4.0083);

      final string = coords.toString();

      expect(string, equals('5.36,-4.0083'));
    });
  });
}
