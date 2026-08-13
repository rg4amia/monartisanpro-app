import 'package:get/get.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../data/models/transaction_model.dart';

class WalletController extends GetxController {
  final ApiClient _apiClient = ApiClient();

  final isLoading = true.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final transactions = <TransactionModel>[].obs;

  @override
  void onInit() {
    super.onInit();
    fetchData();
  }

  Future<void> fetchData() async {
    isLoading.value = true;
    try {
      // Assuming GET /api/v1/wallets/balance returns wallet balance
      // and GET /api/v1/transactions returns transaction history
      
      final balanceResponse = await _apiClient.get(ApiEndpoints.walletBalance);
      final balance = WalletBalance.fromJson(balanceResponse.data['data']);
      walletMateriaux.value = balance.walletMateriaux;
      walletMo.value = balance.walletMo;

      final transactionsResponse = await _apiClient.get(ApiEndpoints.transactions);
      final List<dynamic> data = transactionsResponse.data['data'] ?? [];
      transactions.value = data.map((e) => TransactionModel.fromJson(e)).toList();
    } catch (e) {
      Get.snackbar('Erreur', 'Impossible de charger le portefeuille', snackPosition: SnackPosition.BOTTOM);
    } finally {
      isLoading.value = false;
    }
  }

  @override
  Future<void> refresh() async {
    await fetchData();
  }
}
