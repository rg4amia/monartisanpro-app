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
}

// ─── Selfie Liveness Screen ──────────────────────────────────────────────────

class KycSelfieLivenessScreen extends StatefulWidget {
  const KycSelfieLivenessScreen({super.key});

  @override
  State<KycSelfieLivenessScreen> createState() =>
      _KycSelfieLivenessScreenState();
}

class _KycSelfieLivenessScreenState extends State<KycSelfieLivenessScreen>
    with SingleTickerProviderStateMixin {
  final _c = Get.find<AuthController>();
  final _picker = ImagePicker();

  AnimationController? _pulseCtrl;
  Animation<double>? _pulseAnim;
  final bool _isReady = false;

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 0.95, end: 1.05).animate(
      CurvedAnimation(parent: _pulseCtrl!, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _pulseCtrl?.dispose();
    super.dispose();
  }

  Future<void> _captureSelfie() async {
    unawaited(HapticFeedback.mediumImpact());
    try {
      final img = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
        preferredCameraDevice: CameraDevice.front,
      );
      if (img != null) {
        _c.selfiePath.value = img.path;

        // Upload selfie immediately
        await _c.uploadSelfie();

        // Navigate to main app if successful
        if (_c.errorMsg.value == null) {
          unawaited(Get.offAllNamed(Routes.mainTab));
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
        _c.selfiePath.value = img.path;

        // Upload selfie immediately
        await _c.uploadSelfie();

        // Navigate to main app if successful
        if (_c.errorMsg.value == null) {
          unawaited(Get.offAllNamed(Routes.mainTab));
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
                _buildFaceCircle(),
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

                _buildSecurityNote(),
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
              'Selfie en direct',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w900,
                color: _Dt.ink,
                letterSpacing: -0.5,
              ),
            ),
            Text(
              'Étape 2 sur 2',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: _Dt.muted,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: const LinearProgressIndicator(
            value: 1.0,
            minHeight: 6,
            backgroundColor: _Dt.border,
            valueColor: AlwaysStoppedAnimation<Color>(_Dt.primary),
          ),
        ),
      ],
    );
  }

  Widget _buildTitle() {
    return const Text(
      'Vérification faciale',
      style: TextStyle(
        fontSize: 28,
        fontWeight: FontWeight.w900,
        color: _Dt.ink,
        letterSpacing: -0.5,
      ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildSubtitle() {
    return const Text(
      'Positionnez votre visage dans le cercle pour confirmer votre identité',
      style: TextStyle(
        fontSize: 15,
        color: _Dt.muted,
        fontWeight: FontWeight.w500,
        height: 1.5,
      ),
      textAlign: TextAlign.center,
    );
  }

  Widget _buildFaceCircle() {
    return Obx(() {
      if (_c.selfiePath.value != null) {
        return _buildPreview();
      }

      return AnimatedBuilder(
        animation: _pulseAnim!,
        builder: (context, child) {
          return Transform.scale(
            scale: _pulseAnim!.value,
            child: child,
          );
        },
        child: Container(
          width: 280,
          height: 280,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: const Color(0xFFE8EDF5),
            border: Border.all(
              color: _Dt.primary,
              width: 4,
              strokeAlign: BorderSide.strokeAlignOutside,
            ),
          ),
          child: Stack(
            children: [
              // Phone illustration
              Center(
                child: Container(
                  width: 140,
                  height: 240,
                  decoration: BoxDecoration(
                    color: const Color(0xFF9CA3AF),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: const Color(0xFF6B7280),
                      width: 3,
                    ),
                  ),
                  child: Column(
                    children: [
                      // Phone notch
                      Container(
                        margin: const EdgeInsets.only(top: 8),
                        width: 60,
                        height: 6,
                        decoration: BoxDecoration(
                          color: const Color(0xFF4B5563),
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                      const SizedBox(height: 12),
                      // Screen with person silhouette
                      Expanded(
                        child: Container(
                          margin: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFD1D5DB),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Icon(
                              Icons.person,
                              size: 80,
                              color: Colors.white.withValues(alpha: 0.6),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // Ready badge
              if (_isReady)
                Positioned(
                  bottom: 20,
                  left: 0,
                  right: 0,
                  child: Center(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: _Dt.primary,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'PRÊT',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      );
    });
  }

  Widget _buildPreview() {
    return Container(
      width: 280,
      height: 280,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: _Dt.primary.withValues(alpha: 0.3),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipOval(
        child: Stack(
          fit: StackFit.expand,
          children: [
            Image.file(
              File(_c.selfiePath.value!),
              fit: BoxFit.cover,
            ),
            Positioned(
              top: 16,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF10B981),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.check_circle, color: Colors.white, size: 18),
                      SizedBox(width: 6),
                      Text(
                        'Vérifié',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
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
      final hasSelfie = _c.selfiePath.value != null;

      if (hasSelfie) {
        // Show "Start Scan" button when selfie is captured
        return SizedBox(
          height: 56,
          width: double.infinity,
          child: ElevatedButton(
            onPressed: isLoading ? null : () => Get.offAllNamed(Routes.mainTab),
            style: ElevatedButton.styleFrom(
              backgroundColor: _Dt.primary,
              foregroundColor: Colors.white,
              elevation: 4,
              shadowColor: _Dt.primary.withValues(alpha: 0.3),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: isLoading
                ? const SizedBox(
                    height: 22,
                    width: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : const Text(
                    'Terminer la vérification',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.3,
                    ),
                  ),
          ),
        );
      }

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
                onTap: isLoading ? null : _captureSelfie,
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
                        Icons.camera_enhance,
                        color: Colors.white,
                        size: 36,
                      ),
              ),
            ),
          ),

          const SizedBox(width: 16),

          // Switch camera button
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
                    // Toggle camera (implement if needed)
                    HapticFeedback.lightImpact();
                  },
                  borderRadius: BorderRadius.circular(16),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.cameraswitch_outlined,
                        color: _Dt.muted,
                        size: 28,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'INVERSER',
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

  Widget _buildSecurityNote() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF0FDF4),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: const Color(0xFF86EFAC),
          width: 1,
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.lock_outline,
            color: Color(0xFF16A34A),
            size: 20,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Vos données biométriques sont cryptées et sécurisées',
              style: TextStyle(
                fontSize: 13,
                color: _Dt.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
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
