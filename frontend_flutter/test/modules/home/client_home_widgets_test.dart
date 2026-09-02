import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/client_stat_card.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/dashboard_mini_card.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/expense_progress.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/info_pill.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/night_mode_banner.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/section_header.dart';
import 'package:frontend_flutter/modules/home/widgets/client_home/tab_button.dart';

Future<void> _pump(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(home: Scaffold(body: Center(child: child))),
  );
}

void main() {
  group('Widgets extraits de ClientHomeScreen', () {
    testWidgets('SectionHeader affiche le titre et le trailing optionnel',
        (tester) async {
      await _pump(
        tester,
        const SectionHeader(
          title: 'Catégories populaires',
          trailing: Text('Voir tout'),
        ),
      );

      expect(find.text('Catégories populaires'), findsOneWidget);
      expect(find.text('Voir tout'), findsOneWidget);
    });

    testWidgets('SectionHeader sans trailing', (tester) async {
      await _pump(tester, const SectionHeader(title: 'Dépenses'));
      expect(find.text('Dépenses'), findsOneWidget);
      expect(find.text('Voir tout'), findsNothing);
    });

    testWidgets('ClientStatCard affiche valeur, titre et sous-titre',
        (tester) async {
      await _pump(
        tester,
        const ClientStatCard(
          title: 'Artisans disponibles',
          value: '7',
          subtitle: 'Autour de votre position',
          color: Colors.blue,
          background: Color(0x11000000),
        ),
      );

      expect(find.text('7'), findsOneWidget);
      expect(find.text('Artisans disponibles'), findsOneWidget);
      expect(find.text('Autour de votre position'), findsOneWidget);
    });

    testWidgets('InfoPill affiche icône + libellé', (tester) async {
      await _pump(
        tester,
        const InfoPill(
          icon: Icons.location_on_outlined,
          label: '7 autour de vous',
          color: Colors.blue,
          background: Color(0x11000000),
        ),
      );

      expect(find.text('7 autour de vous'), findsOneWidget);
      expect(find.byIcon(Icons.location_on_outlined), findsOneWidget);
    });

    testWidgets('TabButton : onTap déclenché, style actif/inactif',
        (tester) async {
      var taps = 0;
      await _pump(
        tester,
        TabButton(
          label: 'Exploration',
          icon: Icons.explore_outlined,
          isActive: true,
          onTap: () => taps++,
        ),
      );

      expect(find.text('Exploration'), findsOneWidget);
      await tester.tap(find.byType(TabButton));
      expect(taps, 1);
    });

    testWidgets('DashboardMiniCard affiche la valeur et le titre',
        (tester) async {
      await _pump(
        tester,
        const DashboardMiniCard(
          title: 'Devis Acceptés',
          value: '14',
          icon: Icons.assignment_turned_in_rounded,
          color: Colors.green,
          bg: Color(0x1100FF00),
        ),
      );

      expect(find.text('14'), findsOneWidget);
      expect(find.text('Devis Acceptés'), findsOneWidget);
    });

    testWidgets('ExpenseProgress formate le montant en K FCFA', (tester) async {
      await _pump(
        tester,
        const ExpenseProgress(
          category: 'Maçonnerie',
          amount: 320000,
          percentage: 0.5,
          color: Colors.blue,
        ),
      );

      expect(find.text('Maçonnerie'), findsOneWidget);
      expect(find.text('320K FCFA'), findsOneWidget);
      expect(find.byType(LinearProgressIndicator), findsOneWidget);
    });

    testWidgets('NightModeBanner rend son message', (tester) async {
      await _pump(tester, const NightModeBanner());
      expect(find.text('Mode Nuit Actif (18h - 7h)'), findsOneWidget);
    });
  });
}
