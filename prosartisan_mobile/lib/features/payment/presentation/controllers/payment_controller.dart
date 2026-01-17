import 'package:get/get.dart';
import '../../data/repositories/payment_repository.dart';
import '../../domain/entities/payment_result.dart';

/// Controller for payment initiation
///
/// Requirements: 4.1, 15.2
class PaymentController extends GetxController {
  final PaymentRepository _paymentRepository = Get.find<PaymentRepository>();

  final RxBool isLoading = false.obs;
  final RxString errorMessage = ''.obs;

  /// Initiate escrow payment after quote acceptance
  Future<void> initiateEscrowPayment({
    required String missionId,
    required String devisId,
    required int totalAmountCentimes,
    required String provider,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final result = await _paymentRepository.blockEscrowFunds(
        missionId: missionId,
        devisId: devisId,
        totalAmountCentimes: totalAmountCentimes,
      );

      if (result.isSuccess) {
        // Navigate to mobile money provider
        await _redirectToMobileMoneyProvider(provider, result);
      } else {
        errorMessage.value = result.errorMessage ?? 'Erreur lors du paiement';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      errorMessage.value = 'Erreur de connexion';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoading.value = false;
    }
  }

  /// Redirect to mobile money provider for payment
  Future<void> _redirectToMobileMoneyProvider(
    String provider,
    PaymentResult result,
  ) async {
    try {
      // Get payment URL from result
      final paymentUrl = result.paymentUrl;

      if (paymentUrl != null) {
        // Open mobile money payment page
        // This would typically use url_launcher or webview
        Get.snackbar(
          'Redirection',
          'Redirection vers $provider pour le paiement...',
          snackPosition: SnackPosition.BOTTOM,
          duration: const Duration(seconds: 2),
        );

        // TODO: Implement actual redirection to mobile money provider
        // await launch(paymentUrl);

        // For now, simulate successful payment after delay
        await Future.delayed(const Duration(seconds: 3));
        _handlePaymentSuccess(result);
      } else {
        throw Exception('URL de paiement non disponible');
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la redirection vers $provider';
      _showErrorSnackbar(errorMessage.value);
    }
  }

  /// Handle successful payment
  void _handlePaymentSuccess(PaymentResult result) {
    Get.snackbar(
      'Paiement réussi',
      'Les fonds ont été bloqués dans le séquestre',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: Get.theme.primaryColor,
      colorText: Get.theme.colorScheme.onPrimary,
      duration: const Duration(seconds: 3),
    );

    // Navigate back to mission details or next step
    Get.back(result: {'success': true, 'sequestreId': result.sequestreId});
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
