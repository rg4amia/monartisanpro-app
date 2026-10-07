import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/cache/hive_cipher_provider.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/services/session_end.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:get/get.dart' hide Response;
import 'package:hive_flutter/hive_flutter.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Fin de session (Chantier 42).
///
/// Seule la déconnexion volontaire nettoyait le téléphone. Après une session
/// expirée (401) ou une suppression de compte, l'identité et les caches du
/// compte restaient en place pour le compte suivant.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    Get.reset();
    await Hive.deleteFromDisk();
    await TestHelpers.cleanupTestData();
  });

  Future<void> signIn() async {
    await StorageService.saveToken('jeton-du-compte');
    StorageService.saveUserId(7);
    StorageService.saveName('Awa Koné');
    StorageService.savePhone('+2250708091011');
    StorageService.saveRole('livreur');
    StorageService.saveKycStatus('actif');
    StorageService.saveDriverPlate('1234 AB 01');
  }

  /// Un cache ouvert (écran visité) et un cache resté sur le disque (écran non
  /// visité depuis l'ouverture de l'application).
  Future<Box<Map>> seedCaches() async {
    await Hive.initFlutter();
    final cipher = await HiveCipherProvider.cipher();

    final unvisited =
        await Hive.openBox<Map>('orders_cache', encryptionCipher: cipher);
    await unvisited.put('mes_commandes', {'id': 1});
    await unvisited.close();

    final open =
        await Hive.openBox<Map>('missions_cache', encryptionCipher: cipher);
    await open.put('all', {'id': 2});

    return open;
  }

  test('le nettoyage retire le jeton, l\'identité et tous les caches',
      () async {
    await signIn();
    final openCache = await seedCaches();

    await SessionEnd.wipeLocalData();

    expect(await StorageService.getToken(), isNull);
    expect(StorageService.getUserId(), isNull);
    expect(StorageService.getName(), isNull);
    expect(StorageService.getPhone(), isNull);
    expect(StorageService.getRole(), isNull);
    expect(StorageService.getKycStatus(), isNull);
    expect(StorageService.getDriverPlate(), isNull);

    expect(openCache.isEmpty, isTrue);
    expect(await Hive.boxExists('orders_cache'), isFalse);
  });

  test('les réglages de l\'appareil restent, dont son empreinte', () async {
    final fingerprint = StorageService.getDeviceFingerprint();
    StorageService.setOnboarded(true);
    StorageService.setDataSaverEnabled(false);
    await signIn();

    await SessionEnd.wipeLocalData();

    // Le serveur reconnaît les comptes multiples d'un même appareil à cette
    // empreinte : elle ne doit pas changer d'un compte à l'autre.
    expect(StorageService.getDeviceFingerprint(), fingerprint);
    expect(StorageService.isOnboarded(), isTrue);
    expect(StorageService.isDataSaverEnabled(), isFalse);
  });

  test('une session expirée (401) fait le même nettoyage', () async {
    await signIn();
    final openCache = await seedCaches();

    final adapter = FakeHttpClientAdapter()
      ..on(
        'GET',
        '/wallet/balance',
        const CannedResponse(
          statusCode: 401,
          body: {'message': 'Unauthenticated.'},
        ),
      );
    ApiClient().dio.httpClientAdapter = adapter;

    await expectLater(
      ApiClient().get('/wallet/balance'),
      throwsA(isA<DioException>()),
    );
    // Le nettoyage est lancé sans être attendu par la requête : on le rejoint.
    await SessionEnd.wipeLocalData();

    expect(await StorageService.getToken(), isNull);
    expect(StorageService.getUserId(), isNull);
    expect(StorageService.getName(), isNull);
    expect(openCache.isEmpty, isTrue);
    expect(await Hive.boxExists('orders_cache'), isFalse);
  });

  test('toute boîte de cache du code figure dans la liste vidée', () {
    final declared = <String>{};
    final sources = Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((f) => f.path.endsWith('.dart'));

    for (final file in sources) {
      final content = file.readAsStringSync();
      for (final pattern in [
        RegExp(r"boxName:\s*'(\w+)'"),
        RegExp(r"BoxName\s*=\s*'(\w+)'"),
      ]) {
        declared.addAll(pattern.allMatches(content).map((m) => m.group(1)!));
      }
    }

    // La file hors connexion n'est pas un cache : elle attend son auteur.
    declared.removeWhere((name) => name.startsWith('offline_sync_'));

    expect(declared, isNotEmpty);
    expect(
      declared.difference(CacheStore.knownBoxNames.toSet()),
      isEmpty,
      reason: 'Boîte de cache absente de CacheStore.knownBoxNames : elle '
          'resterait sur le téléphone à la fin de session.',
    );
  });
}
