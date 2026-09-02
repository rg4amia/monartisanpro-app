import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_review/devis_money_format.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_review/grands_comptes_warning_card.dart';
import 'package:frontend_flutter/modules/missions/widgets/devis_review/review_error_state.dart';

Future<void> _pump(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(home: Scaffold(body: Center(child: child))),
  );
}

void main() {
  group('Widgets extraits de DevisReviewScreen', () {
    test('formatDevisFcfa insère un séparateur d\'espace simple', () {
      expect(formatDevisFcfa(1500000), '1 500 000 FCFA');
      expect(formatDevisFcfa(500), '500 FCFA');
      expect(formatDevisFcfa(0), '0 FCFA');
    });

    testWidgets('ReviewErrorState affiche le message et rappelle onRetry',
        (tester) async {
      var retries = 0;
      await _pump(tester, ReviewErrorState(onRetry: () => retries++));

      expect(find.text('Impossible de charger le devis'), findsOneWidget);
      await tester.tap(find.text('Réessayer'));
      expect(retries, 1);
    });

    testWidgets('GrandsComptesWarningCard rend son avertissement',
        (tester) async {
      await _pump(tester, const GrandsComptesWarningCard());
      expect(find.text('Limite Grands Comptes Active'), findsOneWidget);
    });
  });
}
