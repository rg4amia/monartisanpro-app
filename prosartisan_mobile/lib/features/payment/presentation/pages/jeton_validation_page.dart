import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/buttons/primary_button.dart'
    show PrimaryButton, SecondaryButton;
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
  MobileScannerController? scannerController;
  final JetonValidationController controller = Get.put(
    JetonValidationController(),
  );

  @override
  void initState() {
    super.initState();
    scannerController = MobileScannerController();
  }

  @override
  void dispose() {
    scannerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Validation Jeton',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: AppColors.accentPrimary,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return Center(
            child: CircularProgressIndicator(color: AppColors.accentPrimary),
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
                    ? AppColors.accentSuccess.withValues(alpha: 0.1)
                    : AppColors.accentDanger.withValues(alpha: 0.1),
                border: Border(
                  bottom: BorderSide(color: AppColors.overlayMedium, width: 1),
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
                color: AppColors.cardBg,
                border: Border(
                  top: BorderSide(color: AppColors.overlayMedium, width: 1),
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
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: AppSpacing.md),
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(color: AppColors.overlayMedium, width: 2),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.md),
                child: MobileScanner(
                  controller: scannerController,
                  onDetect: (BarcodeCapture capture) {
                    final List<Barcode> barcodes = capture.barcodes;
                    for (final barcode in barcodes) {
                      if (barcode.rawValue != null) {
                        controller.setScannedCode(barcode.rawValue!);
                        scannerController?.stop();
                        break;
                      }
                    }
                  },
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
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: AppSpacing.lg),
          TextField(
            controller: controller.manualCodeController,
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            decoration: InputDecoration(
              labelText: 'Code du jeton (PA-XXXX)',
              labelStyle: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
              hintText: 'PA-1234',
              hintStyle: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(
                  color: AppColors.accentPrimary,
                  width: 2,
                ),
              ),
              prefixIcon: Icon(
                Icons.confirmation_number,
                color: AppColors.textSecondary,
              ),
              filled: true,
              fillColor: AppColors.cardBg,
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
        color: AppColors.cardBg,
        border: Border(top: BorderSide(color: AppColors.overlayMedium)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Montant à utiliser',
            style: AppTypography.sectionTitle.copyWith(
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
              backgroundColor: AppColors.accentPrimary.withValues(alpha: 0.1),
              iconColor: AppColors.accentPrimary,
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
        backgroundColor: AppColors.accentSuccess,
        colorText: AppColors.textPrimary,
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
        backgroundColor: AppColors.accentDanger,
        colorText: AppColors.textPrimary,
      );
    }
  }
}
