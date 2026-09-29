import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/supplier_model.dart';
import 'package:frontend_flutter/data/models/supplier_product_model.dart';
import 'package:frontend_flutter/modules/addresses/controllers/address_controller.dart';
import 'package:frontend_flutter/modules/orders/controllers/order_controller.dart';
import 'package:frontend_flutter/modules/orders/views/order_checkout_screen.dart';
import 'package:get/get.dart';

import '../../helpers/test_helpers.dart';

/// Commande de matériaux à un ou plusieurs fournisseurs : chaque article part
/// vers le fournisseur qui le vend, jamais vers celui affiché à l'écran, et
/// le récapitulatif montre ce qui est réellement encaissé.
class _RecordingOrderController extends OrderController {
  Map<String, dynamic>? singleOrder;
  List<Map<String, dynamic>>? multiPackages;

  @override
  Future<void> loadApprovedSuppliers({String? search}) async {}

  @override
  Future<void> loadSupplierProducts(int supplierId) async {}

  @override
  Future<bool> createOrder({
    required int supplierId,
    required String deliveryMode,
    required List<Map<String, dynamic>> items,
    String? vehicleClass,
    String? promoCode,
    int? addressId,
    String paymentProvider = 'wave',
  }) async {
    singleOrder = {
      'supplier_id': supplierId,
      'delivery_mode': deliveryMode,
      'items': items,
    };
    return false; // pas de modale de succès dans le test
  }

  @override
  Future<bool> createMultiOrders({
    required List<Map<String, dynamic>> packages,
    String? promoCode,
    int? addressId,
    String paymentProvider = 'wave',
  }) async {
    multiPackages = packages;
    return false;
  }
}

class _TestAddressController extends AddressController {
  @override
  Future<void> loadAddresses() async {}
}

SupplierModel _supplier(int id, String shop) => SupplierModel(
      id: id,
      name: 'Gérant $id',
      phone: '',
      shopName: shop,
      activeProductsCount: 1,
    );

SupplierProductModel _product(int id, int supplierId, String name, int price) =>
    SupplierProductModel(
      id: id,
      supplierId: supplierId,
      name: name,
      unitPrice: price,
      stockQuantity: 50,
      isActive: true,
    );

void main() {
  late _RecordingOrderController controller;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    Get.testMode = true;
    controller = Get.put<OrderController>(_RecordingOrderController())
        as _RecordingOrderController;
    Get.put<AddressController>(_TestAddressController());
    controller.approvedSuppliers.assignAll([
      _supplier(10, 'Quincaillerie Nord'),
      _supplier(20, 'Électro Sud'),
    ]);
  });

  tearDown(Get.reset);

  Future<void> openCheckout(WidgetTester tester) async {
    await tester.runAsync(() async {
      await tester
          .pumpWidget(const GetMaterialApp(home: OrderCheckoutScreen()));
      await tester.pump();
    });
  }

  Future<void> confirmInPickupMode(WidgetTester tester) async {
    await tester.tap(find.text('Retrait magasin'));
    await tester.pump();
    final confirm = find.text('Confirmer la commande');
    await tester.ensureVisible(confirm);
    await tester.tap(confirm);
    await tester.runAsync(() => Future<void>.delayed(Duration.zero));
    await tester.pump();
  }

  test(
      'le panier se découpe par fournisseur, quel que soit le catalogue affiché',
      () {
    controller.addToCart(_product(1, 10, 'Ciment', 5000));
    controller.addToCart(_product(1, 10, 'Ciment', 5000));
    controller.addToCart(_product(2, 20, 'Câble', 3000));
    controller.selectedSupplier.value = _supplier(20, 'Électro Sud');

    final groups = controller.cartGroups;
    expect(
      groups.map((g) => g.shopName),
      ['Quincaillerie Nord', 'Électro Sud'],
    );
    expect(groups.first.subtotal, 10000);
    expect(groups.first.itemsPayload, [
      {'supplier_product_id': 1, 'quantity': 2},
    ]);
    expect(controller.cartSummaryLabel, '3 articles chez 2 fournisseurs');
  });

  testWidgets(
      'la commande part vers le fournisseur des articles, pas vers celui affiché',
      (tester) async {
    // Articles pris chez Nord, puis le client consulte le catalogue de Sud.
    controller.addToCart(_product(1, 10, 'Ciment', 5000));
    controller.selectedSupplier.value = _supplier(20, 'Électro Sud');

    await openCheckout(tester);
    expect(
      find.text('Expédition 1/1 — Vendu par Quincaillerie Nord'),
      findsOneWidget,
    );

    await confirmInPickupMode(tester);

    expect(controller.singleOrder?['supplier_id'], 10);
    expect(controller.singleOrder?['items'], [
      {'supplier_product_id': 1, 'quantity': 1},
    ]);
    expect(controller.multiPackages, isNull);
  });

  testWidgets(
      'un panier multi-fournisseurs montre chaque expédition et crée une commande par fournisseur',
      (tester) async {
    controller.addToCart(_product(1, 10, 'Ciment', 5000));
    controller.addToCart(_product(2, 20, 'Câble', 3000));

    await openCheckout(tester);

    expect(
      find.text('Expédition 1/2 — Vendu par Quincaillerie Nord'),
      findsOneWidget,
    );
    expect(find.text('Expédition 2/2 — Vendu par Électro Sud'), findsOneWidget);
    // Les articles des deux fournisseurs figurent dans le récapitulatif.
    expect(find.text('Ciment (x1)'), findsOneWidget);
    expect(find.text('Câble (x1)'), findsOneWidget);
    expect(
      find.textContaining('2 courses (une par fournisseur)'),
      findsOneWidget,
    );

    await confirmInPickupMode(tester);

    expect(controller.singleOrder, isNull);
    expect(controller.multiPackages?.map((p) => p['supplier_id']), [10, 20]);
  });

  testWidgets(
      'le montant à payer exclut la course et le client ne règle aucune majoration',
      (tester) async {
    controller.addToCart(_product(1, 10, 'Ciment', 10000));

    await openCheckout(tester);

    // 10 000 d'articles + 3 % de frais de service = 10 300 FCFA encaissés.
    expect(find.text('À payer maintenant'), findsOneWidget);
    expect(find.text('10 300 FCFA'), findsOneWidget);
    expect(find.textContaining('réglée à la livraison'), findsOneWidget);
    expect(find.byType(Slider), findsNothing);
    expect(find.textContaining('Majoration'), findsNothing);
  });
}
