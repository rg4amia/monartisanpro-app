import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/services/otp_sms_listener.dart';
import 'package:frontend_flutter/modules/auth/controllers/auth_controller.dart';
import 'package:frontend_flutter/modules/auth/views/otp_verification_screen.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Écouteur piloté par le test : le SMS « arrive » quand le test le décide.
class FakeOtpSmsListener implements OtpSmsListener {
  final List<Completer<String?>> waits = [];
  int cancelCount = 0;

  @override
  Future<String?> waitForCode() {
    final completer = Completer<String?>();
    waits.add(completer);
    return completer.future;
  }

  @override
  Future<void> cancel() async => cancelCount++;

  void receive(String? code) => waits.last.complete(code);
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;
  late FakeOtpSmsListener sms;
  late AuthController controller;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
    sms = FakeOtpSmsListener();
    controller = Get.put(AuthController(smsListener: sms))
      ..phone.value = '+2250700000001';
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
  });

  Future<void> pumpScreen(WidgetTester tester) async {
    await tester.pumpWidget(
      const GetMaterialApp(home: OtpVerificationScreen()),
    );
    // Prise de focus différée de la première case.
    await tester.pump(const Duration(milliseconds: 400));
  }

  List<String> boxes(WidgetTester tester) => tester
      .widgetList<TextFormField>(find.byType(TextFormField))
      .map((field) => field.controller!.text)
      .toList();

  ElevatedButton verifyButton(WidgetTester tester) =>
      tester.widget<ElevatedButton>(
        find.widgetWithText(ElevatedButton, 'Vérifier le code'),
      );

  const autoFilledNote =
      'Code rempli automatiquement. Appuyez sur « Vérifier le code ».';

  testWidgets('le code du SMS remplit les 4 cases sans lancer la vérification',
      (tester) async {
    controller.listenForOtpSms();
    await pumpScreen(tester);
    expect(verifyButton(tester).onPressed, isNull);

    sms.receive('4821');
    await tester.pump();

    expect(boxes(tester), ['4', '8', '2', '1']);
    expect(controller.otp.value, '4821');
    expect(find.text(autoFilledNote), findsOneWidget);
    expect(verifyButton(tester).onPressed, isNotNull);
    // La vérification attend l'appui de l'utilisateur.
    expect(adapter.requests, isEmpty);
  });

  testWidgets('un code arrivé avant l\'ouverture de l\'écran est reporté',
      (tester) async {
    controller.listenForOtpSms();
    sms.receive('3310');
    await tester.pump();

    await pumpScreen(tester);

    expect(boxes(tester), ['3', '3', '1', '0']);
    expect(verifyButton(tester).onPressed, isNotNull);
    expect(adapter.requests, isEmpty);
  });

  testWidgets('sans code lu, l\'écran reste en saisie manuelle',
      (tester) async {
    controller.listenForOtpSms();
    await pumpScreen(tester);

    sms.receive(null);
    await tester.pump();

    expect(boxes(tester), ['', '', '', '']);
    expect(find.text(autoFilledNote), findsNothing);
    expect(verifyButton(tester).onPressed, isNull);
    expect(controller.errorMsg.value, isNull);
  });

  testWidgets('un SMS attendu par une écoute arrêtée ne remplit rien',
      (tester) async {
    controller.listenForOtpSms();
    await pumpScreen(tester);

    controller.stopListeningForOtpSms();
    sms.receive('4821');
    await tester.pump();

    expect(boxes(tester), ['', '', '', '']);
    expect(sms.cancelCount, 1);
  });

  testWidgets('un code collé dans une case se répartit sur les 4',
      (tester) async {
    await pumpScreen(tester);

    await tester.enterText(find.byType(TextFormField).first, '1234');
    await tester.pump();

    expect(boxes(tester), ['1', '2', '3', '4']);
    expect(controller.otp.value, '1234');
    expect(verifyButton(tester).onPressed, isNotNull);
    expect(find.text(autoFilledNote), findsNothing);
  });

  testWidgets('modifier une case efface la mention de remplissage automatique',
      (tester) async {
    controller.listenForOtpSms();
    await pumpScreen(tester);
    sms.receive('4821');
    await tester.pump();

    await tester.enterText(find.byType(TextFormField).last, '');
    await tester.pump();

    expect(find.text(autoFilledNote), findsNothing);
    expect(controller.otp.value, '482');
    expect(verifyButton(tester).onPressed, isNull);
  });

  testWidgets('le retour arrière arrête l\'écoute du SMS', (tester) async {
    controller.listenForOtpSms();
    await pumpScreen(tester);

    await tester.tap(find.byIcon(Icons.arrow_back));
    await tester.pump();

    expect(sms.cancelCount, 1);
  });
}
