import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
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
      appBar: AppBar(title: const Text(AppStrings.otpVerification)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 32),

              // Icon
              Icon(Icons.sms, size: 80, color: Theme.of(context).primaryColor),

              const SizedBox(height: 24),

              // Title
              Text(
                AppStrings.enterOtp,
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),

              const SizedBox(height: 8),

              // Subtitle
              Text(
                '${AppStrings.otpSentTo} ${widget.phoneNumber}',
                style: Theme.of(context).textTheme.bodyMedium,
                textAlign: TextAlign.center,
              ),

              const SizedBox(height: 48),

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
                      style: Theme.of(context).textTheme.headlineSmall,
                      decoration: InputDecoration(
                        counterText: '',
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
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

              const SizedBox(height: 32),

              // Verify button
              Obx(
                () => ElevatedButton(
                  onPressed: otpController.isLoading.value
                      ? null
                      : _handleVerify,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: otpController.isLoading.value
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              Colors.white,
                            ),
                          ),
                        )
                      : const Text(
                          AppStrings.verify,
                          style: TextStyle(fontSize: 16),
                        ),
                ),
              ),

              const SizedBox(height: 24),

              // Resend button
              Obx(() {
                final countdown = otpController.resendCountdown.value;

                return TextButton(
                  onPressed: countdown > 0 ? null : _handleResend,
                  child: Text(
                    countdown > 0
                        ? '${AppStrings.resendOtp} (${countdown}s)'
                        : AppStrings.resendOtp,
                  ),
                );
              }),
            ],
          ),
        ),
      ),
    );
  }
}
