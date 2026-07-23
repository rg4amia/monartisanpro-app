import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/gestures.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/config/env_config.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = AppColors.primary;
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const border = AppColors.border;
}

// ─── OTP Verification Screen ─────────────────────────────────────────────────

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({super.key});

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  final _c = Get.find<AuthController>();

  final List<TextEditingController> _otpCtrl =
      List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _otpFocus = List.generate(4, (_) => FocusNode());

  String? _phone;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>?;
    _phone = args?['phone'] as String?;

    // Auto-focus first field
    Future.delayed(const Duration(milliseconds: 300), () {
      if (mounted) _otpFocus[0].requestFocus();
    });
  }

  @override
  void dispose() {
    for (final c in _otpCtrl) {
      c.dispose();
    }
    for (final f in _otpFocus) {
      f.dispose();
    }
    super.dispose();
  }

  void _onOtpKey(String v, int i) {
    if (v.length == 1 && i < 3) {
      _otpFocus[i + 1].requestFocus();
    }
    if (v.isEmpty && i > 0) {
      _otpFocus[i - 1].requestFocus();
    }
    _c.otp.value = _otpCtrl.map((c) => c.text).join();
    if (_c.otp.value.length == 4) {
      HapticFeedback.mediumImpact();
    }
  }

  void _handleVerify() async {
    if (_c.otp.value.length != 4) return;

    HapticFeedback.mediumImpact();

    // Verify OTP
    final hasCompletedProfile = await _c.verifyOtp();

    // If verification successful
    if (_c.errorMsg.value == null) {
      if (hasCompletedProfile) {
        final user = _c.currentUser.value;
        if (user != null && user.cguAcceptedAt == null) {
          _showCguModal();
        } else {
          Get.offAllNamed(Routes.mainTab);
        }
      } else {
        // User needs to complete profile first - use toNamed to keep controller alive
        Get.toNamed(Routes.register);
      }
    }
  }

  void _showCguModal() {
    Get.dialog(
      PopScope(
        canPop: false,
        child: AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text(
            'Mise à jour requise',
            style: TextStyle(fontWeight: FontWeight.w800, color: _Dt.ink),
          ),
          content: RichText(
            text: TextSpan(
              style: const TextStyle(
                fontSize: 14,
                color: _Dt.ink,
                fontWeight: FontWeight.w500,
                height: 1.5,
              ),
              children: [
                const TextSpan(text: 'Pour continuer à utiliser ProsArtisan, vous devez accepter nos nouvelles '),
                TextSpan(
                  text: 'Conditions Générales d\'Utilisation',
                  style: const TextStyle(
                    color: _Dt.primary,
                    fontWeight: FontWeight.w700,
                    decoration: TextDecoration.underline,
                  ),
                  recognizer: TapGestureRecognizer()
                    ..onTap = () async {
                      final url = Uri.parse(EnvConfig.baseUrl.replaceAll('/api/v1', '/cgu'));
                      if (await canLaunchUrl(url)) {
                        await launchUrl(url, mode: LaunchMode.externalApplication);
                      }
                    },
                ),
                const TextSpan(text: '.'),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Get.back();
                _c.errorMsg.value = null;
              },
              child: const Text('Plus tard', style: TextStyle(color: _Dt.muted, fontWeight: FontWeight.w600)),
            ),
            Obx(() => ElevatedButton(
              onPressed: _c.isLoading.value ? null : () async {
                final success = await _c.acceptCgu();
                if (success) {
                  Get.back(); // close modal
                  Get.offAllNamed(Routes.mainTab);
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: _Dt.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                elevation: 0,
              ),
              child: _c.isLoading.value
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('J\'accepte', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
          ],
        ),
      ),
      barrierDismissible: false,
    );
  }

  void _handleResend() async {
    HapticFeedback.lightImpact();

    // Clear OTP fields
    for (final c in _otpCtrl) {
      c.clear();
    }
    _c.otp.value = '';
    _c.errorMsg.value = null;

    // Resend OTP
    await _c.sendOtp();

    if (_c.errorMsg.value == null) {
      // Show success message
      Get.snackbar(
        'Code envoyé',
        'Un nouveau code OTP a été envoyé',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 2),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: _Dt.bg,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: _Dt.ink),
            onPressed: () {
              _c.otpSent.value = false;
              _c.otp.value = '';
              _c.errorMsg.value = null;
              Get.back();
            },
          ),
          title: const Text(
            'Vérification OTP',
            style: TextStyle(
              color: _Dt.ink,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          centerTitle: true,
        ),
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 20),
                _buildHeader(),
                const SizedBox(height: 40),
                _buildOtpFields(),
                const SizedBox(height: 32),

                // Error message
                Obx(() {
                  if (_c.errorMsg.value == null) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: _buildError(_c.errorMsg.value!),
                  );
                }),

                _buildVerifyButton(),
                const SizedBox(height: 24),
                _buildResendSection(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Column(
      children: [
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: _Dt.primary.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.sms_outlined,
            color: _Dt.primary,
            size: 40,
          ),
        ),
        const SizedBox(height: 24),
        const Text(
          'Entrez le code de vérification',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w900,
            color: _Dt.ink,
            letterSpacing: -0.5,
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        Text(
          'Nous avons envoyé un code à 4 chiffres au\n${_phone ?? 'votre numéro'}',
          style: TextStyle(
            fontSize: 14,
            color: _Dt.muted,
            fontWeight: FontWeight.w500,
            height: 1.5,
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildOtpFields() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
      children: List.generate(4, (i) => _buildOtpBox(i)),
    );
  }

  Widget _buildOtpBox(int i) {
    return SizedBox(
      width: 68,
      height: 72,
      child: TextFormField(
        controller: _otpCtrl[i],
        focusNode: _otpFocus[i],
        onChanged: (v) => _onOtpKey(v, i),
        keyboardType: TextInputType.number,
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(1),
        ],
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontSize: 30,
          fontWeight: FontWeight.w900,
          color: _Dt.primary,
          letterSpacing: 0,
        ),
        decoration: InputDecoration(
          counterText: '',
          contentPadding: EdgeInsets.zero,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.border, width: 2),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.border, width: 2),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.primary, width: 2.5),
          ),
          fillColor: _Dt.surface,
          filled: true,
        ),
      ),
    );
  }

  Widget _buildVerifyButton() {
    return Obx(() {
      final canVerify = _c.otp.value.length == 4;

      return SizedBox(
        width: double.infinity,
        height: 56,
        child: ElevatedButton(
          onPressed:
              canVerify ? (_c.isLoading.value ? null : _handleVerify) : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: canVerify ? _Dt.primary : _Dt.border,
            foregroundColor: Colors.white,
            disabledBackgroundColor: _Dt.border,
            disabledForegroundColor: _Dt.muted,
            elevation: canVerify ? 4 : 0,
            shadowColor: canVerify
                ? _Dt.primary.withValues(alpha: 0.3)
                : Colors.transparent,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          child: _c.isLoading.value
              ? const SizedBox(
                  height: 22,
                  width: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    color: Colors.white,
                  ),
                )
              : const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Vérifier le code',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3,
                      ),
                    ),
                    SizedBox(width: 8),
                    Icon(Icons.check_circle_outline, size: 20),
                  ],
                ),
        ),
      );
    });
  }

  Widget _buildResendSection() {
    return Column(
      children: [
        const Text(
          'Vous n\'avez pas reçu le code ?',
          style: TextStyle(
            fontSize: 14,
            color: _Dt.muted,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 8),
        TextButton.icon(
          onPressed: _c.isLoading.value ? null : _handleResend,
          icon: const Icon(Icons.refresh_rounded, size: 18),
          label: const Text(
            'Renvoyer le code',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
          style: TextButton.styleFrom(
            foregroundColor: _Dt.primary,
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          ),
        ),
      ],
    );
  }

  Widget _buildError(String msg) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFECACA), width: 1.5),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: const BoxDecoration(
              color: Color(0xFFFEE2E2),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.error_outline_rounded,
              color: Color(0xFFB91C1C),
              size: 17,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              msg,
              style: const TextStyle(
                color: Color(0xFF7F1D1D),
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
