import 'package:get/get.dart';

import '../../../core/payments/receipt_opener.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/payout_model.dart';
import '../../../data/repositories/payout_repository.dart';

/// Retrait des gains du livreur (Chantier 10), sur le modèle du cash-out
/// quincaillerie : le livreur demande, ProsArtisan valide puis verse.
class DriverCashoutController extends GetxController {
  DriverCashoutController({
    PayoutRepository? repository,
    ReceiptOpener? receiptOpener,
  })  : _repo = repository ?? PayoutRepository(),
        _receiptOpener = receiptOpener ?? ReceiptOpener();

  final PayoutRepository _repo;
  final ReceiptOpener _receiptOpener;

  /// Retrait dont le reçu PDF est en cours d'ouverture.
  final openingReceiptId = RxnInt();

  final isLoading = true.obs;
  final isSubmitting = false.obs;
  final stats = const DriverCashoutStats().obs;
  final cashouts = <DriverCashoutModel>[].obs;
  final errorMsg = RxnString();

  /// Mode choisi : `wave`, `orange_money` ou `virement_bancaire`.
  final mode = 'wave'.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.getDriverCashouts();
      stats.value = result.stats;
      cashouts.value = result.cashouts;
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }

  /// Ouvre le reçu PDF d'un retrait versé ; renvoie le message d'erreur à
  /// afficher, `null` si le reçu s'est ouvert.
  Future<String?> openReceipt(DriverCashoutModel cashout) async {
    final transactionId = cashout.transactionId;
    if (!cashout.receiptAvailable || transactionId == null) {
      return 'Le reçu est disponible une fois le retrait versé.';
    }

    openingReceiptId.value = cashout.id;
    try {
      return await _receiptOpener.open(transactionId);
    } finally {
      openingReceiptId.value = null;
    }
  }

  /// Montant net estimé après les frais de retrait éventuels.
  int netFor(int amount) =>
      amount - (amount * stats.value.commissionRate).round();

  /// Validation locale de confort ; le serveur reste seul juge du solde.
  String? validateAmount(int? amount) {
    if (amount == null || amount <= 0) return 'Saisissez un montant.';
    if (amount < stats.value.minimumAmount) {
      return 'Minimum ${stats.value.minimumAmount} FCFA.';
    }
    if (amount > stats.value.availableBalance) {
      return 'Supérieur à votre solde retirable.';
    }
    return null;
  }

  /// Renvoie le message du serveur en cas de succès, `null` sinon
  /// (l'erreur est alors dans [errorMsg]).
  Future<String?> submit({
    required int amount,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
  }) async {
    isSubmitting.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.requestDriverCashout(
        montant: amount,
        mode: mode.value,
        beneficiaryPhone: beneficiaryPhone,
        bankName: bankName,
        bankAccountNumber: bankAccountNumber,
      );
      await load();

      return result.message;
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);

      return null;
    } finally {
      isSubmitting.value = false;
    }
  }
}
