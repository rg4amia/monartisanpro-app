import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/delivery_fare_model.dart';
import 'package:frontend_flutter/data/models/payout_model.dart';
import 'package:frontend_flutter/data/models/transaction_model.dart';
import 'package:frontend_flutter/modules/home/widgets/driver_home/delivery_fare_dialog.dart';
import 'package:frontend_flutter/modules/orders/widgets/delivery_fare_payment_card.dart';
import 'package:frontend_flutter/modules/wallet/widgets/pending_payouts_section.dart';

/// Chantier 10 : sens des montants, virements échoués relançables, course
/// livreur révélée à la livraison. Les charges utiles incomplètes sont
/// éprouvées autant que la charge nominale (Règle d'or 28).
void main() {
  group('TransactionModel.direction', () {
    test('le sens du serveur fait foi : un acompte est une sortie', () {
      final tx = TransactionModel.fromJson({
        'id': 1,
        'type': 'acompte',
        'montant': 50000,
        'direction': 'sortant',
        'libelle': 'Paiement au coffre de sécurité',
        'statutLibelle': 'Confirmé',
      });

      expect(tx.direction, TransactionModel.sortant);
      expect(tx.libelle, 'Paiement au coffre de sécurité');
      expect(tx.statutLibelle, 'Confirmé');
    });

    test("sans sens serveur, un acompte n'est jamais une rentrée", () {
      final tx = TransactionModel.fromJson({'id': 1, 'type': 'acompte'});
      expect(tx.direction, TransactionModel.sortant);

      final release =
          TransactionModel.fromJson({'id': 2, 'type': 'liberation_jalon'});
      expect(release.direction, TransactionModel.entrant);
    });

    test('un sens inconnu retombe sur le type', () {
      final tx = TransactionModel.fromJson(
        {'id': 1, 'type': 'credit', 'direction': 'n_importe_quoi'},
      );
      expect(tx.direction, TransactionModel.entrant);
    });
  });

  group('PayoutModel', () {
    test('lit un versement échoué complet', () {
      final payout = PayoutModel.fromJson({
        'id': 7,
        'reference': 'VRS-1',
        'context_label': "Paiement d'étape de chantier",
        'montant': 10000,
        'montant_transfere': 9000,
        'statut': 'echoue',
        'statut_label': 'Virement échoué',
        'attempts': '2',
        'last_error': 'Numéro Wave inconnu',
        'next_retry_at': '2026-09-26T12:30:00Z',
        'can_retry': 1,
        'events': [
          {'id': 1, 'action': 'echec', 'action_label': 'Virement échoué'},
        ],
      });

      expect(payout.montant, 9000, reason: 'le montant affiché est le viré');
      expect(payout.isFailed, isTrue);
      expect(payout.isPending, isTrue);
      expect(payout.attempts, 2);
      expect(payout.canRetry, isTrue);
      expect(payout.events.single.actionLabel, 'Virement échoué');
    });

    test('survit à une charge utile vide', () {
      final payout = PayoutModel.fromJson(const {});

      expect(payout.statut, 'en_cours');
      expect(payout.canRetry, isFalse);
      expect(payout.events, isEmpty);
    });
  });

  group('DeliveryFare.tryParse', () {
    test('lit une course à régler avec bonus', () {
      final fare = DeliveryFare.tryParse({
        'base': 3000,
        'waiting_bonus': 500,
        'waiting_minutes': 25,
        'total': 3500,
        'due': 3500,
        'status': 'a_payer',
      })!;

      expect(fare.isAwaitingPayment, isTrue);
      expect(fare.statusLabel, 'Course à régler');
      expect(fare.total, 3500);
    });

    test('déduit le total et le statut quand ils manquent', () {
      final fare =
          DeliveryFare.tryParse({'base': '2000', 'waiting_bonus': 300})!;

      expect(fare.total, 2300);
      expect(fare.isEstimate, isTrue);
    });

    test('renvoie null hors livraison', () {
      expect(DeliveryFare.tryParse(null), isNull);
      expect(DeliveryFare.tryParse([]), isNull);
    });
  });

  group('Widgets', () {
    const awaiting = DeliveryFare(
      base: 3000,
      waitingBonus: 500,
      waitingMinutes: 25,
      total: 3500,
      due: 3500,
      status: 'a_payer',
      statusLabel: 'Course à régler',
    );

    testWidgets('le livreur découvre le montant et le bonus à la livraison',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: DeliveryFareDialog(fare: awaiting)),
      );

      expect(find.text('Livraison confirmée'), findsOneWidget);
      expect(find.textContaining('Bonus d\'attente (25 min)'), findsOneWidget);
      expect(find.textContaining('dès son règlement'), findsOneWidget);
    });

    testWidgets('le client règle la course avec le bon opérateur',
        (tester) async {
      String? chosen;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DeliveryFarePaymentCard(
              fare: awaiting,
              onPay: (provider) => chosen = provider,
            ),
          ),
        ),
      );

      expect(find.textContaining('temps d\'attente'), findsOneWidget);
      await tester.tap(find.text('Orange Money'));
      expect(chosen, 'orange_money');
    });

    testWidgets('un virement échoué propose la relance et son historique',
        (tester) async {
      PayoutModel? retried;
      final payout = PayoutModel.fromJson({
        'id': 7,
        'context_label': "Paiement d'étape de chantier",
        'montant_transfere': 9000,
        'statut': 'echoue',
        'statut_label': 'Virement échoué',
        'last_error': 'Numéro Wave inconnu',
        'can_retry': true,
        'events': [
          {
            'id': 1,
            'action': 'tentative',
            'action_label': 'Tentative de virement',
          },
          {
            'id': 2,
            'action': 'echec',
            'action_label': 'Virement échoué',
            'message': 'Numéro Wave inconnu',
          },
        ],
      });

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: PendingPayoutsSection(
                payouts: [payout],
                onRetry: (p) async => retried = p,
              ),
            ),
          ),
        ),
      );

      expect(find.text('Virements en attente'), findsOneWidget);
      expect(find.text('Numéro Wave inconnu'), findsOneWidget);

      await tester.tap(find.text("Paiement d'étape de chantier"));
      await tester.pumpAndSettle();

      expect(find.text('Tentative de virement'), findsOneWidget);
      await tester.tap(find.text('Relancer le virement'));
      expect(retried?.id, 7);
    });

    testWidgets('aucun bloc sans virement en attente', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body:
                PendingPayoutsSection(payouts: const [], onRetry: (_) async {}),
          ),
        ),
      );

      expect(find.text('Virements en attente'), findsNothing);
    });
  });
}
