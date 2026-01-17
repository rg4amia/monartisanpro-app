import '../../domain/entities/transaction.dart';

/// Transaction history result
class TransactionHistoryResult {
  final bool isSuccess;
  final List<Transaction> transactions;
  final bool hasMorePages;
  final String? errorMessage;

  const TransactionHistoryResult({
    required this.isSuccess,
    required this.transactions,
    required this.hasMorePages,
    this.errorMessage,
  });

  factory TransactionHistoryResult.success({
    required List<Transaction> transactions,
    required bool hasMorePages,
  }) {
    return TransactionHistoryResult(
      isSuccess: true,
      transactions: transactions,
      hasMorePages: hasMorePages,
    );
  }

  factory TransactionHistoryResult.error(String errorMessage) {
    return TransactionHistoryResult(
      isSuccess: false,
      transactions: [],
      hasMorePages: false,
      errorMessage: errorMessage,
    );
  }
}

/// Repository interface for transaction operations
///
/// Requirements: 4.6, 13.6
abstract class TransactionRepository {
  /// Get transaction history with pagination
  Future<TransactionHistoryResult> getTransactionHistory({
    required int page,
    required int limit,
    String? type,
  });
}

/// Implementation of TransactionRepository
class TransactionRepositoryImpl implements TransactionRepository {
  // This would typically use an HTTP client to call the API
  // For now, we'll create a mock implementation

  @override
  Future<TransactionHistoryResult> getTransactionHistory({
    required int page,
    required int limit,
    String? type,
  }) async {
    try {
      // Simulate API call delay
      await Future.delayed(const Duration(seconds: 1));

      // Mock transaction data
      final mockTransactions = _generateMockTransactions(page, limit, type);
      final hasMorePages = page < 3; // Mock pagination

      return TransactionHistoryResult.success(
        transactions: mockTransactions,
        hasMorePages: hasMorePages,
      );
    } catch (e) {
      return TransactionHistoryResult.error(
        'Failed to load transactions: ${e.toString()}',
      );
    }
  }

  List<Transaction> _generateMockTransactions(
    int page,
    int limit,
    String? type,
  ) {
    final transactions = <Transaction>[];
    final baseIndex = (page - 1) * limit;

    for (int i = 0; i < limit; i++) {
      final index = baseIndex + i;
      final transactionType = type ?? _getRandomTransactionType(index);

      transactions.add(
        Transaction(
          id: 'txn_${index + 1}',
          fromUserId: 'user_client',
          toUserId: 'user_artisan',
          amountCentimes: (50000 + (index * 10000)), // Varying amounts
          type: transactionType,
          status: _getRandomStatus(index),
          mobileMoneyReference: 'ref_${index + 1}',
          createdAt: DateTime.now()
              .subtract(Duration(days: index))
              .toIso8601String(),
          completedAt: index % 3 == 0
              ? DateTime.now()
                    .subtract(Duration(days: index))
                    .add(const Duration(hours: 1))
                    .toIso8601String()
              : null,
        ),
      );
    }

    return transactions;
  }

  String _getRandomTransactionType(int index) {
    final types = [
      'ESCROW_BLOCK',
      'MATERIAL_RELEASE',
      'LABOR_RELEASE',
      'REFUND',
    ];
    return types[index % types.length];
  }

  String _getRandomStatus(int index) {
    final statuses = ['COMPLETED', 'PENDING', 'FAILED'];
    return statuses[index % statuses.length];
  }
}
