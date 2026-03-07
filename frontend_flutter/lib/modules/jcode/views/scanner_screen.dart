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
    final gpsEnabled = true.obs;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Vérification J-Code'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header text
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                'Scan the Artisan\'s J-Code to\nauthorize material delivery',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                  height: 1.3,
                ),
              ),
            ),

            // QR Scanner area
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 24),
              height: 340,
              decoration: BoxDecoration(
                color: const Color(0xFF3A4A5C),
                borderRadius: BorderRadius.circular(24),
              ),
              child: Stack(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: MobileScanner(
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
                  ),
                  // Scan frame overlay
                  Center(
                    child: Container(
                      width: 240,
                      height: 240,
                      decoration: BoxDecoration(
                        border: Border.all(color: AppColors.primary, width: 3),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: CustomPaint(
                        painter: _ScannerCornersPainter(),
                      ),
                    ),
                  ),
                  // Bottom action buttons
                  Positioned(
                    bottom: 20,
                    left: 0,
                    right: 0,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _ActionButton(
                          icon: Icons.photo_library,
                          onTap: () {
                            // TODO: Pick from gallery
                          },
                        ),
                        const SizedBox(width: 24),
                        _ActionButton(
                          icon: Icons.camera_alt,
                          isPrimary: true,
                          onTap: () {
                            // Camera is already active
                          },
                        ),
                        const SizedBox(width: 24),
                        _ActionButton(
                          icon: Icons.delete_outline,
                          onTap: () {
                            Get.back();
                          },
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // GPS Validation toggle
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 24),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppColors.success.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.location_on,
                      color: AppColors.success,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'GPS Validation',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 15,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Supplier is at the registered location',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Obx(() => Switch(
                        value: gpsEnabled.value,
                        onChanged: (val) => gpsEnabled.value = val,
                        activeColor: AppColors.success,
                      )),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Manual Entry section
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Manual Entry',
                    style: TextStyle(
                      fontSize: 14,
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: manualCodeCtrl,
                    decoration: InputDecoration(
                      hintText: 'PA-XXXX',
                      hintStyle: TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 16,
                      ),
                      prefixIcon: const Icon(Icons.qr_code_2, size: 20),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide(color: AppColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(
                            color: AppColors.primary, width: 2),
                      ),
                    ),
                    textCapitalization: TextCapitalization.characters,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Validate button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Obx(() => ElevatedButton(
                    onPressed: controller.isScanning.value
                        ? null
                        : () async {
                            final code = manualCodeCtrl.text.trim();
                            if (code.isEmpty) return;
                            // Try to parse as ID or code
                            final id = int.tryParse(code);
                            if (id != null) {
                              await _doScan(id);
                            } else {
                              // TODO: Lookup by code string
                              Get.snackbar(
                                'Code invalide',
                                'Veuillez entrer un code valide (ex: PA-XXXX)',
                                snackPosition: SnackPosition.TOP,
                              );
                            }
                          },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: controller.isScanning.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: const [
                              Text(
                                'Valider manuellement',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              SizedBox(width: 8),
                              Icon(Icons.arrow_forward, size: 20),
                            ],
                          ),
                  )),
            ),

            const SizedBox(height: 32),
          ],
        ),
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
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    }
  }
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool isPrimary;

  const _ActionButton({
    required this.icon,
    required this.onTap,
    this.isPrimary = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 56,
        height: 56,
        decoration: BoxDecoration(
          color: isPrimary ? AppColors.primary : const Color(0xFF5A6A7C),
          shape: BoxShape.circle,
        ),
        child: Icon(
          icon,
          color: Colors.white,
          size: 24,
        ),
      ),
    );
  }
}

class _ScannerCornersPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.primary
      ..strokeWidth = 4
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    const cornerLength = 30.0;

    // Top-left
    canvas.drawLine(const Offset(0, 0), const Offset(cornerLength, 0), paint);
    canvas.drawLine(const Offset(0, 0), const Offset(0, cornerLength), paint);

    // Top-right
    canvas.drawLine(
        Offset(size.width, 0), Offset(size.width - cornerLength, 0), paint);
    canvas.drawLine(
        Offset(size.width, 0), Offset(size.width, cornerLength), paint);

    // Bottom-left
    canvas.drawLine(
        Offset(0, size.height), Offset(cornerLength, size.height), paint);
    canvas.drawLine(
        Offset(0, size.height), Offset(0, size.height - cornerLength), paint);

    // Bottom-right
    canvas.drawLine(Offset(size.width, size.height),
        Offset(size.width - cornerLength, size.height), paint);
    canvas.drawLine(Offset(size.width, size.height),
        Offset(size.width, size.height - cornerLength), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
