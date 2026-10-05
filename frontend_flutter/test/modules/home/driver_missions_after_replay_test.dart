import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:frontend_flutter/modules/home/controllers/home_controller.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Après le rejeu d'une validation enregistrée hors connexion, l'écran du
/// livreur relit ses courses sur le serveur (Chantier 35).
///
/// Une validation mise en file fait avancer la course à l'écran sans attendre
/// le serveur : refusée au rejeu, elle restait affichée « en route ».
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
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
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
  });

  // `ApiClient` retire le `/` initial du chemin avant l'envoi.
  int ordersReads() =>
      adapter.requests.where((r) => r.path == 'orders').length;

  test('le livreur relit ses courses quand la file est vide', () async {
    final controller = HomeController()..role.value = 'livreur';

    expect(await controller.refreshDriverMissionsAfterReplay(), isTrue);
    expect(ordersReads(), 1);

    // Le cache local ne doit pas masquer l'état du serveur : chaque rejeu
    // relit réellement.
    expect(await controller.refreshDriverMissionsAfterReplay(), isTrue);
    expect(ordersReads(), 2);
  });

  test('rien n\'est relu tant qu\'une validation attend d\'être transmise',
      () async {
    final sync = Get.put(SyncService());
    sync.pendingCount.value = 1;
    final controller = HomeController()..role.value = 'livreur';

    expect(await controller.refreshDriverMissionsAfterReplay(), isFalse);
    expect(ordersReads(), 0);

    sync.pendingCount.value = 0;
    expect(await controller.refreshDriverMissionsAfterReplay(), isTrue);
    expect(ordersReads(), 1);
  });

  test('les autres espaces ne sont pas concernés', () async {
    for (final role in ['client', 'artisan', 'fournisseur']) {
      final controller = HomeController()..role.value = role;

      expect(await controller.refreshDriverMissionsAfterReplay(), isFalse);
    }
    expect(ordersReads(), 0);
  });
}
