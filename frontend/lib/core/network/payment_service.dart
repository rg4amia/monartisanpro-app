import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
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

class Transaction {
  final int id;
  final int? projectId;
  final String transactionRef;
  final String type; // deposit, token_redemption, labor_release, refund, commission
  final double amount;
  final String? paymentMethod;
  final String status; // pending, completed, failed, cancelled
  final DateTime createdAt;

  Transaction({
    required this.id,
    this.projectId,
    required this.transactionRef,
    required this.type,
    required this.amount,
    this.paymentMethod,
    required this.status,
    required this.createdAt,
  });

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: json['id'] as int,
      projectId: json['project_id'] as int?,
      transactionRef: json['transaction_ref'] as String,
      type: json['type'] as String,
      amount: (json['amount'] as num).toDouble(),
      paymentMethod: json['payment_method'] as String?,
      status: json['status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  String get formattedAmount => '${amount.toStringAsFixed(0)} FCFA';

  String get typeLabel {
    switch (type) {
      case 'deposit':
        return 'Dépôt';
      case 'token_redemption':
        return 'Utilisation jeton';
      case 'labor_release':
        return 'Paiement main d\'œuvre';
      case 'refund':
        return 'Remboursement';
      case 'commission':
        return 'Commission';
      default:
        return type;
    }
  }
}
