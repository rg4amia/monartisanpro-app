/// Payment result entity
///
/// Requirements: 4.1, 15.4
class PaymentResult {
  final bool isSuccess;
  final String? sequestreId;
  final String? paymentUrl;
  final String? errorMessage;
  final Map<String, dynamic>? metadata;

  const PaymentResult({
    required this.isSuccess,
    this.sequestreId,
    this.paymentUrl,
    this.errorMessage,
    this.metadata,
  });

  /// Create success result
  factory PaymentResult.success({
    required String sequestreId,
    String? paymentUrl,
    Map<String, dynamic>? metadata,
  }) {
    return PaymentResult(
      isSuccess: true,
      sequestreId: sequestreId,
      paymentUrl: paymentUrl,
      metadata: metadata,
    );
  }

  /// Create error result
  factory PaymentResult.error(String errorMessage) {
    return PaymentResult(isSuccess: false, errorMessage: errorMessage);
  }

  /// Create from JSON
  factory PaymentResult.fromJson(Map<String, dynamic> json) {
    final status = json['status'] as String?;
    final isSuccess = status == 'success';

    if (isSuccess) {
      final data = json['data'] as Map<String, dynamic>?;
      return PaymentResult.success(
        sequestreId: data?['id'] as String? ?? '',
        paymentUrl: json['payment_url'] as String?,
        metadata: data,
      );
    } else {
      return PaymentResult.error(json['message'] as String? ?? 'Unknown error');
    }
  }
}
