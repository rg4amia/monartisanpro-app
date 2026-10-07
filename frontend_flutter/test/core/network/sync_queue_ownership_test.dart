import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:get/get.dart' hide Response;
import 'package:hive_flutter/hive_flutter.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// File hors connexion rattachée à un compte et chiffrée (Chantier 42).
///
/// Une requête en file ne portait pas son auteur : elle était rejouée avec le
/// jeton du compte connecté à ce moment-là, et son refus s'affichait à un
/// autre. Ses boîtes, en clair, gardaient les codes de retrait et de réception.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const validation = '/orders/7/verify-delivery';

  late FakeHttpClientAdapter adapter;
  int? userId;

  SyncService service() => SyncService(currentUserId: () => userId);

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    userId = null;
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.reset();
    await Hive.deleteFromDisk();
    await TestHelpers.cleanupTestData();
  });

  test('une action en file n\'est rejouée que par le compte qui l\'a faite',
      () async {
    final sync = service();
    await sync.openStorage();
    adapter.on(
      'POST',
      validation,
      const CannedResponse(statusCode: 200, body: {'success': true}),
    );

    userId = 1;
    await sync.enqueueRequest('POST', validation, data: {'code': '7390'});
    expect(sync.pendingCount.value, 1);

    // Un autre compte se connecte sur le même téléphone.
    userId = 2;
    await sync.flush();
    expect(adapter.requests, isEmpty);
    expect(sync.pendingCount.value, 0);

    // Personne n'est connecté : rien ne part non plus.
    userId = null;
    await sync.flush();
    expect(adapter.requests, isEmpty);

    // L'auteur revient : son action part enfin.
    userId = 1;
    await sync.flush();
    expect(adapter.requests, hasLength(1));
    expect(sync.pendingCount.value, 0);
  });

  test('un refus au rejeu n\'est annoncé qu\'à l\'auteur de l\'action',
      () async {
    final sync = service();
    await sync.openStorage();
    adapter.on(
      'POST',
      validation,
      const CannedResponse(
        statusCode: 400,
        body: {'message': 'Le code de réception de livraison est incorrect.'},
      ),
    );

    userId = 1;
    await sync.enqueueRequest('POST', validation, data: {'code': '0000'});
    await sync.flush();
    expect(sync.failures, hasLength(1));

    userId = 2;
    await sync.flush();
    expect(sync.failures, isEmpty);

    userId = 1;
    await sync.flush();
    expect(sync.failures.single.reason, contains('incorrect'));
  });

  test('à la fin de session rien n\'est montré, et l\'action attend son auteur',
      () async {
    final sync = service();
    await sync.openStorage();

    userId = 1;
    await sync.enqueueRequest('POST', validation, data: {'code': '7390'});

    userId = null;
    sync.onSessionEnded();
    expect(sync.pendingCount.value, 0);
    expect(sync.failures, isEmpty);

    userId = 1;
    await sync.flush();
    // Aucune réponse simulée : la panne réseau laisse l'action en file.
    expect(sync.pendingCount.value, 1);
  });

  test('la suppression d\'un compte retire ses actions en file', () async {
    final sync = service();
    await sync.openStorage();

    userId = 1;
    await sync.enqueueRequest('POST', validation, data: {'code': '7390'});
    userId = 2;
    await sync.enqueueRequest('POST', '/orders/9/verify-pickup', data: {});

    await sync.purgeAccount(1);

    userId = 1;
    await sync.flush();
    expect(sync.pendingCount.value, 0);
    expect(adapter.requests, isEmpty);

    // Les actions de l'autre compte ne sont pas touchées.
    userId = 2;
    await sync.flush();
    expect(sync.pendingCount.value, 1);
  });

  test('le code d\'une validation en file n\'est pas lisible sur le disque',
      () async {
    final sync = service();
    await sync.openStorage();

    userId = 1;
    await sync.enqueueRequest('POST', validation, data: {'code': '7390'});
    await Hive.close();

    final content =
        latin1.decode(File(sync.queueStoragePath!).readAsBytesSync());
    expect(content, isNot(contains('verify-delivery')));
    expect(content, isNot(contains('7390')));
  });

  group('boîtes en clair des versions précédentes', () {
    Future<void> seedLegacyQueue() async {
      await Hive.initFlutter();
      final legacy = await Hive.openBox<Map>('offline_sync_queue');
      await legacy.put(
        '1',
        QueuedRequest(
          id: '1',
          method: 'POST',
          url: validation,
          data: const {'code': '7390'},
          timestamp: DateTime.now(),
        ).toJson(),
      );
      await legacy.close();
    }

    test('reprises pour le compte connecté, puis supprimées', () async {
      await seedLegacyQueue();
      userId = 5;

      final sync = service();
      await sync.openStorage();

      expect(sync.pendingCount.value, 1);
      expect(await Hive.boxExists('offline_sync_queue'), isFalse);
    });

    test('abandonnées quand personne n\'est connecté', () async {
      await seedLegacyQueue();

      final sync = service();
      await sync.openStorage();
      expect(await Hive.boxExists('offline_sync_queue'), isFalse);

      userId = 5;
      await sync.flush();
      expect(sync.pendingCount.value, 0);
      expect(adapter.requests, isEmpty);
    });
  });

  test('une requête sans auteur n\'appartient à personne', () {
    expect(belongsToAccount(null, null), isFalse);
    expect(belongsToAccount(null, 3), isFalse);
    expect(belongsToAccount(3, null), isFalse);
    expect(belongsToAccount(3, 4), isFalse);
    expect(belongsToAccount(3, 3), isTrue);
  });
}
