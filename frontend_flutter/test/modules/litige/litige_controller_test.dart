import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/mission_model.dart';
import 'package:frontend_flutter/modules/litige/controllers/litige_controller.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

/// `onInit()` accepte trois formes d'arguments selon l'écran appelant
/// (MissionModel complet depuis une carte de suivi, id brut, ou Map interne).
/// Un simple `as Map?` strict plantait en release (écran gris) dès qu'un
/// MissionModel était passé directement — ce test verrouille les trois formes
/// pour empêcher une régression silencieuse sur ce point d'entrée.
MissionModel _mission({required int id}) => MissionModel(
      id: id,
      clientId: 1,
      artisanId: 2,
      status: 'in_progress',
      montantTotal: 300000,
      montantMateriaux: 100000,
      montantMo: 200000,
      ratioMateriaux: 0.33,
      createdAt: '2026-01-01T00:00:00Z',
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.routing.args = null;
    await TestHelpers.cleanupTestData();
  });

  group('LitigeController.onInit — extraction de missionId', () {
    test('accepte un MissionModel complet', () {
      Get.routing.args = _mission(id: 77);

      final controller = LitigeController()..onInit();

      expect(controller.missionId, 77);
    });

    test('accepte un id brut (int)', () {
      Get.routing.args = 55;

      final controller = LitigeController()..onInit();

      expect(controller.missionId, 55);
    });

    test('accepte une Map interne {missionId: ...}', () {
      Get.routing.args = {'missionId': 88};

      final controller = LitigeController()..onInit();

      expect(controller.missionId, 88);
    });

    test('retombe sur 0 pour tout autre type sans planter', () {
      Get.routing.args = 'valeur inattendue';

      final controller = LitigeController()..onInit();

      expect(controller.missionId, 0);
    });

    test('retombe sur 0 en l\'absence d\'argument', () {
      Get.routing.args = null;

      final controller = LitigeController()..onInit();

      expect(controller.missionId, 0);
    });
  });

  group('LitigeController.submit — garde-fous de validation', () {
    testWidgets('refuse une description vide sans appeler le réseau', (tester) async {
      Get.routing.args = _mission(id: 1);
      final controller = LitigeController()..onInit();
      controller.description.value = '   ';

      await runControllerAction(tester, controller.submit);

      expect(controller.isLoading.value, isFalse);
      expect(adapter.requests, isEmpty);
    });

    testWidgets('refuse un missionId introuvable sans appeler le réseau', (tester) async {
      Get.routing.args = 'invalide';
      final controller = LitigeController()..onInit();
      controller.description.value = 'Matériaux non livrés';

      await runControllerAction(tester, controller.submit);

      expect(controller.isLoading.value, isFalse);
      expect(adapter.requests, isEmpty);
    });
  });

  group('LitigeController.submit — appel réseau', () {
    testWidgets('ouvre le dossier et redirige vers son détail en cas de succès', (tester) async {
      adapter.on(
        'POST',
        '/litiges',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {'id': 42},
          },
        ),
      );
      Get.routing.args = _mission(id: 1);
      final controller = LitigeController()..onInit();
      controller.description.value = 'Matériaux non livrés selon le devis';

      await runControllerAction(tester, controller.submit);

      expect(controller.isLoading.value, isFalse);
      expect(adapter.requests, hasLength(1));
      expect(adapter.requests.single.data, isA<Map>());
      expect(
        (adapter.requests.single.data as Map)['mission_id'],
        1,
      );
    });

    testWidgets('signale une erreur réseau sans planter', (tester) async {
      // Aucune route enregistrée ⇒ connectionError simulée.
      Get.routing.args = _mission(id: 1);
      final controller = LitigeController()..onInit();
      controller.description.value = 'Matériaux non livrés selon le devis';

      await runControllerAction(tester, controller.submit);

      expect(controller.isLoading.value, isFalse);
    });
  });
}
