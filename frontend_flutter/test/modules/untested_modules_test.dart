import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/models/chat_message_model.dart';
import 'package:frontend_flutter/data/repositories/artisan_repository.dart';
import 'package:frontend_flutter/data/repositories/chat_repository.dart';
import 'package:frontend_flutter/modules/artisans/controllers/parrainage_controller.dart';
import 'package:frontend_flutter/modules/clients/parrainage/controllers/parrainage_client_controller.dart';
import 'package:frontend_flutter/modules/main_tab/controllers/main_tab_controller.dart';
import 'package:frontend_flutter/modules/stock/controllers/stock_controller.dart';
import 'package:get/get.dart' hide Response;

import '../helpers/fake_http_adapter.dart';
import '../helpers/getx_snackbar_harness.dart';
import '../helpers/test_helpers.dart';

/// Modules qui n'avaient aucun test : discussion de chantier, artisans,
/// parrainage client, onglets, stock (audit du 07/10/2026, anomalie 12 bis).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;

  const serverError = CannedResponse(
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

  group('discussion de chantier', () {
    test('les messages se lisent, y compris incomplets', () async {
      adapter.on(
        'GET',
        '/missions/12/messages',
        const CannedResponse(
          statusCode: 200,
          body: {
            'is_funded': 1,
            'chat_mode': 'limited',
            'data': [
              {
                'id': '41',
                'mission_id': 12,
                'sender_id': 3,
                'sender': {'name': 'Awa', 'role': 'client'},
                'type': 'text',
                'content': 'Bonjour',
                'created_at': '2026-10-07T09:00:00Z',
              },
              // Message sans expéditeur ni date : ne doit pas tout faire échouer.
              {'id': 42, 'type': 'image', 'media_url': '/media/prive/a.jpg'},
            ],
          },
        ),
      );

      final result = await ChatRepository().fetchMessages(12);
      final messages = result['messages'] as List<ChatMessageModel>;

      expect(result['is_funded'], isTrue);
      expect(result['chat_mode'], 'limited');
      expect(messages, hasLength(2));
      expect(messages.first.id, 41);
      expect(messages.first.senderName, 'Awa');
      expect(messages.last.isImage, isTrue);
    });

    test('un message dont les coordonnées ont été masquées le signale',
        () async {
      adapter.on(
        'POST',
        '/missions/12/messages',
        const CannedResponse(
          statusCode: 201,
          body: {
            'data': {
              'id': 43,
              'mission_id': 12,
              'sender_id': 3,
              'type': 'text',
              'content': 'Appelez-moi au ***',
              'is_redacted': true,
            },
          },
        ),
      );

      final sent = await ChatRepository().sendMessage(
        missionId: 12,
        content: 'Appelez-moi au 0708091011',
      );

      expect(sent.isRedacted, isTrue);
      expect((adapter.requests.single.data as Map)['type'], 'text');
    });

    test('une réponse sans message est une erreur, pas un message vide',
        () async {
      adapter.on(
        'POST',
        '/missions/12/messages',
        const CannedResponse(statusCode: 201, body: {'success': true}),
      );

      await expectLater(
        ChatRepository().sendMessage(missionId: 12, content: 'Bonjour'),
        throwsFormatException,
      );
    });

    test('une panne de chargement remonte', () async {
      adapter.on('GET', '/missions/12/messages', serverError);

      await expectLater(
        ChatRepository().fetchMessages(12),
        throwsA(isA<DioException>()),
      );
    });
  });

  group('artisans', () {
    test('le détail du score se lit tel que le serveur le rend', () async {
      adapter.on(
        'GET',
        '/artisans/8/score',
        const CannedResponse(
          statusCode: 200,
          body: {'score': 640, 'distinct_clients': 6},
        ),
      );

      final score = await ArtisanRepository().getScore(8);

      expect(score['score'], 640);
      expect(score['distinct_clients'], 6);
    });

    test('une réponse de score illisible est une erreur', () async {
      adapter.on(
        'GET',
        '/artisans/8/score',
        const CannedResponse(statusCode: 200, body: []),
      );

      await expectLater(ArtisanRepository().getScore(8), throwsFormatException);
    });

    test('la liste des filleuls se charge', () async {
      adapter.on(
        'GET',
        '/parrainages',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {'id': 1, 'filleul_nom': 'Koffi', 'statut': 'en_attente'},
            ],
          },
        ),
      );

      final controller = ParrainageController();
      await controller.loadFilleuls();

      expect(controller.filleuls.single['filleul_nom'], 'Koffi');
      expect(controller.errorMsg.value, isNull);
      expect(controller.isLoading.value, isFalse);
    });

    test('une panne est annoncée, pas affichée comme « aucun filleul »',
        () async {
      adapter.on('GET', '/parrainages', serverError);

      final controller = ParrainageController();
      await controller.loadFilleuls();

      expect(controller.errorMsg.value, 'Erreur du serveur.');
    });
  });

  group('parrainage client', () {
    test('la liste se charge', () async {
      adapter.on(
        'GET',
        '/parrainages-clients',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {'id': 5, 'filleul_nom': 'Mariam'},
            ],
          },
        ),
      );

      final controller = ParrainageClientController();
      await controller.loadFilleuls();

      expect(controller.filleuls.single['filleul_nom'], 'Mariam');
    });

    testWidgets('un refus du serveur rend son message', (tester) async {
      adapter
        ..on(
          'GET',
          '/parrainages-clients',
          const CannedResponse(statusCode: 200, body: {'data': []}),
        )
        ..on(
          'POST',
          '/parrainages-clients',
          const CannedResponse(
            statusCode: 422,
            body: {
              'message': 'Données invalides.',
              'errors': {
                'filleul_phone': ['Ce numéro est déjà parrainé.'],
              },
            },
          ),
        );

      late bool added;
      late ParrainageClientController controller;
      await runControllerAction(tester, () async {
        controller = ParrainageClientController();
        added = await controller.addFilleul('+2250708091011', 'Mariam');
      });

      expect(added, isFalse);
      expect(controller.errorMsg.value, 'Ce numéro est déjà parrainé.');
    });
  });

  group('onglets', () {
    test('le rôle vient du compte connecté', () {
      StorageService.saveRole('livreur');

      final controller = MainTabController()..onInit();

      expect(controller.isDriver, isTrue);
      expect(controller.isClient, isFalse);
    });

    test('changer d\'espace revient au premier onglet et se mémorise', () {
      StorageService.saveRole('client');
      final controller = MainTabController()
        ..onInit()
        ..changeTab(3);

      controller.switchSpace('artisan');

      expect(controller.currentIndex.value, 0);
      expect(controller.isArtisan, isTrue);
      expect(StorageService.getRole(), 'artisan');
    });
  });

  group('stock de l\'artisan', () {
    const item = {
      'id': 4,
      'artisan_id': 8,
      'description': 'Sac de ciment',
      'quantity': '3',
      'unit_cost': 5500,
      'condition': 'neuf',
    };

    test('le stock se charge', () async {
      adapter.on(
        'GET',
        '/artisan-stock',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [item],
          },
        ),
      );

      final controller = StockController();
      await controller.loadStock();

      expect(controller.stockItems.single.description, 'Sac de ciment');
      expect(controller.stockItems.single.quantity, 3);
      expect(controller.hasError.value, isFalse);
    });

    testWidgets('une panne est signalée, la liste n\'est pas dite vide',
        (tester) async {
      adapter.on('GET', '/artisan-stock', serverError);

      final controller = StockController();
      await runControllerAction(tester, controller.loadStock);

      expect(controller.hasError.value, isTrue);
      expect(controller.isLoading.value, isFalse);
    });

    testWidgets('un article supprimé quitte la liste', (tester) async {
      adapter
        ..on(
          'GET',
          '/artisan-stock',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': [item],
            },
          ),
        )
        ..on(
          'DELETE',
          '/artisan-stock/4',
          const CannedResponse(statusCode: 200, body: {'success': true}),
        );

      final controller = StockController();
      late bool deleted;
      await runControllerAction(tester, () async {
        await controller.loadStock();
        deleted = await controller.deleteItem(controller.stockItems.single);
      });

      expect(deleted, isTrue);
      expect(controller.stockItems, isEmpty);
    });
  });
}
