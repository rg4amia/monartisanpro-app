import 'package:get/get.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/verify_otp_usecase.dart';

/// Controller for OTP verification
class OtpController extends GetxController {
  final VerifyOtpUseCase _verifyOtpUseCase;
  final AuthRepository _authRepository;

  OtpController(this._verifyOtpUseCase, this._authRepository);

  // Observable state
  final RxBool isLoading = false.obs;
  final RxString errorMessage = ''.obs;
  final RxBool isVerified = false.obs;
  final RxInt resendCountdown = 0.obs;

  /// Generate OTP
  Future<bool> generateOtp(String phoneNumber) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      await _authRepository.generateOtp(phoneNumber: phoneNumber);

      // Start countdown for resend (60 seconds)
      startResendCountdown();

      return true;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Verify OTP
  Future<bool> verifyOtp({
    required String phoneNumber,
    required String code,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final verified = await _verifyOtpUseCase(
        phoneNumber: phoneNumber,
        code: code,
      );

      isVerified.value = verified;

      if (!verified) {
        errorMessage.value = 'Code OTP invalide';
      }

      return verified;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Start countdown for resend button
  void startResendCountdown() {
    resendCountdown.value = 60;

    Future.doWhile(() async {
      await Future.delayed(const Duration(seconds: 1));
      if (resendCountdown.value > 0) {
        resendCountdown.value--;
        return true;
      }
      return false;
    });
  }

  /// Clear error message
  void clearError() {
    errorMessage.value = '';
  }
}
