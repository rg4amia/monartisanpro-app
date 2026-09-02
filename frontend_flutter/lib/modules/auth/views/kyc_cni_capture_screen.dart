import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = AppColors.primary;
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const border = AppColors.border;
  static const cardBg = AppColors.primaryDark;
  static const warning = AppColors.warning;
}

// ─── CNI Capture Screen ──────────────────────────────────────────────────────

class KycCniCaptureScreen extends StatefulWidget {
  const KycCniCaptureScreen({super.key});

  @override
  State<KycCniCaptureScreen> createState() => _KycCniCaptureScreenState();
}

class _KycCniCaptureScreenState extends State<KycCniCaptureScreen> {
  final _c = Get.find<AuthController>();
  final _picker = ImagePicker();

  Future<void> _captureImage() async {
    unawaited(HapticFeedback.mediumImpact());
    try {
      final img = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
        preferredCameraDevice: CameraDevice.rear,
      );
      if (img != null) {
        _c.cniPath.value = img.path;

        // Upload CNI immediately
        await _c.uploadCni();

        // Navigate to selfie screen if successful
        if (_c.errorMsg.value == null) {
          unawaited(Get.toNamed(Routes.kycSelfie));
        }
      }
    } catch (e) {
      _c.errorMsg.value = 'Erreur lors de la capture: ${e.toString()}';
    }
  }

  Future<void> _pickFromGallery() async {
    unawaited(HapticFeedback.lightImpact());
    try {
      final img = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );
      if (img != null) {
        _c.cniPath.value = img.path;

        // Small delay to ensure token is saved
        await Future.delayed(const Duration(milliseconds: 500));

        // Upload CNI immediately
        await _c.uploadCni();

        // Navigate to selfie screen if successful
        if (_c.errorMsg.value == null) {
          unawaited(Get.toNamed(Routes.kycSelfie));
        }
      }
    } catch (e) {
      _c.errorMsg.value = 'Erreur lors de la sélection: ${e.toString()}';
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
            onPressed: () => Get.back(),
          ),
          title: const Text(
            'Vérification d\'identité',
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
                _buildProgress(),
                const SizedBox(height: 32),
                _buildTitle(),
                const SizedBox(height: 12),
                _buildSubtitle(),
                const SizedBox(height: 40),
                _buildCaptureFrame(),
                const SizedBox(height: 40),
                _buildActionButtons(),
                const SizedBox(height: 24),

                // Error message
                Obx(() {
                  if (_c.errorMsg.value == null) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: _buildError(_c.errorMsg.value!),
                  );
                }),

                _buildPrivacyNote(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildProgress() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'ÉTAPE 1 SUR 2',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: _Dt.primary,
                letterSpacing: 1.2,
              ),
            ),
            Text(
              '50%',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: _Dt.muted,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        const Text(
          'Capture de la CNI',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w900,
            color: _Dt.ink,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 16),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: 0.5,
            minHeight: 6,
            backgroundColor: _Dt.border,
            valueColor: const AlwaysStoppedAnimation<Color>(_Dt.primary),
          ),
        ),
      ],
    );
  }

  Widget _buildTitle() {
    return const Text(
      'Alignez votre carte CNI dans le cadre et assurez-vous que tous les détails sont clairement visibles.',
      style: TextStyle(
        fontSize: 15,
        color: _Dt.muted,
        fontWeight: FontWeight.w500,
        height: 1.5,
      ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildSubtitle() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: _Dt.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(8),
      ),
      child: const Text(
        'Assurez-vous que votre CNI est valide et non expirée',
        style: TextStyle(
          fontSize: 13,
          color: _Dt.primary,
          fontWeight: FontWeight.w600,
        ),
        textAlign: TextAlign.center,
      ),
    );
  }

  Widget _buildCaptureFrame() {
    return Obx(() {
      if (_c.cniPath.value != null) {
        return _buildPreview();
      }

      return Container(
        height: 240,
        decoration: BoxDecoration(
          color: _Dt.cardBg,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 20,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Stack(
          children: [
            // Frame overlay
            Positioned.fill(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: _Dt.primary,
                      width: 3,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.credit_card,
                        size: 48,
                        color: Colors.white.withValues(alpha: 0.5),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'PLACEZ LA CNI\nICI',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: Colors.white.withValues(alpha: 0.5),
                          letterSpacing: 1.2,
                          height: 1.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Tip banner
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.7),
                  borderRadius: const BorderRadius.only(
                    bottomLeft: Radius.circular(20),
                    bottomRight: Radius.circular(20),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.lightbulb_outline,
                      color: _Dt.warning,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    const Expanded(
                      child: Text(
                        'Conseil : Évitez les reflets et utilisez un bon éclairage',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    });
  }

  Widget _buildPreview() {
    return Container(
      height: 240,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: _Dt.primary.withValues(alpha: 0.2),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Stack(
          fit: StackFit.expand,
          children: [
            Image.file(
              File(_c.cniPath.value!),
              fit: BoxFit.cover,
            ),
            Positioned(
              top: 12,
              right: 12,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFF10B981),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.check_circle, color: Colors.white, size: 16),
                    SizedBox(width: 4),
                    Text(
                      'Capturé',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButtons() {
    return Obx(() {
      final isLoading = _c.isLoading.value;

      return Row(
        children: [
          // Gallery button
          Expanded(
            child: Container(
              height: 72,
              decoration: BoxDecoration(
                color: _Dt.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: _Dt.border, width: 1.5),
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: isLoading ? null : _pickFromGallery,
                  borderRadius: BorderRadius.circular(16),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.photo_library_outlined,
                        color: _Dt.muted,
                        size: 28,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'GALERIE',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: _Dt.muted,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),

          const SizedBox(width: 16),

          // Camera button (primary)
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: _Dt.primary,
              boxShadow: [
                BoxShadow(
                  color: _Dt.primary.withValues(alpha: 0.4),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: isLoading ? null : _captureImage,
                customBorder: const CircleBorder(),
                child: isLoading
                    ? const Center(
                        child: SizedBox(
                          width: 28,
                          height: 28,
                          child: CircularProgressIndicator(
                            strokeWidth: 3,
                            color: Colors.white,
                          ),
                        ),
                      )
                    : const Icon(
                        Icons.camera_alt,
                        color: Colors.white,
                        size: 36,
                      ),
              ),
            ),
          ),

          const SizedBox(width: 16),

          // Flash button
          Expanded(
            child: Container(
              height: 72,
              decoration: BoxDecoration(
                color: _Dt.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: _Dt.border, width: 1.5),
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () {
                    // Toggle flash (implement if needed)
                    HapticFeedback.lightImpact();
                  },
                  borderRadius: BorderRadius.circular(16),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.flash_off_outlined,
                        color: _Dt.muted,
                        size: 28,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'FLASH',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: _Dt.muted,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      );
    });
  }

  Widget _buildPrivacyNote() {
    return RichText(
      textAlign: TextAlign.center,
      text: TextSpan(
        style: TextStyle(
          fontSize: 12,
          color: _Dt.muted,
          height: 1.5,
        ),
        children: const [
          TextSpan(text: 'En continuant, vous acceptez la '),
          TextSpan(
            text: 'Politique de confidentialité',
            style: TextStyle(
              color: _Dt.primary,
              fontWeight: FontWeight.w700,
              decoration: TextDecoration.underline,
            ),
          ),
          TextSpan(
            text: ' de ProsArtisan concernant le traitement des documents.',
          ),
        ],
      ),
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
