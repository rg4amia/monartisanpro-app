import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/home/widgets/driver_home/driver_overview_cards.dart';

/// La carte des notations du livreur affichait en dur « 4.9/5 — Basé sur
/// 48 courses » et « +0.2 ce mois » pour tous les comptes, y compris un
/// livreur jamais évalué (Règle d'or 29).
void main() {
  Future<void> pump(
    WidgetTester tester, {
    required double? rating,
    required int count,
    Map<int, double> distribution = const {},
  }) {
    return tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: buildDriverRatingEvolutionCard(
            rating: rating,
            ratingsCount: count,
            distribution: distribution,
          ),
        ),
      ),
    );
  }

  testWidgets('un livreur jamais évalué voit « Non évalué », pas une note',
      (tester) async {
    await pump(tester, rating: null, count: 0);

    expect(find.textContaining('Non évalué'), findsOneWidget);
    expect(find.text('4.9'), findsNothing);
    expect(find.textContaining('48'), findsNothing);
    expect(find.textContaining('ce mois'), findsNothing);
  });

  testWidgets('la note et la répartition reflètent les évaluations reçues',
      (tester) async {
    await pump(
      tester,
      rating: 4.3,
      count: 4,
      distribution: {5: 0.5, 4: 0.25, 3: 0.25, 2: 0, 1: 0},
    );

    expect(find.text('4.3'), findsOneWidget);
    expect(find.text('Basé sur 4 évaluations'), findsOneWidget);
    expect(find.text('50%'), findsOneWidget);
    expect(find.text('25%'), findsNWidgets(2));
    expect(find.textContaining('Non évalué'), findsNothing);
  });

  testWidgets('une répartition incomplète ne casse pas le rendu',
      (tester) async {
    await pump(tester, rating: 5, count: 1, distribution: const {});

    expect(find.text('5.0'), findsOneWidget);
    expect(find.text('Basé sur 1 évaluation'), findsOneWidget);
    expect(find.text('0%'), findsNWidgets(5));
  });
}
