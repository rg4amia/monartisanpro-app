import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/services/push_identity.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/repositories/auth_repository.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Chantier 14, lot A : aucune fin de session ne détachait l'appareil de
/// OneSignal, si bien qu'un téléphone partagé continuait de recevoir les
/// notifications (paiements, litiges) du compte précédent.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('OneSignal');
  late List<MethodCall> calls;
  late FakeHttpClientAdapter adapter;

  void recordOneSignalCalls({bool fail = false}) {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      if (fail) throw PlatformException(code: 'indisponible');
      return null;
    });
  }

  bool unlinked() => calls.any((c) => c.method == 'OneSignal#logout');

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() async {
    calls = [];
    recordOneSignalCalls();
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
    await StorageService.saveToken('jeton-de-test');
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  test('la déconnexion détache l\'appareil du compte', () async {
    adapter.on(
      'POST',
      '/auth/logout',
      const CannedResponse(statusCode: 200, body: {'success': true}),
    );

    await AuthRepository().logout();

    expect(unlinked(), isTrue);
    expect(await StorageService.getToken(), isNull);
  });

  test('la déconnexion détache l\'appareil même si le serveur est injoignable',
      () async {
    // Aucune réponse simulée : l'appel réseau échoue.
    await AuthRepository().logout();

    expect(unlinked(), isTrue);
  });

  test('une session révoquée (401) détache l\'appareil', () async {
    adapter.on(
      'GET',
      '/auth/me',
      const CannedResponse(statusCode: 401, body: {'message': 'Unauthenticated.'}),
    );

    await expectLater(AuthRepository().me(), throwsA(anything));
    await Future<void>.delayed(Duration.zero);

    expect(unlinked(), isTrue);
  });

  test('une panne du SDK push ne fait pas échouer la déconnexion', () async {
    recordOneSignalCalls(fail: true);

    await expectLater(PushIdentity.unlink(), completes);
    await expectLater(AuthRepository().logout(), completes);
    expect(await StorageService.getToken(), isNull);
  });

  test('la connexion associe l\'appareil à l\'identifiant du compte', () async {
    await PushIdentity.link(42);

    final login = calls.singleWhere((c) => c.method == 'OneSignal#login');
    expect((login.arguments as Map)['externalId'], '42');
  });
}
