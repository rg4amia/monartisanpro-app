import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/driver_route_model.dart';
import 'package:frontend_flutter/data/repositories/order_repository.dart';

import '../helpers/fake_http_adapter.dart';
import '../helpers/test_helpers.dart';

/// Itinéraire d'une course servi par le serveur, et appels du récapitulatif
/// de commande passés par le dépôt (Chantier 43).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;
  late OrderRepository repository;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
    repository = OrderRepository();
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  group('itinéraire d\'une course', () {
    const body = {
      'success': true,
      'data': {
        'leg': 'pickup',
        'from': {'lat': 5.30, 'lng': -4.01},
        'to': {'lat': '5.3484', 'lng': -4.0267},
        'route': {
          'distance_km': 4,
          'duration_min': 10.4,
          'is_fallback': false,
          // GeoJSON : longitude d'abord.
          'geometry': [
            [-4.01, 5.30],
            [-4.02, 5.32],
            [-4.0267, 5.3484],
          ],
        },
      },
    };

    test('les extrémités et le tracé viennent du serveur', () async {
      adapter.on(
        'GET',
        '/orders/7/route',
        const CannedResponse(statusCode: 200, body: body),
      );

      final route = await repository.getDriverRoute(
        7,
        leg: 'pickup',
        fromLat: 5.30,
        fromLng: -4.01,
      );

      expect(route.to.latitude, 5.3484);
      expect(route.to.longitude, -4.0267);
      expect(route.path, hasLength(3));
      expect(route.path.first.latitude, 5.30);
      expect(route.path.first.longitude, -4.01);
      expect(route.distanceKm, 4.0);
      expect(route.isEstimate, isFalse);

      final sent = adapter.requests.single.queryParameters;
      expect(sent['leg'], 'pickup');
      expect(sent['from_lat'], 5.30);
    });

    test('une estimation du serveur est annoncée comme telle', () {
      final route = DriverRoute.fromResponse({
        'data': {
          'from': {'lat': 5.3, 'lng': -4.0},
          'to': {'lat': 5.4, 'lng': -4.1},
          'route': {'distance_km': 14.2, 'is_fallback': true},
        },
      });

      expect(route.isEstimate, isTrue);
      // Sans tracé, une ligne droite entre les deux points connus.
      expect(route.path, hasLength(2));
    });

    test('sans indication du serveur, la distance n\'est pas dite exacte', () {
      final route = DriverRoute.fromResponse({
        'data': {
          'from': {'lat': 5.3, 'lng': -4.0},
          'to': {'lat': 5.4, 'lng': -4.1},
        },
      });

      expect(route.isEstimate, isTrue);
      expect(route.distanceKm, isNull);
    });

    test('une réponse sans destination est une erreur, pas un trajet', () {
      expect(
        () => DriverRoute.fromResponse({
          'data': {
            'from': {'lat': 5.3, 'lng': -4.0},
          },
        }),
        throwsFormatException,
      );
      expect(() => DriverRoute.fromResponse(const []), throwsFormatException);
    });

    test('une position inconnue du serveur remonte à l\'écran', () async {
      adapter.on(
        'GET',
        '/orders/7/route',
        const CannedResponse(
          statusCode: 422,
          body: {
            'success': false,
            'message': "La position de la boutique n'est pas connue.",
          },
        ),
      );

      await expectLater(
        repository.getDriverRoute(7, leg: 'delivery'),
        throwsA(isA<DioException>()),
      );
    });
  });

  group('code promo', () {
    test('un code valide rend la remise', () async {
      adapter.on(
        'POST',
        '/promo-codes/verify',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'message': 'Code appliqué.',
            'data': {
              'discount_amount': 1500,
              'discount_type': 'fixed',
              'discount_value': 1500,
            },
          },
        ),
      );

      final check =
          await repository.verifyPromoCode(code: 'PROS225', amount: 20000);

      expect(check.valid, isTrue);
      expect(check.discountAmount, 1500.0);
      expect(check.detail, '-1500.0 FCFA');
    });

    test('un code refusé est une réponse, avec le message du serveur',
        () async {
      adapter.on(
        'POST',
        '/promo-codes/verify',
        const CannedResponse(
          statusCode: 422,
          body: {'success': false, 'message': 'Ce code promo a expiré.'},
        ),
      );

      final check =
          await repository.verifyPromoCode(code: 'VIEUX', amount: 20000);

      expect(check.valid, isFalse);
      expect(check.message, 'Ce code promo a expiré.');
    });

    test('une panne du serveur n\'est pas un code refusé', () async {
      adapter.on(
        'POST',
        '/promo-codes/verify',
        const CannedResponse(statusCode: 500, body: {'message': 'Erreur.'}),
      );

      await expectLater(
        repository.verifyPromoCode(code: 'PROS225', amount: 20000),
        throwsA(isA<DioException>()),
      );
    });
  });

  group('estimation de la course', () {
    test('rend les données du serveur', () async {
      adapter.on(
        'POST',
        '/deliveries/estimate',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {'delivery_cost': 1800, 'distance_km': 4.2},
          },
        ),
      );

      final data = await repository.estimateDelivery(
        supplierId: 3,
        vehicleClass: 'moto',
        items: const [
          {'supplier_product_id': 1, 'quantity': 2},
        ],
        addressId: 9,
      );

      expect(data?['delivery_cost'], 1800);
      expect((adapter.requests.single.data as Map)['address_id'], 9);
    });

    test('rend null quand le serveur ne donne pas d\'estimation', () async {
      adapter.on(
        'POST',
        '/deliveries/estimate',
        const CannedResponse(statusCode: 200, body: {'success': false}),
      );

      expect(
        await repository.estimateDelivery(
          supplierId: 3,
          vehicleClass: 'moto',
          items: const [],
        ),
        isNull,
      );
    });
  });
}
