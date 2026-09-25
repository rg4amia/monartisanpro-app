import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/auth/views/kyc_verified_dialog.dart';

void main() {
  group('KycVerificationInProgress', () {
    testWidgets("s'affiche pendant l'envoi du selfie", (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: KycVerificationInProgress(visible: true)),
        ),
      );

      expect(
        find.text('Vérification de votre identité en cours…'),
        findsOneWidget,
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });

    testWidgets('reste masqué hors envoi', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: KycVerificationInProgress(visible: false)),
        ),
      );

      expect(
        find.text('Vérification de votre identité en cours…'),
        findsNothing,
      );
    });
  });

  group('KycVerifiedDialog', () {
    testWidgets('félicite et mène à la plateforme', (tester) async {
      var continued = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: KycVerifiedDialog(onContinue: () => continued = true),
          ),
        ),
      );

      expect(
        find.text('Félicitations, votre compte est validé !'),
        findsOneWidget,
      );

      await tester.tap(find.text('Accéder à la plateforme'));
      expect(continued, isTrue);
    });
  });
}
