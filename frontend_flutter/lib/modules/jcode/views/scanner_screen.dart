import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:geolocator/geolocator.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/jcode_controller.dart';

class ScannerScreen extends GetView<JcodeController> {
  const ScannerScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final manualCodeCtrl = TextEditingController();
    final scannerCtrl = MobileScannerController();

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text('Scanner J-Code'),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            onPressed: () => scannerCtrl.toggleTorch(),
            icon: const Icon(Icons.flashlight_on),
          ),
        ],
      ),
      body: Column(
        children: [
          // QR scanner
          Expanded(
            flex: 3,
            child: Stack(
              children: [
                MobileScanner(
                  controller: scannerCtrl,
                  onDetect: (capture) async {
                    final barcode = capture.barcodes.firstOrNull;
                    if (barcode?.rawValue == null) return;
                    final raw = barcode!.rawValue!;
                    final jcodeId = int.tryParse(raw);
                    if (jcodeId == null) return;
                    scannerCtrl.stop();
                    await _doScan(jcodeId);
                  },
                ),
                // Overlay frame
                Center(
                  child: Container(
                    width: 220,
                    height: 220,
                    decoration: BoxDecoration(
                      border: Border.all(color: AppColors.accent, width: 3),
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                ),
                // Label
                Positioned(
                  bottom: 16,
                  left: 0,
                  right: 0,
                  child: const Text(
                    'Centrez le QR Code dans le cadre',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white70, fontSize: 14),
                  ),
                ),
              ],
            ),
          ),

          // Saisie manuelle
          Expanded(
            flex: 2,
            child: Container(
              color: AppColors.background,
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Saisir le code manuellement',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: manualCodeCtrl,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            hintText: 'ID du J-Code (ex: 42)',
                            prefixIcon: Icon(Icons.tag),
                          ),
                          textCapitalization: TextCapitalization.characters,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Obx(() => ElevatedButton(
                            onPressed: controller.isScanning.value
                                ? null
                                : () async {
                                    final id =
                                        int.tryParse(manualCodeCtrl.text.trim());
                                    if (id == null) return;
                                    await _doScan(id);
                                  },
                            style: ElevatedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 20, vertical: 16),
                            ),
                            child: controller.isScanning.value
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('Valider'),
                          )),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _doScan(int jcodeId) async {
    try {
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        await Geolocator.requestPermission();
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );
      await controller.scanJcode(jcodeId, pos.latitude, pos.longitude);
    } catch (e) {
      Get.snackbar(
        'Erreur GPS',
        'Impossible d\'obtenir votre position',
        snackPosition: SnackPosition.TOP,
      );
    }
  }
}
