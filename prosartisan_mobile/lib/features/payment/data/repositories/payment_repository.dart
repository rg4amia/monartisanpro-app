import '../../domain/entities/payment_result.dart';

/// Repository interface for payment operations
///
/// Requirements: 4.1, 15.4
abstract class PaymentRepository {
  /// Block funds in escrow after quote acceptance
  Future<PaymentResult> blockEscrowFunds({
    required String missionId,
    required String devisId,
    required int totalAmountCentimes,
  });
}

/// Implementation of PaymentRepository
class PaymentRepositoryImpl implements PaymentRepository {
  // This would typically use an HTTP client to call the API
  // For now, we'll create a mock implementation

  @override
  Future<PaymentResult> blockEscrowFunds({
    required String missionId,
    required String devisId,
    required int totalAmountCentimes,
  }) async {
    try {
      // Simulate API call delay
      await Future.delayed(const Duration(seconds: 2));

      // Mock successful response
      return PaymentResult.success(
        sequestreId: 'seq_${DateTime.now().millisecondsSinceEpoch}',
        paymentUrl: 'https://wave.com/pay/mock-payment-url',
        metadata: {
          'mission_id': missionId,
          'devis_id': devisId,
          'total_amount': totalAmountCentimes,
        },
      );
    } catch (e) {
      return PaymentResult.error(
        'Failed to block escrow funds: ${e.toString()}',
      );
    }
  }
}
