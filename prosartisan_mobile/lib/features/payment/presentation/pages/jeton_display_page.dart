import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../core/theme/app_shadows.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/icon_button.dart';
import '../controllers/jeton_controller.dart';
import '../widgets/jeton_info_card.dart';
import '../widgets/jeton_status_badge.dart';

/// Jeton display screen for artisans (show code and QR)
/// Updated with ProSartisan design system
///
/// Requirements: 5.1, 5.2
class JetonDisplayPage extends StatelessWidget {
  final String jetonId;

  const JetonDisplayPage({super.key, required this.jetonId});

  @override
  Widget build(BuildContext context) {
    final JetonController controller = Get.put(JetonController());

    // Load jeton details when page opens
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.loadJetonDetails(jetonId);
    });

    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Jeton Matériel',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: CustomIconButton(
          icon: Icons.arrow_back,
          onPressed: () => Get.back(),
          backgroundColor: AppColors.overlayLight,
          iconColor: AppColors.textPrimary,
        ),
        actions: [
          CustomIconButton(
            icon: Icons.refresh,
            onPressed: () => controller.loadJetonDetails(jetonId),
            backgroundColor: AppColors.overlayLight,
            iconColor: AppColors.textPrimary,
          ),
          const SizedBox(width: AppSpacing.base),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const CircularProgressIndicator(color: AppColors.accentPrimary),
                const SizedBox(height: AppSpacing.base),
                Text(
                  'Chargement du jeton...',
                  style: AppTypography.body.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          );
        }

        final jeton = controller.currentJeton.value;
        if (jeton == null) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 64, color: AppColors.textMuted),
                const SizedBox(height: AppSpacing.base),
                Text(
                  'Jeton non trouvé',
                  style: AppTypography.h4.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  'Vérifiez l\'ID du jeton et réessayez',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          );
        }

        return SingleChildScrollView(
          padding: AppSpacing.screenPaddingAll,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Jeton status
              JetonStatusBadge(status: jeton.status),

              const SizedBox(height: AppSpacing.xl),

              // QR Code Card
              Container(
                padding: AppSpacing.cardPaddingAll,
                decoration: BoxDecoration(
                  color: AppColors.cardBg,
                  borderRadius: AppRadius.cardRadius,
                  boxShadow: AppShadows.card,
                ),
                child: Column(
                  children: [
                    // QR Code with white background
                    Container(
                      padding: AppSpacing.all(AppSpacing.base),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: AppRadius.mediumRadius,
                      ),
                      child: QrImageView(
                        data: jeton.qrCodeData,
                        version: QrVersions.auto,
                        size: 200.0,
                        backgroundColor: Colors.white,
                      ),
                    ),

                    const SizedBox(height: AppSpacing.base),

                    // Code display
                    Text(
                      'Code: ${jeton.code}',
                      style: AppTypography.h3.copyWith(
                        color: AppColors.textPrimary,
                        fontWeight: FontWeight.bold,
                        fontFamily: 'monospace',
                      ),
                    ),

                    const SizedBox(height: AppSpacing.base),

                    // Copy button
                    SecondaryButton(
                      text: 'Copier le code',
                      icon: Icons.copy,
                      onPressed: () => _copyToClipboard(jeton.code),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: AppSpacing.xl),

              // Jeton information
              JetonInfoCard(jeton: jeton),

              const SizedBox(height: AppSpacing.xl),

              // Status message
              _buildStatusMessage(jeton),

              const SizedBox(height: AppSpacing.xl),

              // Generate new jeton button (if current is expired or fully used)
              if (jeton.isExpired || jeton.status == 'FULLY_USED')
                PrimaryButton(
                  text: 'Générer un nouveau jeton',
                  icon: Icons.add,
                  onPressed: () => controller.generateNewJeton(),
                  width: double.infinity,
                ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildStatusMessage(dynamic jeton) {
    if (jeton.isExpired) {
      return Container(
        padding: AppSpacing.cardPaddingAll,
        decoration: BoxDecoration(
          color: AppColors.accentDanger.withOpacity(0.1),
          borderRadius: AppRadius.cardRadius,
          border: Border.all(color: AppColors.accentDanger.withOpacity(0.3)),
        ),
        child: Row(
          children: [
            Icon(
              Icons.warning_rounded,
              color: AppColors.accentDanger,
              size: AppSpacing.iconSize,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Jeton expiré',
                    style: AppTypography.body.copyWith(
                      color: AppColors.accentDanger,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    'Les fonds non utilisés ont été retournés au séquestre.',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.accentDanger,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: AppSpacing.cardPaddingAll,
      decoration: BoxDecoration(
        color: AppColors.accentSuccess.withOpacity(0.1),
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.accentSuccess.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          Icon(
            Icons.info_rounded,
            color: AppColors.accentSuccess,
            size: AppSpacing.iconSize,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Jeton actif',
                  style: AppTypography.body.copyWith(
                    color: AppColors.accentSuccess,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  'Présentez ce code QR ou le code à votre fournisseur pour acheter des matériaux.',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.accentSuccess,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _copyToClipboard(String code) {
    Clipboard.setData(ClipboardData(text: code));
    Get.snackbar(
      'Code copié',
      'Le code du jeton a été copié dans le presse-papiers',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: AppColors.accentSuccess,
      colorText: Colors.white,
      duration: const Duration(seconds: 2),
      borderRadius: AppRadius.mediumRadius.topLeft.x,
      margin: AppSpacing.screenPaddingAll,
    );
  }
}
