import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:frontend_flutter/modules/orders/controllers/order_controller.dart';
import 'package:frontend_flutter/modules/orders/views/order_checkout_screen.dart';

/// N'appelle jamais le réseau : évite tout appel Dio réel pendant le test.
class _TestOrderController extends OrderController {
  @override
  void onInit() {
    // Ne charge pas les fournisseurs approuvés (appel réseau réel sinon).
  }
}

void main() {
  testWidgets(
    'le champ code promo et le bouton APPLIQUER sont bien présents',
    (tester) async {
      Get.testMode = true;
      Get.put<OrderController>(_TestOrderController());

      // `initState` déclenche un vrai appel Dio (_fetchDeliveryEstimate) :
      // on le laisse tourner sur la vraie boucle d'événements (voir Règle
      // d'Or 62 / test/helpers/getx_snackbar_harness.dart) plutôt que sous
      // l'horloge virtuelle de testWidgets, qui ne le résoudrait jamais.
      await tester.runAsync(() async {
        await tester.pumpWidget(
          const GetMaterialApp(home: OrderCheckoutScreen()),
        );
        await tester.pump();
      });

      expect(find.text('Avez-vous un code promo ?'), findsOneWidget);
      expect(find.text('APPLIQUER'), findsOneWidget,
          reason: 'Le bouton APPLIQUER devrait être visible.');
      expect(find.byType(TextField), findsWidgets,
          reason: 'Le champ de saisie du code promo devrait être visible.');
    },
  );
}
