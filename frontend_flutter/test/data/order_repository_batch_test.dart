import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/repositories/order_repository.dart';

import '../helpers/fake_http_adapter.dart';
import '../helpers/test_helpers.dart';

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

  group('OrderRepository — Tournées groupées multi-drop', () {
    test('getDeliveryBatches renvoie la liste des tournées groupées', () async {
      adapter.on(
        'GET',
        '/deliveries/batches',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': [
              {
                'batch_id': 'BATCH-1-10',
                'supplier_name': 'Quincaillerie Centrale',
                'orders_count': 2,
                'total_distance_km': 7.8,
                'driver_earnings': 6500,
              },
            ],
          },
        ),
      );

      final batches = await repository.getDeliveryBatches();

      expect(batches, isNotEmpty);
      expect(batches.length, 1);
      expect(batches.first['batch_id'], 'BATCH-1-10');
      expect(batches.first['orders_count'], 2);
    });

    test('acceptDeliveryBatch accepte le lot et transmet les IDs de commandes', () async {
      adapter.on(
        'POST',
        '/deliveries/batch-accept',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'message': 'Tournée acceptée',
            'data': {
              'tour_id': 'TOUR-ABC123',
              'orders_count': 2,
            },
          },
        ),
      );

      final result = await repository.acceptDeliveryBatch([10, 11]);

      expect(result['success'], isTrue);
      expect(adapter.requests.last.data, {'order_ids': [10, 11]});
    });

    test('getActiveDeliveryTour renvoie la tournée active courante', () async {
      adapter.on(
        'GET',
        '/deliveries/active-tour',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {
              'tour_id': 'TOUR-ACTIVE-1',
              'total_orders': 2,
              'pending_pickups': 1,
              'pending_deliveries': 1,
            },
          },
        ),
      );

      final tour = await repository.getActiveDeliveryTour();

      expect(tour, isNotNull);
      expect(tour!['tour_id'], 'TOUR-ACTIVE-1');
      expect(tour['total_orders'], 2);
    });
  });
}
