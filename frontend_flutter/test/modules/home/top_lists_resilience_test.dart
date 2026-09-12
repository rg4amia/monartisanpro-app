import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/home/controllers/home_controller.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/top_drivers_section.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/top_suppliers_section.dart';

/// Les classements du tableau de bord client sont alimentés par du JSON.
///
/// `TopDriversSection` transtypait `driver['vehicle']` en `String` alors que
/// l'API ne produisait pas cette clé : la construction levait
/// « type 'Null' is not a subtype of type 'String' » et Flutter remplaçait
/// toute la section par une zone grise.
///
/// Le défaut est resté invisible des mois durant parce qu'une exception
/// antérieure laissait le classement vide : aucune carte n'était construite,
/// donc aucune clé n'était lue. Corriger cette exception l'a révélé — d'où ce
/// test, qui éprouve le rendu avec des charges utiles incomplètes plutôt que
/// la seule charge utile nominale.
void main() {
  Future<void> pump(WidgetTester tester, Widget child) => tester.pumpWidget(
        MaterialApp(home: Scaffold(body: SingleChildScrollView(child: child))),
      );

  group('TopDriversSection', () {
    testWidgets('se construit sans la cle vehicle', (tester) async {
      final controller = HomeController()
        ..topDrivers.value = [
          {'name': 'Koffi Livraison', 'rating': 4.8, 'trips': 12},
        ];

      await pump(tester, TopDriversSection(controller: controller));

      expect(tester.takeException(), isNull);
      expect(find.text('Koffi Livraison'), findsOneWidget);
      expect(find.textContaining('12 livraisons'), findsOneWidget);
    });

    testWidgets('affiche le vehicule quand il est fourni', (tester) async {
      final controller = HomeController()
        ..topDrivers.value = [
          {
            'name': 'Koffi Livraison',
            'rating': 4.8,
            'trips': 12,
            'vehicle': 'Cargo',
          },
        ];

      await pump(tester, TopDriversSection(controller: controller));

      expect(tester.takeException(), isNull);
      expect(find.text('Cargo'), findsOneWidget);
    });

    testWidgets('survit a une ligne entierement vide', (tester) async {
      // Un backend plus ancien, ou une réponse tronquée, ne doit pas faire
      // disparaître la section entière.
      final controller = HomeController()..topDrivers.value = [{}];

      await pump(tester, TopDriversSection(controller: controller));

      expect(tester.takeException(), isNull);
    });
  });

  group('TopSuppliersSection', () {
    testWidgets('survit a un nom absent', (tester) async {
      final controller = HomeController()
        ..topSuppliers.value = [
          {'rating': 4.5, 'deliveries': 30},
        ];

      await pump(tester, TopSuppliersSection(controller: controller));

      expect(tester.takeException(), isNull);
      expect(find.text('Quincaillerie'), findsOneWidget);
    });
  });
}
