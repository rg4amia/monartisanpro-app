import 'package:get/get.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/payments/receipt_opener.dart';
import '../../../core/utils/error_handler.dart';
import '../../../core/utils/json_readers.dart';
import '../../../data/models/history_models.dart';
import '../../../data/models/payout_model.dart';
import '../../../data/models/transaction_model.dart';
import '../../../data/repositories/payout_repository.dart';

class WalletController extends GetxController {
  WalletController({
    PayoutRepository? payoutRepository,
    ReceiptOpener? receiptOpener,
  })  : _payoutRepo = payoutRepository ?? PayoutRepository(),
        _receiptOpener = receiptOpener ?? ReceiptOpener();

  final ApiClient _apiClient = ApiClient();
  final PayoutRepository _payoutRepo;
  final ReceiptOpener _receiptOpener;

  final isLoading = true.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final walletEscrowLivreur = 0.obs;
  final transactions = <TransactionModel>[].obs;

  /// Statut affiché dans l'historique ; `null` = toutes les opérations.
  final statusFilter = RxnString();
  final hasMoreTransactions = false.obs;
  final isLoadingMore = false.obs;
  int _page = 1;

  /// Virements Mobile Money non aboutis (échoués ou en cours) : les fonds
  /// restent sur le portefeuille et le virement peut être relancé.
  final pendingPayouts = <PayoutModel>[].obs;

  /// Versement dont la relance est en cours, pour ne bloquer que sa ligne.
  final retryingPayoutId = RxnInt();

  /// Remboursement dont le moyen de réception est en cours d'enregistrement.
  final updatingDestinationPayoutId = RxnInt();

  /// Transaction dont le reçu PDF est en cours d'ouverture.
  final openingReceiptId = RxnInt();

  @override
  void onInit() {
    super.onInit();
    fetchData();
  }

  Future<void> fetchData() async {
    isLoading.value = true;
    try {
      final balanceResponse = await _apiClient.get(ApiEndpoints.walletBalance);
      final balanceData = (balanceResponse.data as Map<String, dynamic>)['data']
          as Map<String, dynamic>;
      final balance = WalletBalance.fromJson(balanceData);
      walletMateriaux.value = balance.walletMateriaux;
      walletMo.value = balance.walletMo;

      // Un livreur sans champ `wallet_escrow_livreur` dans la réponse API
      // n'a aucun séquestre en cours — jamais de valeur inventée affichée
      // à sa place (Règle d'or 29).
      walletEscrowLivreur.value =
          readInt(balanceData['wallet_escrow_livreur']) ?? 0;

      final page = await _fetchTransactions(1);
      _page = 1;
      transactions.value = page.items;
      hasMoreTransactions.value = page.hasMore;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger le portefeuille',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }

    await loadPayouts();
  }

  Future<HistoryPage<TransactionModel>> _fetchTransactions(int page) async {
    final response = await _apiClient.get(
      ApiEndpoints.transactions,
      params: {
        'page': page,
        if (statusFilter.value != null) 'status': statusFilter.value,
      },
    );

    return HistoryPage<TransactionModel>.fromResponse(
      response.data,
      TransactionModel.fromJson,
    );
  }

  /// Page suivante de l'historique ; renvoie le message d'erreur à afficher,
  /// `null` si elle est chargée. Les lignes déjà affichées sont conservées.
  Future<String?> loadMoreTransactions() async {
    if (!hasMoreTransactions.value || isLoadingMore.value) return null;

    isLoadingMore.value = true;
    try {
      final page = await _fetchTransactions(_page + 1);
      _page += 1;
      transactions.addAll(page.items);
      hasMoreTransactions.value = page.hasMore;

      return null;
    } catch (e) {
      return ErrorHandler.getErrorMessage(e);
    } finally {
      isLoadingMore.value = false;
    }
  }

  /// Filtre l'historique par statut (`confirme`, `en_attente`, `echoue`),
  /// `null` pour tout afficher.
  Future<void> setStatusFilter(String? status) async {
    if (statusFilter.value == status) return;

    statusFilter.value = status;
    await fetchData();
  }

  /// Bloc indépendant : une panne de ce seul point d'accès ne doit pas
  /// masquer le solde ni l'historique déjà chargés.
  Future<void> loadPayouts() async {
    try {
      final payouts = await _payoutRepo.getPayouts();
      pendingPayouts.value = payouts.where((p) => p.isPending).toList();
    } catch (_) {
      pendingPayouts.clear();
    }
  }

  /// Relance d'un virement échoué ; renvoie le message du serveur.
  Future<String> retryPayout(int payoutId) async {
    retryingPayoutId.value = payoutId;
    try {
      final result = await _payoutRepo.retryPayout(payoutId);
      await fetchData();

      return result.message;
    } catch (e) {
      return ErrorHandler.getErrorMessage(e);
    } finally {
      retryingPayoutId.value = null;
    }
  }

  /// Moyen de réception d'un remboursement après litige non abouti, choisi
  /// par le client ; renvoie le message du serveur.
  Future<String> changePayoutDestination(
    PayoutModel payout, {
    required String provider,
    required String phone,
  }) async {
    updatingDestinationPayoutId.value = payout.id;
    try {
      final result = await _payoutRepo.updateDestination(
        payout.id,
        provider: provider,
        phone: phone,
      );
      await loadPayouts();

      return result.message;
    } catch (e) {
      return ErrorHandler.getErrorMessage(e);
    } finally {
      updatingDestinationPayoutId.value = null;
    }
  }

  /// Ouvre le reçu PDF d'une transaction ; renvoie le message d'erreur à
  /// afficher, `null` si le reçu s'est ouvert.
  Future<String?> openReceipt(int transactionId) async {
    openingReceiptId.value = transactionId;
    try {
      return await _receiptOpener.open(transactionId);
    } finally {
      openingReceiptId.value = null;
    }
  }

  @override
  Future<void> refresh() async {
    await fetchData();
  }
}
