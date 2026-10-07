import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/repositories/evaluation_repository.dart';
import 'package:frontend_flutter/data/repositories/order_repository.dart';
import 'package:frontend_flutter/modules/home/controllers/home_controller.dart';
import 'package:get/get.dart' hide Response;

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Une panne n'est pas une absence (Chantier 42, Règles d'or 29 et 75).
///
/// Les lectures du livreur avalaient toute erreur et rendaient une liste
/// vide : sans réseau, l'écran affichait « Aucune course disponible ».
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  const refused = CannedResponse(
    statusCode: 500,
    body: {'message': 'Erreur du serveur.'},
  );

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.reset();
    await CacheStore.wipeAll();
    await TestHelpers.cleanupTestData();
  });

  group('lectures du livreur', () {
    test('une panne remonte au lieu de rendre une liste vide', () async {
      adapter
        ..on('GET', '/deliveries/available', refused)
        ..on('GET', '/deliveries/batches', refused)
        ..on('GET', '/deliveries/active-tour', refused)
        ..on('GET', '/orders', refused)
        ..on('GET', '/orders/7/tracking', refused);
      final repo = OrderRepository();

      final failure = throwsA(isA<DioException>());

      await expectLater(repo.getAvailableDeliveries(), failure);
      await expectLater(repo.getDeliveryBatches(), failure);
      await expectLater(repo.getActiveDeliveryTour(), failure);
      await expectLater(repo.getMyOrders(forceRefresh: true), failure);
      await expectLater(repo.getOrderTracking(7), failure);
    });

    test('une réponse sans liste est une erreur, pas « aucune course »',
        () async {
      adapter.on(
        'GET',
        '/deliveries/available',
        const CannedResponse(statusCode: 200, body: {'success': true}),
      );

      await expectLater(
        OrderRepository().getAvailableDeliveries(),
        throwsA(isA<FormatException>()),
      );
    });

    test('le serveur peut répondre qu\'il n\'y a pas de tournée active',
        () async {
      adapter.on(
        'GET',
        '/deliveries/active-tour',
        const CannedResponse(
          statusCode: 200,
          body: {'success': true, 'data': null},
        ),
      );

      expect(await OrderRepository().getActiveDeliveryTour(), isNull);
    });
  });

  test('les évaluations ne rendent plus « rien » sur une panne', () async {
    adapter.on('GET', '/evaluations/my', refused);

    await expectLater(
      EvaluationRepository().getMyEvaluations(),
      throwsA(isA<DioException>()),
    );
  });

  test('l\'accueil du livreur annonce l\'échec, puis sa levée', () async {
    final controller = HomeController()..role.value = 'livreur';

    adapter
      ..on('GET', '/deliveries/available', refused)
      ..on('GET', '/orders', refused);
    await controller.retryDriverMissions();
    expect(controller.driverMissionsLoadFailed.value, isTrue);

    adapter
      ..on(
        'GET',
        '/deliveries/available',
        const CannedResponse(statusCode: 200, body: {'data': []}),
      )
      ..on(
        'GET',
        '/orders',
        const CannedResponse(statusCode: 200, body: {'data': []}),
      );
    await controller.retryDriverMissions();
    expect(controller.driverMissionsLoadFailed.value, isFalse);
    expect(controller.driverAvailableMissions, isEmpty);
  });
}
