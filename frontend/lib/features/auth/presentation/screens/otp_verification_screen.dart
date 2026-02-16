import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'dart:async';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/auth_controller.dart';
import 'kyc_upload_screen.dart';

class OtpVerificationScreen extends StatefulWidget {
  final String phone;

  const OtpVerificationScreen({
    super.key,
    required this.phone,
  });

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  final _authController = Get.find<AuthController>();
  final List<TextEditingController> _otpControllers = List.generate(
    AppConstants.otpLength,
    (index) => TextEditingController(),
  );
  final List<FocusNode> _focusNodes = List.generate(
    AppConstants.otpLength,
    (index) => FocusNode(),
  );

  int _resendCountdown = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startResendCountdown();
  }

  @override
  void dispose() {
    for (var controller in _otpControllers) {
      controller.dispose();
    }
    for (var node in _focusNodes) {
      node.dispose();
    }
    _timer?.cancel();
    super.dispose();
  }

  void _startResendCountdown() {
    setState(() => _resendCountdown = AppConstants.otpResendDelay.inSeconds);
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      setState(() {
        if (_resendCountdown > 0) {
          _resendCountdown--;
        } else {
          timer.cancel();
        }
      });
    });
  }

  Future<void> _handleVerifyOtp() async {
    final otp = _otpControllers.map((c) => c.text).join();

    if (otp.length != AppConstants.otpLength) {
      Get.snackbar(
        'Erreur',
        'Veuillez entrer le code à ${AppConstants.otpLength} chiffres',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final success = await _authController.verifyOtp(
      phone: widget.phone,
      otp: otp,
    );

    if (success) {
      Get.snackbar(
        'Succès',
        'Téléphone vérifié avec succès!',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );

      // Navigate based on user role
      final user = _authController.currentUser.value;
      if (user != null && (user.role == 'artisan' || user.role == 'fournisseur')) {
        // Artisans and vendors need KYC verification
        Get.off(() => const KycUploadScreen());
      } else {
        // Clients can proceed to home
        Get.offAllNamed('/home');
      }
    } else {
      Get.snackbar(
        'Erreur',
        _authController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      // Clear OTP fields on error
      for (var controller in _otpControllers) {
        controller.clear();
      }
      _focusNodes[0].requestFocus();
    }
  }

  Future<void> _handleResendOtp() async {
    if (_resendCountdown > 0) return;

    final success = await _authController.sendOtp(widget.phone);

    if (success) {
      Get.snackbar(
        'Succès',
        'Code de vérification renvoyé!',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      _startResendCountdown();
      // Clear existing OTP
      for (var controller in _otpControllers) {
        controller.clear();
      }
      _focusNodes[0].requestFocus();
    } else {
      Get.snackbar(
        'Erreur',
        _authController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Vérification du téléphone'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Spacing.xxl),

              // Icon
              Icon(
                Icons.smartphone_outlined,
                size: 80,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(height: Spacing.xl),

              // Title
              Text(
                'Entrez le code',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.md),

              // Description
              Text(
                'Un code à ${AppConstants.otpLength} chiffres a été envoyé au',
                style: Theme.of(context).textTheme.bodyMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.xs),
              Text(
                widget.phone,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.xxxl),

              // OTP Input Fields
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: List.generate(
                  AppConstants.otpLength,
                  (index) => _buildOtpField(index),
                ),
              ),
              const SizedBox(height: Spacing.xxxl),

              // Verify Button
              Obx(() => ElevatedButton(
                    onPressed: _authController.isLoading.value
                        ? null
                        : _handleVerifyOtp,
                    child: _authController.isLoading.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor:
                                  AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text('Vérifier'),
                  )),
              const SizedBox(height: Spacing.xl),

              // Resend OTP
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Code non reçu? ',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (_resendCountdown > 0)
                    Text(
                      'Renvoyer dans ${_resendCountdown}s',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: AppColors.lightTextSecondary,
                          ),
                    )
                  else
                    TextButton(
                      onPressed: _handleResendOtp,
                      child: const Text('Renvoyer le code'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOtpField(int index) {
    return SizedBox(
      width: 50,
      child: TextFormField(
        controller: _otpControllers[index],
        focusNode: _focusNodes[index],
        keyboardType: TextInputType.number,
        textAlign: TextAlign.center,
        maxLength: 1,
        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.bold,
            ),
        decoration: InputDecoration(
          counterText: '',
          contentPadding: const EdgeInsets.symmetric(vertical: Spacing.md),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            borderSide: BorderSide(
              color: AppColors.lightTextTertiary,
              width: 1.5,
            ),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            borderSide: BorderSide(
              color: Theme.of(context).colorScheme.primary,
              width: 2,
            ),
          ),
        ),
        onChanged: (value) {
          if (value.isNotEmpty) {
            // Move to next field
            if (index < AppConstants.otpLength - 1) {
              _focusNodes[index + 1].requestFocus();
            } else {
              // Last field filled, unfocus to show keyboard dismiss
              _focusNodes[index].unfocus();
              // Optionally auto-submit
              _handleVerifyOtp();
            }
          } else if (value.isEmpty && index > 0) {
            // Move to previous field on backspace
            _focusNodes[index - 1].requestFocus();
          }
        },
      ),
    );
  }
}
