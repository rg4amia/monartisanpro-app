import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/secondary_button.dart';
import '../controllers/otp_controller.dart';

/// OTP verification page
class OtpVerificationPage extends StatefulWidget {
  final String phoneNumber;
  final VoidCallback? onVerified;

  const OtpVerificationPage({
    super.key,
    required this.phoneNumber,
    this.onVerified,
  });

  @override
  State<OtpVerificationPage> createState() => _OtpVerificationPageState();
}

class _OtpVerificationPageState extends State<OtpVerificationPage> {
  final List<TextEditingController> _controllers = List.generate(
    6,
    (index) => TextEditingController(),
  );

  final List<FocusNode> _focusNodes = List.generate(6, (index) => FocusNode());

  @override
  void initState() {
    super.initState();
    // Auto-generate OTP on page load
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final otpController = Get.find<OtpController>();
      otpController.generateOtp(widget.phoneNumber);
    });
  }

  @override
  void dispose() {
    for (var controller in _controllers) {
      controller.dispose();
    }
    for (var node in _focusNodes) {
      node.dispose();
    }
    super.dispose();
  }

  String _getOtpCode() {
    return _controllers.map((c) => c.text).join();
  }

  Future<void> _handleVerify() async {
    final code = _getOtpCode();

    if (code.length != 6) {
      Get.snackbar(
        'Erreur',
        'Veuillez entrer le code complet',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
      return;
    }

    final otpController = Get.find<OtpController>();

    final verified = await otpController.verifyOtp(
      phoneNumber: widget.phoneNumber,
      code: code,
    );

    if (verified) {
      Get.snackbar(
        AppStrings.otpVerified,
        'Vérification réussie',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );

      if (widget.onVerified != null) {
        widget.onVerified!();
      } else {
        Get.back(result: true);
      }
    } else {
      Get.snackbar(
        'Erreur',
        otpController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  Future<void> _handleResend() async {
    final otpController = Get.find<OtpController>();

    final success = await otpController.generateOtp(widget.phoneNumber);

    if (success) {
      Get.snackbar(
        AppStrings.otpSent,
        'Un nouveau code a été envoyé',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );
    } else {
      Get.snackbar(
        'Erreur',
        otpController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final otpController = Get.find<OtpController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          AppStrings.otpVerification,
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
      ),
      body: SafeArea(
        child: Padding(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(height: AppSpacing.xl),

              // Icon
              Icon(Icons.sms, size: 80, color: AppColors.primary),

              SizedBox(height: AppSpacing.lg),

              // Title
              Text(
                AppStrings.enterOtp,
                style: AppTypography.headingMedium.copyWith(
                  color: AppColors.textPrimary,
                ),
                textAlign: TextAlign.center,
              ),

              SizedBox(height: AppSpacing.sm),

              // Subtitle
              Text(
                '${AppStrings.otpSentTo} ${widget.phoneNumber}',
                style: AppTypography.bodyMedium.copyWith(
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),

              SizedBox(height: AppSpacing.xl * 2),

              // OTP input fields
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: List.generate(6, (index) {
                  return SizedBox(
                    width: 45,
                    child: TextField(
                      controller: _controllers[index],
                      focusNode: _focusNodes[index],
                      textAlign: TextAlign.center,
                      keyboardType: TextInputType.number,
                      maxLength: 1,
                      style: AppTypography.headingMedium.copyWith(
                        color: AppColors.textPrimary,
                      ),
                      decoration: InputDecoration(
                        counterText: '',
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(
                            AppTheme.radiusMd,
                          ),
                          borderSide: BorderSide(color: AppColors.border),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(
                            AppTheme.radiusMd,
                          ),
                          borderSide: BorderSide(color: AppColors.border),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(
                            AppTheme.radiusMd,
                          ),
                          borderSide: BorderSide(
                            color: AppColors.primary,
                            width: 2,
                          ),
                        ),
                        filled: true,
                        fillColor: AppColors.surface,
                      ),
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      onChanged: (value) {
                        if (value.isNotEmpty && index < 5) {
                          _focusNodes[index + 1].requestFocus();
                        } else if (value.isEmpty && index > 0) {
                          _focusNodes[index - 1].requestFocus();
                        }

                        // Auto-verify when all digits entered
                        if (index == 5 && value.isNotEmpty) {
                          _handleVerify();
                        }
                      },
                    ),
                  );
                }),
              ),

              SizedBox(height: AppSpacing.xl),

              // Verify button
              Obx(
                () => PrimaryButton(
                  onPressed: otpController.isLoading.value
                      ? null
                      : _handleVerify,
                  text: AppStrings.verify,
                  isLoading: otpController.isLoading.value,
                  isFullWidth: true,
                ),
              ),

              SizedBox(height: AppSpacing.lg),

              // Resend button
              Obx(() {
                final countdown = otpController.resendCountdown.value;

                return SecondaryButton(
                  onPressed: countdown > 0 ? null : _handleResend,
                  text: countdown > 0
                      ? '${AppStrings.resendOtp} (${countdown}s)'
                      : AppStrings.resendOtp,
                  isFullWidth: true,
                );
              }),
            ],
          ),
        ),
      ),
    );
  }
}
