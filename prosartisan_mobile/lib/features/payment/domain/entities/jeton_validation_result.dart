/// Jeton validation result entity
///
/// Requirements: 5.3
class JetonValidationResult {
  final bool isSuccess;
  final String? validationId;
  final int amountUsed;
  final int remainingAmount;
  final String? validatedAt;
  final String? errorMessage;

  const JetonValidationResult({
    required this.isSuccess,
    this.validationId,
    required this.amountUsed,
    required this.remainingAmount,
    this.validatedAt,
    this.errorMessage,
  });

  /// Create success result
  factory JetonValidationResult.success({
    required String validationId,
    required int amountUsed,
    required int remainingAmount,
    required String validatedAt,
  }) {
    return JetonValidationResult(
      isSuccess: true,
      validationId: validationId,
      amountUsed: amountUsed,
      remainingAmount: remainingAmount,
      validatedAt: validatedAt,
    );
  }

  /// Create error result
  factory JetonValidationResult.error(String errorMessage) {
    return JetonValidationResult(
      isSuccess: false,
      amountUsed: 0,
      remainingAmount: 0,
      errorMessage: errorMessage,
    );
  }

  /// Create from JSON
  factory JetonValidationResult.fromJson(Map<String, dynamic> json) {
    final status = json['status'] as String?;
    final isSuccess = status == 'success';

    if (isSuccess) {
      final data = json['data'] as Map<String, dynamic>?;
      return JetonValidationResult.success(
        validationId: data?['validation_id'] as String? ?? '',
        amountUsed: data?['amount_used'] as int? ?? 0,
        remainingAmount: data?['remaining_amount'] as int? ?? 0,
        validatedAt: data?['validated_at'] as String? ?? '',
      );
    } else {
      return JetonValidationResult.error(
        json['message'] as String? ?? 'Unknown error',
      );
    }
  }
}
