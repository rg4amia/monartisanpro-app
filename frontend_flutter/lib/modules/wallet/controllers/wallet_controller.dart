import 'package:get/get.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/utils/error_handler.dart';
import '../../../core/utils/json_readers.dart';
import '../../../data/models/payout_model.dart';
import '../../../data/models/transaction_model.dart';
import '../../../data/repositories/payout_repository.dart';

class WalletController extends GetxController {
  WalletController({PayoutRepository? payoutRepository})
      : _payoutRepo = payoutRepository ?? PayoutRepository();

  final ApiClient _apiClient = ApiClient();
  final PayoutRepository _payoutRepo;

  final isLoading = true.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final walletEscrowLivreur = 0.obs;
  final transactions = <TransactionModel>[].obs;

  /// Virements Mobile Money non aboutis (échoués ou en cours) : les fonds
  /// restent sur le portefeuille et le virement peut être relancé.
  final pendingPayouts = <PayoutModel>[].obs;

  /// Versement dont la relance est en cours, pour ne bloquer que sa ligne.
  final retryingPayoutId = RxnInt();

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

      final transactionsResponse =
          await _apiClient.get(ApiEndpoints.transactions);
      final List<dynamic> data =
          (transactionsResponse.data as Map<String, dynamic>)['data'] ?? [];
      transactions.value =
          data.map((e) => TransactionModel.fromJson(e)).toList();
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

  @override
  Future<void> refresh() async {
    await fetchData();
  }
}
