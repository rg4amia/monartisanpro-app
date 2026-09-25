import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/repositories/auth_repository.dart';

import '../helpers/fake_http_adapter.dart';
import '../helpers/test_helpers.dart';

/// Le serveur peut valider un dossier KYC dès le téléversement (vérification
/// IA de la pièce et du selfie) : le dépôt doit remonter le statut renvoyé,
/// et tolérer une réponse qui ne le contient pas (Règle d'or 28).
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late FakeHttpClientAdapter adapter;
  late AuthRepository repository;
  late File image;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() async {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
    repository = AuthRepository();
    image = File('${Directory.systemTemp.createTempSync('kyc').path}/piece.jpg')
      ..writeAsBytesSync([0xFF, 0xD8, 0xFF, 0xD9]);
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  test('uploadSelfie renvoie le statut actif après validation automatique', () async {
    adapter.on(
      'POST',
      '/kyc/upload-selfie',
      const CannedResponse(
        statusCode: 200,
        body: {
          'success': true,
          'data': {'type': 'selfie', 'auto_verified': true, 'kyc_status': 'actif'},
        },
      ),
    );

    expect(await repository.uploadSelfie(image.path), 'actif');
  });

  test('uploadCni renvoie le statut en attente sans validation automatique', () async {
    adapter.on(
      'POST',
      '/kyc/upload-cni',
      const CannedResponse(
        statusCode: 200,
        body: {
          'success': true,
          'data': {'type': 'cni', 'ocr_data': [], 'kyc_status': 'en_attente'},
        },
      ),
    );

    expect(await repository.uploadCni(image.path), 'en_attente');
  });

  test('une réponse sans statut renvoie null sans lever', () async {
    adapter.on(
      'POST',
      '/kyc/upload-cni',
      const CannedResponse(statusCode: 200, body: {'success': true}),
    );

    expect(await repository.uploadCni(image.path), isNull);
  });
}
