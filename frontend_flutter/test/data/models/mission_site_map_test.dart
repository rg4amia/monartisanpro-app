import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/mission_site_map.dart';

void main() {
  group('MissionSiteMap', () {
    test('parse client + suppliers', () {
      final map = MissionSiteMap.fromJson({
        'mission_id': 42,
        'client': {
          'name': 'Awa',
          'address': 'Cocody',
          'coordinates': {'lat': 5.36, 'lng': -4.01},
        },
        'suppliers': [
          {
            'id': 7,
            'name': 'Quincaillerie Nord',
            'coordinates': {'lat': 5.37, 'lng': -4.02},
            'jcodeCount': 2,
            'montant': 55000,
          },
        ],
      });

      expect(map.missionId, 42);
      expect(map.client, isNotNull);
      expect(map.client!.lat, 5.36);
      expect(map.client!.name, 'Awa');
      expect(map.suppliers, hasLength(1));
      expect(map.suppliers.first.name, 'Quincaillerie Nord');
      expect(map.suppliers.first.jcodeCount, 2);
      expect(map.suppliers.first.montant, 55000);
      expect(map.hasAnyPoint, isTrue);
    });

    test('null client and empty suppliers', () {
      final map = MissionSiteMap.fromJson({
        'mission_id': 1,
        'client': null,
        'suppliers': <dynamic>[],
      });

      expect(map.client, isNull);
      expect(map.suppliers, isEmpty);
      expect(map.hasAnyPoint, isFalse);
    });
  });
}
