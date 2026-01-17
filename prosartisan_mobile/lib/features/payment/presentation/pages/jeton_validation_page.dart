import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_code_scanner/qr_code_scanner.dart';
import 'package:geolocator/geolocator.dart';
import '../controllers/jeton_validation_controller.dart';
import '../widgets/amount_input_field.dart';
import '../widgets/gps_status_indicator.dart';

/// Jeton validation screen for suppliers (scan QR, verify GPS)
///
/// Requirements: 5.3
class JetonValidationPage extends StatefulWidget {
  const JetonValidationPage({Key? key}) : super(key: key);

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
      appBar: AppBar(
        title: const Text('Validation Jeton'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        return Column(
          children: [
            // GPS Status indicator
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              color: controller.hasGpsPermission.value
                  ? Colors.green.shade50
                  : Colors.red.shade50,
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
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  // Toggle between QR and manual input
                  TextButton.icon(
                    onPressed: () => controller.toggleInputMethod(),
                    icon: Icon(
                      controller.showManualInput.value
                          ? Icons.qr_code_scanner
                          : Icons.keyboard,
                    ),
                    label: Text(
                      controller.showManualInput.value
                          ? 'Scanner QR Code'
                          : 'Saisie manuelle',
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Validate button
                  if (controller.scannedJetonCode.value.isNotEmpty)
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: controller.canValidate.value
                            ? () => _validateJeton()
                            : null,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Theme.of(context).primaryColor,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                        ),
                        child: const Text(
                          'Valider le jeton',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
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
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Text(
            'Scannez le QR code du jeton',
            style: Theme.of(context).textTheme.titleMedium,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: QRView(
                key: qrKey,
                onQRViewCreated: _onQRViewCreated,
                overlay: QrScannerOverlayShape(
                  borderColor: Theme.of(context).primaryColor,
                  borderRadius: 10,
                  borderLength: 30,
                  borderWidth: 10,
                  cutOutSize: 250,
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
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Text(
            'Saisissez le code du jeton',
            style: Theme.of(context).textTheme.titleMedium,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          TextField(
            controller: controller.manualCodeController,
            decoration: const InputDecoration(
              labelText: 'Code du jeton (PA-XXXX)',
              hintText: 'PA-1234',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.confirmation_number),
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
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        border: Border(top: BorderSide(color: Colors.grey.shade300)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Montant à utiliser',
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),

          // Jeton info display
          if (controller.jetonInfo.value != null)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.blue.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Code: ${controller.scannedJetonCode.value}',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontFamily: 'monospace',
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Montant disponible: ${controller.jetonInfo.value!.remainingAmountFormatted}',
                    style: TextStyle(
                      color: Colors.green.shade700,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),

          const SizedBox(height: 16),

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
        desiredAccuracy: LocationAccuracy.high,
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
