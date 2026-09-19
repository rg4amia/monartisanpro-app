import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/app/routes/app_routes.dart';
import 'package:frontend_flutter/modules/addresses/controllers/address_controller.dart';
import 'package:frontend_flutter/modules/orders/controllers/order_controller.dart';
import 'package:frontend_flutter/modules/orders/views/client_catalog_screen.dart';
import 'package:frontend_flutter/modules/orders/views/order_checkout_screen.dart';
import 'package:get/get.dart';

import '../../helpers/test_helpers.dart';

/// N'appelle jamais le réseau : évite tout appel Dio réel pendant le test.
class _TestOrderController extends OrderController {
  @override
  Future<void> loadApprovedSuppliers({String? search}) async {}
}

/// Le carnet d'adresses est chargé au montage de l'écran de checkout : on
/// neutralise l'appel réseau réel, comme dans order_checkout_promo_field_test.
class _TestAddressController extends AddressController {
  @override
  Future<void> loadAddresses() async {}
}

class _TestAddressBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<AddressController>(() => _TestAddressController());
  }
}

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  testWidgets(
    'PASSER LA COMMANDE depuis le catalogue ouvre bien l\'écran de commande '
    '(régression : Get.to() sautait le binding du carnet d\'adresses)',
    (tester) async {
      Get.testMode = true;
      final orderController = Get.put<OrderController>(_TestOrderController());
      // Simule un panier non vide pour faire apparaître la barre d'action.
      orderController.cart[1] = 2;

      await tester.runAsync(() async {
        await tester.pumpWidget(
          GetMaterialApp(
            initialRoute: Routes.clientCatalog,
            getPages: [
              GetPage(
                name: Routes.clientCatalog,
                page: () => const ClientCatalogScreen(),
              ),
              // Reproduit exactement l'enregistrement de production : la
              // route nommée porte le binding du carnet d'adresses, que
              // seule une navigation par `Get.toNamed` déclenche.
              GetPage(
                name: Routes.orderCheckout,
                page: () => const OrderCheckoutScreen(),
                binding: _TestAddressBinding(),
              ),
            ],
          ),
        );
        await tester.pump();

        expect(find.text('PASSER LA COMMANDE'), findsOneWidget);

        // Pas de `pumpAndSettle` : l'écran de commande déclenche un vrai
        // appel Dio (`_fetchDeliveryEstimate`, non attendu par le widget
        // lui-même — cf. Règle d'Or 62) qui ne se résout jamais dans cet
        // environnement de test et ferait dépasser son délai d'attente.
        await tester.tap(find.text('PASSER LA COMMANDE'));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 400));
      });

      // Avant correction, `Get.find<AddressController>()` dans
      // OrderCheckoutScreen levait une exception ici : la navigation par
      // `Get.to()` ne déclenchait jamais le binding attaché à la route
      // nommée, donc le carnet d'adresses n'était jamais enregistré et
      // l'écran de commande — qui affiche pourtant le contenu du panier —
      // ne s'ouvrait plus du tout.
      expect(tester.takeException(), isNull);
      expect(find.text('Passer votre commande'), findsOneWidget);
      expect(Get.isRegistered<AddressController>(), isTrue);
    },
  );
}
