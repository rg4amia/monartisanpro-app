import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/action_tile.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/hero_metric.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/meta_text.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/mission_action.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/stat_card.dart';
import 'package:frontend_flutter/modules/home/widgets/artisan_home/status_pill.dart';

Future<void> _pump(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(home: Scaffold(body: Center(child: child))),
  );
}

void main() {
  group('Widgets extraits de ArtisanHomeScreen', () {
    testWidgets('HeroMetric affiche libellé et valeur', (tester) async {
      await _pump(
        tester,
        const HeroMetric(label: 'Gains disponibles ›', value: '12 000 FCFA'),
      );

      expect(find.text('Gains disponibles ›'), findsOneWidget);
      expect(find.text('12 000 FCFA'), findsOneWidget);
    });

    testWidgets('StatCard affiche label, valeur, sous-titre et icône',
        (tester) async {
      await _pump(
        tester,
        const StatCard(
          label: 'A deviser',
          value: '3',
          subtitle: 'Demandes en attente',
          color: Colors.orange,
          icon: Icons.receipt_long_outlined,
        ),
      );

      expect(find.text('A deviser'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);
      expect(find.text('Demandes en attente'), findsOneWidget);
      expect(find.byIcon(Icons.receipt_long_outlined), findsOneWidget);
    });

    testWidgets('ActionTile : le badge est optionnel et onTap est déclenché',
        (tester) async {
      var tapped = 0;
      await _pump(
        tester,
        ActionTile(
          icon: Icons.qr_code_2_rounded,
          gradient: const LinearGradient(colors: [Colors.teal, Colors.green]),
          title: 'Module J-Code',
          subtitle: 'Préparer une commande',
          badge: 'Retrait Prêt',
          onTap: () => tapped++,
        ),
      );

      expect(find.text('Module J-Code'), findsOneWidget);
      expect(find.text('Retrait Prêt'), findsOneWidget);

      await tester.tap(find.byType(ActionTile));
      expect(tapped, 1);
    });

    testWidgets('ActionTile sans badge ne rend aucune pastille',
        (tester) async {
      await _pump(
        tester,
        ActionTile(
          icon: Icons.store_rounded,
          gradient: const LinearGradient(colors: [Colors.teal, Colors.green]),
          title: 'Quincailleries Agréées',
          subtitle: 'Réseau partenaire',
          onTap: () {},
        ),
      );

      expect(find.text('Quincailleries Agréées'), findsOneWidget);
      expect(find.text('Retrait Prêt'), findsNothing);
    });

    testWidgets('StatusPill affiche le libellé', (tester) async {
      await _pump(
        tester,
        const StatusPill(label: 'Financée', color: Colors.blue),
      );

      expect(find.text('Financée'), findsOneWidget);
    });

    testWidgets('MetaText tronque et affiche icône + texte', (tester) async {
      await _pump(
        tester,
        const MetaText(icon: Icons.person_outline, text: 'Awa Koné'),
      );

      expect(find.text('Awa Koné'), findsOneWidget);
      expect(find.byIcon(Icons.person_outline), findsOneWidget);
    });

    test('MissionAction conserve les champs fournis', () {
      const action = MissionAction(
        label: 'Devis',
        subtitle: 'Chiffrage attendu',
        color: Colors.orange,
        onTap: null,
      );

      expect(action.label, 'Devis');
      expect(action.subtitle, 'Chiffrage attendu');
      expect(action.onTap, isNull);
    });
  });
}
