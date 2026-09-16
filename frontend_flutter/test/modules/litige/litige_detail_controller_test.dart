import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/modules/litige/controllers/litige_detail_controller.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

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

  group('LitigeDetailController.onInit — extraction de litigeId', () {
    test('accepte un id brut (redirection notification push)', () async {
      Get.routing.args = 12;
      adapter.on(
        'GET',
        '/litiges/12',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {'id': 12, 'statut': 'ouvert', 'workflowStep': 'preuves'},
          },
        ),
      );

      final controller = LitigeDetailController()..onInit();
      await controller.loadLitige();

      expect(controller.litigeId, 12);
    });

    test('accepte une Map interne {litigeId: ...}', () async {
      Get.routing.args = {'litigeId': 34};
      adapter.on(
        'GET',
        '/litiges/34',
        const CannedResponse(statusCode: 200, body: {'data': null}),
      );

      final controller = LitigeDetailController()..onInit();
      await controller.loadLitige();

      expect(controller.litigeId, 34);
    });

    test('retombe sur 0 pour un type non reconnu sans planter', () async {
      Get.routing.args = 'valeur inattendue';
      adapter.on(
        'GET',
        '/litiges/0',
        const CannedResponse(statusCode: 200, body: {'data': null}),
      );

      final controller = LitigeDetailController()..onInit();
      await controller.loadLitige();

      expect(controller.litigeId, 0);
    });
  });

  group('LitigeDetailController.canUploadEvidence', () {
    test('refuse tant que le dossier est chargé', () {
      final controller = LitigeDetailController();
      expect(controller.canUploadEvidence, isFalse);
    });

    test('autorise uniquement au step "preuves" sur un dossier non résolu', () {
      final controller = LitigeDetailController()
        ..litige.value = {'statut': 'ouvert', 'workflowStep': 'preuves'};

      expect(controller.canUploadEvidence, isTrue);
    });

    test('refuse une fois le litige résolu, même au step "preuves"', () {
      final controller = LitigeDetailController()
        ..litige.value = {'statut': 'resolu', 'workflowStep': 'preuves'};

      expect(controller.canUploadEvidence, isFalse);
    });

    test('refuse en dehors du step "preuves"', () {
      final controller = LitigeDetailController()
        ..litige.value = {'statut': 'ouvert', 'workflowStep': 'instruction'};

      expect(controller.canUploadEvidence, isFalse);
    });
  });
}
