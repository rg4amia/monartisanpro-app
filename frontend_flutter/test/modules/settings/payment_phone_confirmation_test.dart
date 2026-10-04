import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/core/utils/phone_format.dart';
import 'package:frontend_flutter/modules/auth/widgets/login/login_account_dialogs.dart';
import 'package:frontend_flutter/modules/settings/controllers/settings_controller.dart';

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

  /// Contrôleur dans l'état d'un compte chargé, sans appel réseau.
  SettingsController controllerFor({
    required String role,
    String accountPhone = '+2250700000001',
    String paymentPhone = '+2250700000001',
  }) {
    return SettingsController()
      ..userRole.value = role
      ..userPhone.value = accountPhone
      ..paymentPhone.value = paymentPhone;
  }

  group('normalizeIvorianPhone', () {
    test('ramène deux saisies du même numéro à la même forme', () {
      expect(normalizeIvorianPhone('0701020304'), '+2250701020304');
      expect(normalizeIvorianPhone('+225 07 01 02 03 04'), '+2250701020304');
      expect(normalizeIvorianPhone(''), '');
      expect(normalizeIvorianPhone(null), '');
    });
  });

  group('SettingsController — changement du numéro de paiement', () {
    test('un compte qui reçoit des gains confirme un nouveau numéro par code',
        () {
      final controller = controllerFor(role: 'artisan');

      expect(controller.paymentPhoneNeedsCode('0500000002'), isTrue);
    });

    test('garder le même numéro ou revenir à celui du compte ne demande rien',
        () {
      final controller = controllerFor(
        role: 'livreur',
        paymentPhone: '+2250500000002',
      );

      expect(controller.paymentPhoneNeedsCode('0500000002'), isFalse);
      expect(controller.paymentPhoneNeedsCode('0700000001'), isFalse);
    });

    test('un client change son moyen de paiement sans code', () {
      final controller = controllerFor(role: 'client');

      expect(controller.paymentPhoneNeedsCode('0500000002'), isFalse);
    });

    test('la demande de code part vers la route dédiée', () async {
      adapter.on(
        'POST',
        '/users/payment-phone/code',
        const CannedResponse(statusCode: 200, body: {'success': true}),
      );
      final controller = controllerFor(role: 'artisan');

      final sent = await controller.requestPaymentPhoneCode();

      expect(sent, isTrue);
      expect(controller.paymentPhoneCodeSent.value, isTrue);
      expect(controller.paymentPhoneError.value, isNull);
    });

    test('un échec de la demande de code est annoncé', () async {
      final controller = controllerFor(role: 'artisan');

      // Aucune route enregistrée : panne réseau simulée.
      final sent = await controller.requestPaymentPhoneCode();

      expect(sent, isFalse);
      expect(controller.paymentPhoneCodeSent.value, isFalse);
      expect(controller.paymentPhoneError.value, isNotNull);
    });

    test('le code saisi accompagne le nouveau numéro', () async {
      StorageService.saveUserId(7);
      adapter.on(
        'PUT',
        '/users/7',
        const CannedResponse(statusCode: 200, body: {'success': true}),
      );
      final controller = controllerFor(role: 'artisan')
        ..paymentPhoneCodeSent.value = true;

      final saved = await controller.updatePaymentPhone(
        newPaymentPhone: '0500000002',
        provider: 'wave',
        code: '4821',
      );

      expect(saved, isTrue);
      expect(controller.paymentPhone.value, '0500000002');
      expect(controller.paymentPhoneCodeSent.value, isFalse);

      final request =
          adapter.requests.lastWhere((r) => r.path.contains('users/7'));
      final body = request.data is String
          ? jsonDecode(request.data as String) as Map<String, dynamic>
          : Map<String, dynamic>.from(request.data as Map);
      expect(body['payment_phone'], '0500000002');
      expect(body['payment_phone_code'], '4821');
    });
  });

  group('RecoverAccountDialog', () {
    testWidgets('renvoie vers le support, sans formulaire de récupération',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: RecoverAccountDialog())),
      );

      expect(find.text('Récupération de compte'), findsOneWidget);
      expect(find.text('Contacter le support'), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      expect(find.byType(DropdownButtonFormField<String>), findsNothing);
    });
  });
}
