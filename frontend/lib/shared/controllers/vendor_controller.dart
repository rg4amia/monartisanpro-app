import 'package:get/get.dart';
import '../../core/network/vendor_service.dart';
import '../models/escrow_model.dart';

class VendorController extends GetxController {
  final VendorService _vendorService = VendorService();

  // Stats
  final Rx<VendorStats?> stats = Rx<VendorStats?>(null);
  final RxBool isLoadingStats = false.obs;

  // Redemptions
  final RxList<TokenRedemption> redemptions = <TokenRedemption>[].obs;
  final RxBool isLoadingRedemptions = false.obs;
  final RxString redemptionsFilter = 'all'.obs;

  // Transactions
  final RxList<Transaction> transactions = <Transaction>[].obs;
  final RxBool isLoadingTransactions = false.obs;
  final RxString transactionsFilter = 'all'.obs;

  // Analytics
  final Rx<VendorAnalytics?> analytics = Rx<VendorAnalytics?>(null);
  final RxBool isLoadingAnalytics = false.obs;
  final RxString analyticsPeriod = 'month'.obs;

  // Error handling
  final RxString errorMessage = ''.obs;

  @override
  void onInit() {
    super.onInit();
    loadStats();
  }

  /// Load vendor statistics
  Future<void> loadStats() async {
    try {
      isLoadingStats.value = true;
      errorMessage.value = '';

      final response = await _vendorService.getStats();

      if (response.success && response.data != null) {
        stats.value = response.data;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
    } finally {
      isLoadingStats.value = false;
    }
  }

  /// Load vendor redemptions
  Future<void> loadRedemptions({
    String? validationMethod,
    String? startDate,
    String? endDate,
  }) async {
    try {
      isLoadingRedemptions.value = true;
      errorMessage.value = '';

      final response = await _vendorService.getRedemptions(
        validationMethod: validationMethod,
        startDate: startDate,
        endDate: endDate,
      );

      if (response.success && response.data != null) {
        redemptions.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
        redemptions.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      redemptions.value = [];
    } finally {
      isLoadingRedemptions.value = false;
    }
  }

  /// Load vendor transactions
  Future<void> loadTransactions({String? status}) async {
    try {
      isLoadingTransactions.value = true;
      errorMessage.value = '';

      final response = await _vendorService.getTransactions(status: status);

      if (response.success && response.data != null) {
        transactions.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
        transactions.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      transactions.value = [];
    } finally {
      isLoadingTransactions.value = false;
    }
  }

  /// Load vendor analytics
  Future<void> loadAnalytics({String? period}) async {
    try {
      isLoadingAnalytics.value = true;
      errorMessage.value = '';

      final response = await _vendorService.getAnalytics(
        period: period ?? analyticsPeriod.value,
      );

      if (response.success && response.data != null) {
        analytics.value = response.data;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
    } finally {
      isLoadingAnalytics.value = false;
    }
  }

  /// Filter redemptions by validation method
  void filterRedemptions(String method) {
    redemptionsFilter.value = method;
    loadRedemptions(
      validationMethod: method == 'all' ? null : method,
    );
  }

  /// Filter transactions by status
  void filterTransactions(String status) {
    transactionsFilter.value = status;
    loadTransactions(status: status == 'all' ? null : status);
  }

  /// Change analytics period
  void changeAnalyticsPeriod(String period) {
    analyticsPeriod.value = period;
    loadAnalytics(period: period);
  }

  /// Refresh all data
  Future<void> refreshAll() async {
    await Future.wait([
      loadStats(),
      loadRedemptions(),
      loadTransactions(),
      loadAnalytics(),
    ]);
  }

  /// Get filtered redemptions
  List<TokenRedemption> getFilteredRedemptions(String method) {
    if (method == 'all') return redemptions;
    return redemptions.where((r) => r.validationMethod == method).toList();
  }

  /// Get filtered transactions
  List<Transaction> getFilteredTransactions(String status) {
    if (status == 'all') return transactions;
    return transactions.where((t) => t.status == status).toList();
  }

  /// Calculate total pending
  double get totalPending {
    return transactions
        .where((t) => t.status == 'pending')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  /// Calculate total completed
  double get totalCompleted {
    return transactions
        .where((t) => t.status == 'completed')
        .fold(0.0, (sum, t) => sum + t.amount);
  }
}
