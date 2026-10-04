import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/modules/auth/controllers/auth_controller.dart';
import 'package:frontend_flutter/modules/auth/views/kyc_rejection_notice.dart';

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
    await TestHelpers.cleanupTestData();
  });

  group('AuthController — motif du rejet KYC', () {
    test('lit le motif transmis par le serveur', () async {
      adapter.on(
        'GET',
        '/kyc/status',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {
              'kyc_status': 'rejete',
              'rejection_reason': 'Photo de la carte illisible.',
            },
          },
        ),
      );
      final controller = AuthController();

      await controller.loadKycRejectionReason();

      expect(controller.kycRejectionReason.value, 'Photo de la carte illisible.');
    });

    test('ne retient aucun motif pour un dossier qui n\'est pas rejeté', () async {
      adapter.on(
        'GET',
        '/kyc/status',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {'kyc_status': 'en_attente', 'rejection_reason': null},
          },
        ),
      );
      final controller = AuthController();

      await controller.loadKycRejectionReason();

      expect(controller.kycRejectionReason.value, isNull);
    });

    test('tolère une réponse incomplète ou une panne réseau', () async {
      final controller = AuthController();

      // Aucune route enregistrée : panne réseau simulée.
      await controller.loadKycRejectionReason();
      expect(controller.kycRejectionReason.value, isNull);

      adapter.on(
        'GET',
        '/kyc/status',
        const CannedResponse(statusCode: 200, body: {'success': true}),
      );
      await controller.loadKycRejectionReason();
      expect(controller.kycRejectionReason.value, isNull);
    });
  });

  group('KycRejectionNotice', () {
    testWidgets('affiche le motif saisi par l\'administrateur', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: KycRejectionNotice(reason: 'Photo de la carte illisible.'),
          ),
        ),
      );

      expect(find.text('Votre dossier a été rejeté'), findsOneWidget);
      expect(find.text('Motif : Photo de la carte illisible.'), findsOneWidget);
      expect(
        find.text('Envoyez de nouvelles pièces pour relancer la vérification.'),
        findsOneWidget,
      );
    });
  });
}
