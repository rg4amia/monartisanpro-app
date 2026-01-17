import 'package:get/get.dart';
import '../../data/repositories/transaction_repository.dart';
import '../../domain/entities/transaction.dart';

/// Controller for transaction history
///
/// Requirements: 4.6, 13.6
class TransactionHistoryController extends GetxController {
  final TransactionRepository _transactionRepository =
      Get.find<TransactionRepository>();

  final RxBool isLoading = false.obs;
  final RxBool isLoadingMore = false.obs;
  final RxBool hasMorePages = true.obs;
  final RxList<Transaction> transactions = <Transaction>[].obs;
  final RxString selectedFilter = 'all'.obs;
  final RxString errorMessage = ''.obs;

  int _currentPage = 1;
  static const int _pageSize = 20;

  /// Load transactions with current filter
  Future<void> loadTransactions() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';
      _currentPage = 1;

      final result = await _transactionRepository.getTransactionHistory(
        page: _currentPage,
        limit: _pageSize,
        type: selectedFilter.value == 'all' ? null : selectedFilter.value,
      );

      if (result.isSuccess) {
        transactions.value = result.transactions;
        hasMorePages.value = result.hasMorePages;
      } else {
        errorMessage.value = result.errorMessage ?? 'Erreur lors du chargement';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      errorMessage.value = 'Erreur de connexion';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoading.value = false;
    }
  }

  /// Load more transactions (pagination)
  Future<void> loadMoreTransactions() async {
    if (isLoadingMore.value || !hasMorePages.value) return;

    try {
      isLoadingMore.value = true;
      _currentPage++;

      final result = await _transactionRepository.getTransactionHistory(
        page: _currentPage,
        limit: _pageSize,
        type: selectedFilter.value == 'all' ? null : selectedFilter.value,
      );

      if (result.isSuccess) {
        transactions.addAll(result.transactions);
        hasMorePages.value = result.hasMorePages;
      } else {
        _currentPage--; // Revert page increment on error
        errorMessage.value = result.errorMessage ?? 'Erreur lors du chargement';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      _currentPage--; // Revert page increment on error
      errorMessage.value = 'Erreur de connexion';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoadingMore.value = false;
    }
  }

  /// Refresh transactions (pull to refresh)
  Future<void> refreshTransactions() async {
    await loadTransactions();
  }

  /// Set transaction type filter
  void setFilter(String filter) {
    if (selectedFilter.value != filter) {
      selectedFilter.value = filter;
      loadTransactions();
    }
  }

  /// Get available filter options
  List<Map<String, String>> get filterOptions => [
    {'key': 'all', 'label': 'Toutes'},
    {'key': 'ESCROW_BLOCK', 'label': 'Séquestre'},
    {'key': 'MATERIAL_RELEASE', 'label': 'Matériaux'},
    {'key': 'LABOR_RELEASE', 'label': 'Main d\'œuvre'},
    {'key': 'REFUND', 'label': 'Remboursement'},
  ];

  /// Get transaction type display name
  String getTransactionTypeDisplayName(String type) {
    switch (type) {
      case 'ESCROW_BLOCK':
        return 'Blocage séquestre';
      case 'MATERIAL_RELEASE':
        return 'Libération matériaux';
      case 'LABOR_RELEASE':
        return 'Libération main d\'œuvre';
      case 'REFUND':
        return 'Remboursement';
      default:
        return type;
    }
  }

  /// Get transaction status display name
  String getTransactionStatusDisplayName(String status) {
    switch (status) {
      case 'PENDING':
        return 'En attente';
      case 'COMPLETED':
        return 'Terminée';
      case 'FAILED':
        return 'Échouée';
      default:
        return status;
    }
  }

  /// Get transaction status color
  Color getTransactionStatusColor(String status) {
    switch (status) {
      case 'PENDING':
        return Get.theme.colorScheme.secondary;
      case 'COMPLETED':
        return Get.theme.primaryColor;
      case 'FAILED':
        return Get.theme.colorScheme.error;
      default:
        return Get.theme.disabledColor;
    }
  }

  /// Show error snackbar
  void _showErrorSnackbar(String message) {
    Get.snackbar(
      'Erreur',
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: Get.theme.colorScheme.error,
      colorText: Get.theme.colorScheme.onError,
      duration: const Duration(seconds: 4),
    );
  }
}
