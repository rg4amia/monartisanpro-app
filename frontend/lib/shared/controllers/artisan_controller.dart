import 'package:get/get.dart';
import '../../core/network/artisan_service.dart';
import '../models/escrow_model.dart';
import '../models/project_model.dart';

class ArtisanController extends GetxController {
  final ArtisanService _artisanService = ArtisanService();

  // Stats
  final Rx<ArtisanStats?> stats = Rx<ArtisanStats?>(null);
  final RxBool isLoadingStats = false.obs;

  // Quotes
  final RxList<Quote> quotes = <Quote>[].obs;
  final RxBool isLoadingQuotes = false.obs;
  final RxString quotesFilter = 'all'.obs;

  // Transactions
  final RxList<Transaction> transactions = <Transaction>[].obs;
  final RxBool isLoadingTransactions = false.obs;
  final RxString transactionsFilter = 'all'.obs;

  // Error handling
  final RxString errorMessage = ''.obs;

  @override
  void onInit() {
    super.onInit();
    loadStats();
  }

  /// Load artisan statistics
  Future<void> loadStats() async {
    try {
      isLoadingStats.value = true;
      errorMessage.value = '';

      final response = await _artisanService.getStats();

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

  /// Load artisan quotes
  Future<void> loadQuotes({String? status}) async {
    try {
      isLoadingQuotes.value = true;
      errorMessage.value = '';

      final response = await _artisanService.getQuotes(
        status: status ?? quotesFilter.value,
      );

      if (response.success && response.data != null) {
        quotes.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
        quotes.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      quotes.value = [];
    } finally {
      isLoadingQuotes.value = false;
    }
  }

  /// Load artisan transactions
  Future<void> loadTransactions({String? status, String? type}) async {
    try {
      isLoadingTransactions.value = true;
      errorMessage.value = '';

      final response = await _artisanService.getTransactions(
        status: status,
        transactionType: type,
      );

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

  /// Filter quotes by status
  void filterQuotes(String status) {
    quotesFilter.value = status;
    loadQuotes(status: status);
  }

  /// Filter transactions by status
  void filterTransactions(String status) {
    transactionsFilter.value = status;
    loadTransactions(status: status);
  }

  /// Refresh all data
  Future<void> refreshAll() async {
    await Future.wait([loadStats(), loadQuotes(), loadTransactions()]);
  }

  /// Get filtered quotes
  List<Quote> getFilteredQuotes(String status) {
    if (status == 'all') return quotes;
    return quotes.where((q) => q.status == status).toList();
  }

  /// Get filtered transactions
  List<Transaction> getFilteredTransactions(String status) {
    if (status == 'all') return transactions;
    return transactions.where((t) => t.status == status).toList();
  }

  /// Calculate total earnings
  double get totalEarnings {
    return transactions
        .where((t) => t.status == 'completed' && t.type == 'labor_release')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  /// Calculate pending payments
  double get pendingPayments {
    return transactions
        .where((t) => t.status == 'pending' && t.type == 'labor_release')
        .fold(0.0, (sum, t) => sum + t.amount);
  }
}
