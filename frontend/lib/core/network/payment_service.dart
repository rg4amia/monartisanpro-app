import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/escrow_model.dart';
import 'dio_client.dart';

class PaymentService {
  final Dio _dio = DioClient().dio;

  /// Initialize payment for a project
  Future<ApiResponse<PaymentInitResponse>> initializePayment({
    required int projectId,
    required int quoteId,
    required double amount,
  }) async {
    try {
      final response = await _dio.post('/payments/initialize', data: {
        'project_id': projectId,
        'quote_id': quoteId,
        'amount': amount,
      });

      return ApiResponse<PaymentInitResponse>.fromJson(
        response.data,
        (json) => PaymentInitResponse.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<PaymentInitResponse>.fromJson(
          e.response!.data,
          (json) => PaymentInitResponse.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Verify payment status
  Future<ApiResponse<PaymentStatus>> verifyPayment(String transactionId) async {
    try {
      final response = await _dio.get('/payments/verify/$transactionId');

      return ApiResponse<PaymentStatus>.fromJson(
        response.data,
        (json) => PaymentStatus.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<PaymentStatus>.fromJson(
          e.response!.data,
          (json) => PaymentStatus.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get payment history for a project
  Future<ApiResponse<List<Transaction>>> getProjectTransactions(
    int projectId,
  ) async {
    try {
      final response = await _dio.get('/payments/project/$projectId');

      return ApiResponse<List<Transaction>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((tx) => Transaction.fromJson(tx as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Transaction>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }
}

class PaymentInitResponse {
  final String paymentUrl;
  final String transactionId;
  final String paymentToken;

  PaymentInitResponse({
    required this.paymentUrl,
    required this.transactionId,
    required this.paymentToken,
  });

  factory PaymentInitResponse.fromJson(Map<String, dynamic> json) {
    return PaymentInitResponse(
      paymentUrl: json['payment_url'] as String,
      transactionId: json['transaction_id'] as String,
      paymentToken: json['payment_token'] as String,
    );
  }
}

class PaymentStatus {
  final String status; // pending, completed, failed, cancelled
  final String? transactionId;
  final double? amount;
  final String? paymentMethod;
  final DateTime? completedAt;

  PaymentStatus({
    required this.status,
    this.transactionId,
    this.amount,
    this.paymentMethod,
    this.completedAt,
  });

  factory PaymentStatus.fromJson(Map<String, dynamic> json) {
    return PaymentStatus(
      status: json['status'] as String,
      transactionId: json['transaction_id'] as String?,
      amount: json['amount'] != null ? (json['amount'] as num).toDouble() : null,
      paymentMethod: json['payment_method'] as String?,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'] as String)
          : null,
    );
  }

  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'completed';
  bool get isFailed => status == 'failed';
  bool get isCancelled => status == 'cancelled';
}
