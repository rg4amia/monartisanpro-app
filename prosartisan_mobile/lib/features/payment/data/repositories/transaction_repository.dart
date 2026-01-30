import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';
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

  /// Get transaction by ID
  Future<Transaction?> getTransactionById(String transactionId);
}

/// Implementation of TransactionRepository
class TransactionRepositoryImpl implements TransactionRepository {
  final ApiClient _apiClient;

  TransactionRepositoryImpl(this._apiClient);

  @override
  Future<TransactionHistoryResult> getTransactionHistory({
    required int page,
    required int limit,
    String? type,
  }) async {
    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'limit': limit,
      };

      if (type != null) {
        queryParams['type'] = type;
      }

      final response = await _apiClient.get(
        ApiConstants.transactions,
        queryParameters: queryParams,
      );

      // Check if response data is null
      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      // Check if response data is a Map
      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;

      // Extract the 'data' field (Laravel pagination format)
      final data = responseData.containsKey('data')
          ? responseData['data']
          : responseData;

      // Handle pagination metadata
      final meta = responseData['meta'] as Map<String, dynamic>?;
      final hasMorePages = meta != null
          ? (meta['current_page'] as int) < (meta['last_page'] as int)
          : false;

      // Parse transactions
      final List<Transaction> transactions;
      if (data is List) {
        transactions = data
            .map((json) => Transaction.fromJson(json as Map<String, dynamic>))
            .toList();
      } else {
        throw Exception('Invalid transactions data format');
      }

      return TransactionHistoryResult.success(
        transactions: transactions,
        hasMorePages: hasMorePages,
      );
    } on DioException catch (e) {
      final errorMessage = _handleError(e);
      return TransactionHistoryResult.error(errorMessage);
    } catch (e) {
      return TransactionHistoryResult.error(
        'Failed to load transactions: ${e.toString()}',
      );
    }
  }

  @override
  Future<Transaction?> getTransactionById(String transactionId) async {
    try {
      final path = ApiConstants.transactionById.replaceAll('{id}', transactionId);
      final response = await _apiClient.get(path);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Transaction.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      throw Exception(_handleError(e));
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to get transaction: ${e.toString()}');
    }
  }

  /// Handle Dio errors
  String _handleError(DioException error) {
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return 'Connection timeout. Please check your internet connection.';
    }

    if (error.type == DioExceptionType.connectionError) {
      return 'Network error. Please check your internet connection.';
    }

    if (error.response != null) {
      final statusCode = error.response!.statusCode;
      final data = error.response!.data;

      if (statusCode == 401) {
        return 'Unauthorized. Please log in again.';
      }

      if (statusCode == 403) {
        return 'Access denied.';
      }

      if (statusCode == 404) {
        return 'Transaction not found.';
      }

      if (statusCode == 500) {
        return 'Server error. Please try again later.';
      }

      if (data is Map<String, dynamic> && data.containsKey('message')) {
        return data['message'];
      }

      return 'Server error: $statusCode';
    }

    return 'Unknown error occurred. Please try again.';
  }
}
