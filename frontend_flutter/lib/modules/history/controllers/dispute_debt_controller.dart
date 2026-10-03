import 'package:get/get.dart';

import '../../../core/payments/operator_payment_runner.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/dispute_debt_model.dart';
import '../../../data/repositories/dispute_debt_repository.dart';

/// Dettes de litige de commande du compte connecté et leur règlement direct
/// par Wave ou Orange Money.
class DisputeDebtController extends GetxController {
  DisputeDebtController({
    DisputeDebtRepository? repository,
    OperatorPaymentRunner? runner,
  })  : _repo = repository ?? DisputeDebtRepository(),
        _runner = runner ?? OperatorPaymentRunner();

  final DisputeDebtRepository _repo;
  final OperatorPaymentRunner _runner;

  final debts = <DisputeDebt>[].obs;
  final isLoading = false.obs;
  final errorMsg = RxnString();

  /// Dette dont le règlement est en cours chez l'opérateur.
  final payingDebtId = RxnInt();

  /// Message à afficher après une tentative de règlement.
  final paymentMessage = RxnString();

  List<DisputeDebt> get openDebts => debts.where((d) => d.isOpen).toList();

  /// Somme restant due, toutes dettes en cours confondues.
  int get totalDue => openDebts.fold(0, (sum, debt) => sum + debt.restant);

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      debts.assignAll(await _repo.list());
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }

  /// Règle une dette. Renvoie `true` si le paiement est confirmé pendant
  /// l'attente ; sinon [paymentMessage] dit où en est le règlement.
  Future<bool> pay(DisputeDebt debt, {required String provider}) async {
    if (payingDebtId.value != null) return false;

    payingDebtId.value = debt.id;
    paymentMessage.value = null;

    try {
      final payment = await _repo.pay(debt.id, provider: provider);
      final outcome = await _runner.run(payment);

      switch (outcome) {
        case OperatorPaymentOutcome.confirmed:
          paymentMessage.value =
              'Remboursement reçu. Votre compte est débloqué.';
        case OperatorPaymentOutcome.failed:
          paymentMessage.value =
              'Le paiement n\'a pas abouti. Vous pouvez réessayer.';
        case OperatorPaymentOutcome.pending:
          paymentMessage.value =
              'Paiement en attente de confirmation. Actualisez dans quelques instants.';
      }

      await load();

      return outcome == OperatorPaymentOutcome.confirmed;
    } catch (e) {
      paymentMessage.value = ErrorHandler.getErrorMessage(e);

      return false;
    } finally {
      payingDebtId.value = null;
    }
  }
}
