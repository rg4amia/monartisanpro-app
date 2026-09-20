import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/mission_model.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/budget_bar.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/status_pill.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/tracking_info_row.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/tracking_media_viewer.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/workflow_card.dart';

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

    testWidgets(
        'WorkflowCard invite l\'artisan a accepter/refuser une demande en '
        'pending_artisan_acceptance (non-regression du statut normalise)',
        (tester) async {
      // json['status'] brut FSM : MissionModel.status le normalise en
      // 'en_attente', mais rawStatus (statusGemini) doit rester
      // 'pending_artisan_acceptance' pour piloter cet affichage.
      final mission = MissionModel.fromJson({
        'id': 1,
        'client_id': 10,
        'artisan_id': 20,
        'status': 'pending_artisan_acceptance',
        'montant_total': 0,
        'montant_materiaux': 0,
        'montant_mo': 0,
        'created_at': DateTime.now().toIso8601String(),
      });

      expect(mission.status, 'en_attente');
      expect(mission.rawStatus, 'pending_artisan_acceptance');

      await _pump(
        tester,
        WorkflowCard(
          mission: mission,
          devis: null,
          role: 'artisan',
          hasReferentPendingValidation: false,
        ),
      );

      expect(find.text('Nouvelle demande de devis reçue'), findsOneWidget);
      expect(find.textContaining('accepter ou refuser'), findsOneWidget);
      expect(find.text('Devis a preparer'), findsNothing);
    });
  });
}
