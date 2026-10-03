import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/payments/operator_payment_runner.dart';
import 'package:frontend_flutter/data/models/dispute_debt_model.dart';
import 'package:frontend_flutter/data/models/payment_model.dart';
import 'package:frontend_flutter/data/repositories/dispute_debt_repository.dart';
import 'package:frontend_flutter/modules/history/controllers/dispute_debt_controller.dart';
import 'package:frontend_flutter/modules/history/views/dispute_debt_screen.dart';
import 'package:get/get.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../../helpers/test_helpers.dart';

DisputeDebt _debt({String statut = 'en_cours', int restant = 7000}) =>
    DisputeDebt.fromJson({
      'id': 4,
      'order_id': 100,
      'montant': 19000,
      'montant_recouvre': 19000 - restant,
      'restant': restant,
      'statut': statut,
      'statut_label': statut == 'en_cours' ? 'À rembourser' : 'Soldée',
      'entries': [
        {
          'montant': 12000,
          'source_label': 'Prélèvement sur vos gains',
          'created_at': '2026-10-02T09:00:00Z',
        },
      ],
    });

class _FakeRepository extends DisputeDebtRepository {
  _FakeRepository(this.debts, {this.failList = false});

  List<DisputeDebt> debts;
  final bool failList;
  final paid = <(int, String)>[];

  @override
  Future<List<DisputeDebt>> list() async {
    if (failList) throw Exception('panne');
    return debts;
  }

  @override
  Future<PaymentInitiationModel> pay(
    int debtId, {
    required String provider,
  }) async {
    paid.add((debtId, provider));
    return const PaymentInitiationModel(transactionId: 55, provider: 'wave');
  }
}

class _FakeRunner extends OperatorPaymentRunner {
  _FakeRunner(this.outcome);

  final OperatorPaymentOutcome outcome;

  @override
  Future<OperatorPaymentOutcome> run(PaymentInitiationModel payment) async =>
      outcome;
}

Widget _host(Widget child) => GetMaterialApp(home: Scaffold(body: child));

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
    await initializeDateFormatting('fr_FR', null);
  });

  tearDown(Get.reset);

  test('une dette se lit sans exception sur une charge utile incomplète', () {
    final debt = DisputeDebt.fromJson({'id': 9});

    expect(debt.isOpen, isTrue);
    expect(debt.restant, 0);
    expect(debt.entries, isEmpty);
    expect(debt.orderId, isNull);
  });

  group('DisputeDebtController', () {
    test('totalise le restant dû des seules dettes en cours', () async {
      final controller = DisputeDebtController(
        repository: _FakeRepository([
          _debt(),
          _debt(statut: 'soldee', restant: 0),
        ]),
      );
      await controller.load();

      expect(controller.openDebts, hasLength(1));
      expect(controller.totalDue, 7000);
    });

    test('un règlement confirmé recharge les dettes', () async {
      final repo = _FakeRepository([_debt()]);
      final controller = DisputeDebtController(
        repository: repo,
        runner: _FakeRunner(OperatorPaymentOutcome.confirmed),
      );
      await controller.load();
      repo.debts = [_debt(statut: 'soldee', restant: 0)];

      final confirmed = await controller.pay(_debt(), provider: 'wave');

      expect(confirmed, isTrue);
      expect(repo.paid, [(4, 'wave')]);
      expect(controller.totalDue, 0);
      expect(controller.paymentMessage.value, contains('débloqué'));
      expect(controller.payingDebtId.value, isNull);
    });

    test('un règlement non abouti le dit et laisse la dette en cours',
        () async {
      final controller = DisputeDebtController(
        repository: _FakeRepository([_debt()]),
        runner: _FakeRunner(OperatorPaymentOutcome.failed),
      );
      await controller.load();

      final confirmed = await controller.pay(_debt(), provider: 'orange_money');

      expect(confirmed, isFalse);
      expect(controller.totalDue, 7000);
      expect(controller.paymentMessage.value, contains('n\'a pas abouti'));
    });

    test('une panne de chargement s\'annonce', () async {
      final controller = DisputeDebtController(
        repository: _FakeRepository(const [], failList: true),
      );
      await controller.load();

      expect(controller.errorMsg.value, isNotNull);
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('Écrans', () {
    testWidgets('le bandeau annonce la somme due et le blocage',
        (tester) async {
      final controller =
          DisputeDebtController(repository: _FakeRepository([_debt()]));
      await controller.load();

      await tester.pumpWidget(_host(DisputeDebtBanner(controller: controller)));

      expect(find.textContaining('Remboursement dû : 7'), findsOneWidget);
      expect(find.textContaining('Votre compte est bloqué'), findsOneWidget);
      expect(find.text('Régler maintenant'), findsOneWidget);
    });

    testWidgets('sans dette en cours, le bandeau n\'affiche rien',
        (tester) async {
      final controller = DisputeDebtController(
        repository: _FakeRepository([_debt(statut: 'soldee', restant: 0)]),
      );
      await controller.load();

      await tester.pumpWidget(_host(DisputeDebtBanner(controller: controller)));

      expect(find.text('Régler maintenant'), findsNothing);
    });

    testWidgets('l\'écran détaille la dette et lance le règlement',
        (tester) async {
      final repo = _FakeRepository([_debt()]);
      final controller = DisputeDebtController(
        repository: repo,
        runner: _FakeRunner(OperatorPaymentOutcome.pending),
      );
      await controller.load();

      await tester.pumpWidget(
        GetMaterialApp(home: DisputeDebtScreen(controller: controller)),
      );

      expect(find.text('Litige de la commande #100'), findsOneWidget);
      expect(find.textContaining('Reste à régler : 7'), findsOneWidget);
      expect(
        find.textContaining('Prélèvement sur vos gains : 12'),
        findsOneWidget,
      );

      await tester.tap(find.text('Régler par Wave'));
      await tester.pumpAndSettle();

      expect(repo.paid, [(4, 'wave')]);
      expect(find.textContaining('en attente de confirmation'), findsOneWidget);
    });

    testWidgets('une panne s\'annonce, jamais « Aucun remboursement dû »',
        (tester) async {
      final controller = DisputeDebtController(
        repository: _FakeRepository(const [], failList: true),
      );
      await controller.load();

      await tester.pumpWidget(
        GetMaterialApp(home: DisputeDebtScreen(controller: controller)),
      );

      expect(find.text('Réessayer'), findsOneWidget);
      expect(find.text('Aucun remboursement dû'), findsNothing);
    });
  });
}
