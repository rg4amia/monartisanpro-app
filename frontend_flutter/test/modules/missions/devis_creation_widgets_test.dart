import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_creation/creation_app_bar.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_creation/creation_empty_state.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_creation/creation_money_format.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_creation/workflow_card.dart';

Future<void> _pump(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(home: Scaffold(body: Center(child: child))),
  );
}

void main() {
  group('Widgets extraits de DevisCreationScreen', () {
    test('formatCreationFcfa insère un séparateur d\'espace simple', () {
      expect(formatCreationFcfa(1500000), '1 500 000 FCFA');
      expect(formatCreationFcfa(500), '500 FCFA');
      expect(formatCreationFcfa(0), '0 FCFA');
    });

    testWidgets('CreationAppBar affiche le titre', (tester) async {
      await _pump(tester, const CreationAppBar());
      expect(find.text('Créer un devis'), findsOneWidget);
    });

    testWidgets('CreationEmptyState rend message et indice', (tester) async {
      await _pump(
        tester,
        const CreationEmptyState(
          icon: Icons.inbox_outlined,
          message: 'Aucun jalon défini',
          hint: 'Définissez les étapes de validation du projet',
        ),
      );
      expect(find.text('Aucun jalon défini'), findsOneWidget);
      expect(
        find.text('Définissez les étapes de validation du projet'),
        findsOneWidget,
      );
    });

    testWidgets('WorkflowCard rappelle la règle des jalons', (tester) async {
      await _pump(tester, const WorkflowCard());
      expect(find.textContaining('total des jalons doit égaler'), findsOneWidget);
    });
  });
}
