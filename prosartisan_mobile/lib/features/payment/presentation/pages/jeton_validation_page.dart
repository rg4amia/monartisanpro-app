import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_code_scanner/qr_code_scanner.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/secondary_button.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../controllers/jeton_validation_controller.dart';
import '../widgets/amount_input_field.dart';
import '../widgets/gps_status_indicator.dart';

/// Jeton validation screen for suppliers (scan QR, verify GPS)
///
/// Requirements: 5.3
class JetonValidationPage extends StatefulWidget {
  const JetonValidationPage({super.key});

  @override
  State<JetonValidationPage> createState() => _JetonValidationPageState();
}

class _JetonValidationPageState extends State<JetonValidationPage> {
  final GlobalKey qrKey = GlobalKey(debugLabel: 'QR');
  QRViewController? qrController;
  final JetonValidationController controller = Get.put(
    JetonValidationController(),
  );

  @override
  void dispose() {
    qrController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Validation Jeton',
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return Center(
            child: CircularProgressIndicator(color: AppColors.primary),
          );
        }

        return Column(
          children: [
            // GPS Status indicator
            Container(
              width: double.infinity,
              padding: EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: controller.hasGpsPermission.value
                    ? AppColors.success.withValues(alpha: 0.1)
                    : AppColors.error.withValues(alpha: 0.1),
                border: Border(
                  bottom: BorderSide(color: AppColors.border, width: 1),
                ),
              ),
              child: GPSStatusIndicator(
                hasPermission: controller.hasGpsPermission.value,
                accuracy: controller.gpsAccuracy.value,
              ),
            ),

            // QR Scanner or Manual input
            Expanded(
              flex: 3,
              child: controller.showManualInput.value
                  ? _buildManualInputSection()
                  : _buildQRScannerSection(),
            ),

            // Amount input section
            if (controller.scannedJetonCode.value.isNotEmpty)
              Expanded(flex: 2, child: _buildAmountInputSection()),

            // Action buttons
            Container(
              padding: EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: AppColors.surface,
                border: Border(
                  top: BorderSide(color: AppColors.border, width: 1),
                ),
              ),
              child: Column(
                children: [
                  // Toggle between QR and manual input
                  SecondaryButton(
                    onPressed: () => controller.toggleInputMethod(),
                    text: controller.showManualInput.value
                        ? 'Scanner QR Code'
                        : 'Saisie manuelle',
                    icon: controller.showManualInput.value
                        ? Icons.qr_code_scanner
                        : Icons.keyboard,
                  ),

                  SizedBox(height: AppSpacing.md),

                  // Validate button
                  if (controller.scannedJetonCode.value.isNotEmpty)
                    PrimaryButton(
                      onPressed: controller.canValidate
                          ? () => _validateJeton()
                          : null,
                      text: 'Valider le jeton',
                      isFullWidth: true,
                    ),
                ],
              ),
            ),
          ],
        );
      }),
    );
  }

  Widget _buildQRScannerSection() {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      child: Column(
        children: [
          Text(
            'Scannez le QR code du jeton',
            style: AppTypography.bodyLarge.copyWith(
              color: AppColors.textPrimary,
            ),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: AppSpacing.md),
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                border: Border.all(color: AppColors.border, width: 2),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                child: QRView(
                  key: qrKey,
                  onQRViewCreated: _onQRViewCreated,
                  overlay: QrScannerOverlayShape(
                    borderColor: AppColors.primary,
                    borderRadius: 10,
                    borderLength: 30,
                    borderWidth: 10,
                    cutOutSize: 250,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildManualInputSection() {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      child: Column(
        children: [
          Text(
            'Saisissez le code du jeton',
            style: AppTypography.bodyLarge.copyWith(
              color: AppColors.textPrimary,
            ),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: AppSpacing.lg),
          TextField(
            controller: controller.manualCodeController,
            style: AppTypography.bodyMedium.copyWith(
              color: AppColors.textPrimary,
            ),
            decoration: InputDecoration(
              labelText: 'Code du jeton (PA-XXXX)',
              labelStyle: AppTypography.bodyMedium.copyWith(
                color: AppColors.textSecondary,
              ),
              hintText: 'PA-1234',
              hintStyle: AppTypography.bodyMedium.copyWith(
                color: AppColors.textSecondary,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                borderSide: BorderSide(color: AppColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                borderSide: BorderSide(color: AppColors.border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                borderSide: BorderSide(color: AppColors.primary, width: 2),
              ),
              prefixIcon: Icon(
                Icons.confirmation_number,
                color: AppColors.textSecondary,
              ),
              filled: true,
              fillColor: AppColors.surface,
            ),
            textCapitalization: TextCapitalization.characters,
            onChanged: (value) => controller.setScannedCode(value),
          ),
        ],
      ),
    );
  }

  Widget _buildAmountInputSection() {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Montant à utiliser',
            style: AppTypography.headingSmall.copyWith(
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.md),

          // Jeton info display
          if (controller.jetonInfo.value != null)
            InfoCard(
              title: 'Code: ${controller.scannedJetonCode.value}',
              subtitle:
                  'Montant disponible: ${controller.jetonInfo.value!.remainingAmountFormatted}',
              icon: Icons.confirmation_number,
              backgroundColor: AppColors.info.withValues(alpha: 0.1),
              borderColor: AppColors.info,
            ),

          SizedBox(height: AppSpacing.md),

          // Amount input
          AmountInputField(
            controller: controller.amountController,
            labelText: 'Montant (FCFA)',
            maxAmount: controller.jetonInfo.value?.remainingAmountCentimes ?? 0,
            onChanged: (value) => controller.setAmount(value),
          ),
        ],
      ),
    );
  }

  void _onQRViewCreated(QRViewController controller) {
    qrController = controller;
    controller.scannedDataStream.listen((scanData) {
      if (scanData.code != null) {
        this.controller.setScannedCode(scanData.code!);
        qrController?.pauseCamera();
      }
    });
  }

  void _validateJeton() async {
    // Get current location
    try {
      Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      await controller.validateJeton(
        supplierLatitude: position.latitude,
        supplierLongitude: position.longitude,
      );

      // Show success message and navigate back
      Get.snackbar(
        'Validation réussie',
        'Le jeton a été validé avec succès',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.green,
        colorText: Colors.white,
        duration: const Duration(seconds: 3),
      );

      // Navigate back after delay
      Future.delayed(const Duration(seconds: 2), () {
        Get.back();
      });
    } catch (e) {
      Get.snackbar(
        'Erreur de localisation',
        'Impossible d\'obtenir votre position GPS',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }
}
