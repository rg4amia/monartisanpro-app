import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/supplier_model.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/supplier_banner.dart';
import 'package:frontend_flutter/modules/orders/utils/supplier_grouping.dart';
import 'package:get/get.dart';

/// Fournisseurs agréés présentés par secteur d'activité (accueil client).
void main() {
  SupplierModel supplier(int id, String shop, [Map<String, dynamic>? sector]) =>
      SupplierModel.fromJson({
        'id': id,
        'name': 'Gérant $id',
        'shopName': shop,
        'activeProductsCount': 3,
        'sector': sector,
      });

  const plomberie = {'id': 2, 'name': 'Plomberie', 'icon': 'droplets'};
  const electricite = {'id': 1, 'name': 'Électricité', 'icon': 'zap'};

  test('lit le secteur et le métier de façon défensive', () {
    final model = SupplierModel.fromJson({
      'id': '4',
      'shopName': 'Eau Pro',
      'sector': {'id': '2', 'name': 'Plomberie', 'color': '#3498DB'},
      'trade': 'Plombier sanitaire',
    });

    expect(model.sector?.id, 2);
    expect(model.sector?.name, 'Plomberie');
    expect(model.sector?.color, '#3498DB');
    expect(model.trade, 'Plombier sanitaire');

    // Secteur absent, vide ou malformé : aucun secteur deviné.
    expect(SupplierModel.fromJson({'id': 1}).sector, isNull);
    expect(SupplierModel.fromJson({'id': 1, 'sector': []}).sector, isNull);
    expect(
      SupplierModel.fromJson({
        'id': 1,
        'sector': {'id': 3, 'name': ' '},
      }).sector,
      isNull,
    );
  });

  test(
      'regroupe par secteur trié par nom, les non classés en dernier sous « Autres fournisseurs »',
      () {
    final groups = groupSuppliersBySector([
      supplier(1, 'Zinc & Tubes', plomberie),
      supplier(2, 'Divers Bâtiment'),
      supplier(3, 'Aqua Services', plomberie),
      supplier(4, 'Lumière Plus', electricite),
    ]);

    expect(groups.map((g) => g.label).toList(), [
      'Électricité',
      'Plomberie',
      kUnclassifiedSectorLabel,
    ]);
    expect(
      groups[1].suppliers.map((s) => s.shopName).toList(),
      ['Aqua Services', 'Zinc & Tubes'],
    );
    expect(groups.last.sector, isNull);
    expect(groups.last.suppliers.single.id, 2);
  });

  test('sans fournisseur non classé, pas de rubrique « Autres fournisseurs »',
      () {
    final groups = groupSuppliersBySector([supplier(1, 'Aqua', plomberie)]);

    expect(groups.map((g) => g.label), ['Plomberie']);
  });

  testWidgets(
      'la bannière de l\'accueil client annonce les fournisseurs agréés',
      (tester) async {
    await tester.pumpWidget(
      const GetMaterialApp(home: Scaffold(body: SupplierBanner())),
    );

    expect(find.text('Fournisseurs Agréés'), findsOneWidget);
    expect(find.textContaining('Quincailleries'), findsNothing);
  });
}
