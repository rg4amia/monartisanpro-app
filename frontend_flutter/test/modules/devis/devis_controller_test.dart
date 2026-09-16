import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/modules/devis/controllers/devis_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
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
    await TestHelpers.cleanupTestData();
    // `getDevis`/`getMissionDevis` sont mis en cache (Hive) indépendamment du
    // stockage de session purgé ci-dessus.
    await CacheStore.wipeAll();
  });

  group('DevisController — composition des lignes et jalons', () {
    test('totalMo / totalMat / total ventilent par type de ligne', () {
      final controller = DevisController()
        ..addLigne('mo', 'Pose carrelage', 50000)
        ..addLigne('mat', 'Ciment', 30000)
        ..addLigne('mat', 'Sable', 10000);

      expect(controller.totalMo, 50000);
      expect(controller.totalMat, 40000);
      expect(controller.total, 90000);
    });

    test('removeLigne retire la ligne ciblée par index', () {
      final controller = DevisController()
        ..addLigne('mo', 'Pose carrelage', 50000)
        ..addLigne('mat', 'Ciment', 30000);

      controller.removeLigne(0);

      expect(controller.lignes, hasLength(1));
      expect(controller.lignes.single.type, 'mat');
    });

    test('addJalon numérote les jalons dans l\'ordre d\'ajout', () {
      final controller = DevisController()
        ..addJalon('Démarrage', 20000, '2026-02-01')
        ..addJalon('Mi-parcours', 30000, '2026-02-15');

      expect(controller.jalons.map((j) => j.ordre), [1, 2]);
    });

    test('removeJalon renumérote les jalons restants sans trou', () {
      final controller = DevisController()
        ..addJalon('Démarrage', 20000, '2026-02-01')
        ..addJalon('Mi-parcours', 30000, '2026-02-15')
        ..addJalon('Livraison', 10000, '2026-03-01');

      controller.removeJalon(0);

      expect(controller.jalons.map((j) => j.ordre), [1, 2]);
      expect(controller.jalons.map((j) => j.description), [
        'Mi-parcours',
        'Livraison',
      ]);
    });
  });

  group('DevisController.loadDevis / loadMissionDevis', () {
    testWidgets('loadDevis charge le devis ciblé', (tester) async {
      adapter.on(
        'GET',
        '/devis/1',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 1,
              'mission_id': 10,
              'artisan_id': 2,
              'statut': 'soumis',
              'created_at': '2026-01-01T00:00:00Z',
              'lignes_json': [
                {'type': 'mo', 'description': 'Pose', 'montant': 20000},
              ],
              'jalons_json': [],
            },
          },
        ),
      );
      final controller = DevisController();

      await runControllerAction(tester, () => controller.loadDevis(1));

      expect(controller.devis.value?.id, 1);
      expect(controller.isLoading.value, isFalse);
    });

    testWidgets('loadMissionDevis charge tous les devis de la mission', (tester) async {
      adapter.on(
        'GET',
        '/missions/10/devis',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 1,
                'mission_id': 10,
                'artisan_id': 2,
                'statut': 'accepte',
                'created_at': '2026-01-01T00:00:00Z',
                'lignes_json': [],
                'jalons_json': [],
              },
            ],
          },
        ),
      );
      final controller = DevisController();

      await runControllerAction(tester, () => controller.loadMissionDevis(10));

      expect(controller.devoiList, hasLength(1));
      expect(controller.isLoading.value, isFalse);
    });

    test('loadDevis propage l\'erreur réseau (non interceptée)', () async {
      final controller = DevisController();

      await expectLater(controller.loadDevis(99), throwsA(anything));
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('DevisController.submitDevis', () {
    testWidgets('envoie le devis composé et confirme par snackbar', (tester) async {
      adapter.on(
        'POST',
        '/missions/10/devis',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 5,
              'mission_id': 10,
              'artisan_id': 2,
              'statut': 'soumis',
              'created_at': '2026-01-01T00:00:00Z',
              'lignes_json': [],
              'jalons_json': [],
            },
          },
        ),
      );
      final controller = DevisController()
        ..addLigne('mo', 'Pose carrelage', 50000)
        ..addJalon('Démarrage', 50000, '2026-02-01');

      await runControllerAction(tester, () => controller.submitDevis(10));

      expect(controller.isLoading.value, isFalse);
      expect(adapter.requests, hasLength(1));
      expect(
        (adapter.requests.single.data as Map)['lignes_json'],
        hasLength(1),
      );
    });
  });

  group('DevisController.refuseDevis', () {
    testWidgets('refuse le devis et revient à l\'écran précédent', (tester) async {
      adapter.on('POST', '/devis/7/refuse', const CannedResponse(statusCode: 200));
      final controller = DevisController();

      await runControllerAction(tester, () => controller.refuseDevis(7));

      expect(adapter.requests, hasLength(1));
      expect(adapter.requests.single.path, 'devis/7/refuse');
    });
  });

  group('DevisController.acceptDevis — paiement du séquestre', () {
    testWidgets('refuse sans numéro de téléphone enregistré', (tester) async {
      adapter.on(
        'GET',
        '/devis/1',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 1,
              'mission_id': 10,
              'artisan_id': 2,
              'statut': 'soumis',
              'created_at': '2026-01-01T00:00:00Z',
              'lignes_json': [
                {'type': 'mo', 'description': 'Pose', 'montant': 20000},
              ],
              'jalons_json': [],
            },
          },
        ),
      );
      final controller = DevisController();

      await runControllerAction(tester, () => controller.acceptDevis(1));

      expect(adapter.requests, hasLength(1));
      expect(controller.devis.value?.statut, 'soumis');
    });

    testWidgets('finance la mission dès la première confirmation de paiement', (tester) async {
      StorageService.savePhone('+2250700000001');
      adapter
        ..on(
          'GET',
          '/devis/1',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'id': 1,
                'mission_id': 10,
                'artisan_id': 2,
                'statut': 'soumis',
                'created_at': '2026-01-01T00:00:00Z',
                'lignes_json': [
                  {'type': 'mo', 'description': 'Pose', 'montant': 20000},
                ],
                'jalons_json': [],
              },
            },
          ),
        )
        ..on(
          'POST',
          '/payments/initiate',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {'transaction_id': 55, 'provider': 'wave'},
            },
          ),
        )
        ..on(
          'GET',
          '/payments/55/status',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'transaction_id': 55,
                'status': 'confirme',
                'montant': 20000,
                'provider': 'wave',
              },
            },
          ),
        )
        ..on(
          'POST',
          '/devis/1/accept',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'id': 1,
                'mission_id': 10,
                'artisan_id': 2,
                'statut': 'accepte',
                'created_at': '2026-01-01T00:00:00Z',
                'lignes_json': [],
                'jalons_json': [],
              },
            },
          ),
        );
      final controller = DevisController();

      await runControllerAction(tester, () => controller.acceptDevis(1));

      expect(controller.devis.value?.statut, 'accepte');
    });
  });
}
