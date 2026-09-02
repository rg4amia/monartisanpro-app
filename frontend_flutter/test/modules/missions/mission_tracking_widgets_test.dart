import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/budget_bar.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/status_pill.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/tracking_info_row.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/tracking_media_viewer.dart';

Future<void> _pump(WidgetTester tester, Widget child) {
  return tester.pumpWidget(
    MaterialApp(home: Scaffold(body: Center(child: child))),
  );
}

void main() {
  group('Widgets extraits de MissionTrackingScreen', () {
    test('isTrackingVideoUrl reconnaît les extensions vidéo', () {
      expect(isTrackingVideoUrl('https://x/clip.MP4'), isTrue);
      expect(isTrackingVideoUrl('https://x/proof.mov'), isTrue);
      expect(isTrackingVideoUrl('https://x/photo.jpg'), isFalse);
      expect(isTrackingVideoUrl('https://x/photo.png'), isFalse);
    });

    testWidgets('StatusPill affiche son libellé', (tester) async {
      await _pump(
        tester,
        const StatusPill(label: 'Soumis', color: Colors.orange),
      );
      expect(find.text('Soumis'), findsOneWidget);
    });

    testWidgets('TrackingInfoRow affiche libellé et valeur', (tester) async {
      await _pump(
        tester,
        const TrackingInfoRow(label: 'Total general', value: '1 000 FCFA'),
      );
      expect(find.text('Total general'), findsOneWidget);
      expect(find.text('1 000 FCFA'), findsOneWidget);
    });

    testWidgets('BudgetBar affiche le libellé et le montant formaté',
        (tester) async {
      await _pump(
        tester,
        const BudgetBar(
          label: 'Wallet materiaux',
          amount: 250000,
          percentage: 65,
          color: Colors.green,
        ),
      );
      expect(find.text('Wallet materiaux'), findsOneWidget);
      expect(find.byType(LinearProgressIndicator), findsOneWidget);
    });
  });
}
