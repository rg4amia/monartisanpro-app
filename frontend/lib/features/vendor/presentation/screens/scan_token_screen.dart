import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/token_controller.dart';
import '../../../../shared/controllers/search_controller.dart';

class ScanTokenScreen extends StatefulWidget {
  const ScanTokenScreen({super.key});

  @override
  State<ScanTokenScreen> createState() => _ScanTokenScreenState();
}

class _ScanTokenScreenState extends State<ScanTokenScreen> {
  final _tokenController = Get.put(TokenController());
  final _searchController = Get.find<ArtisanSearchController>();
  final _amountController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  MobileScannerController? _scannerController;
  bool _isScanning = true;
  String? _receiptPhotoPath;

  @override
  void initState() {
    super.initState();
    _scannerController = MobileScannerController(
      detectionSpeed: DetectionSpeed.noDuplicates,
    );
  }

  @override
  void dispose() {
    _scannerController?.dispose();
    _amountController.dispose();
    super.dispose();
  }

  void _onQRCodeDetected(BarcodeCapture barcodeCapture) {
    if (!_isScanning) return;

    final barcode = barcodeCapture.barcodes.firstOrNull;
    if (barcode?.rawValue != null) {
      setState(() => _isScanning = false);
      _validateToken(barcode!.rawValue!);
    }
  }

  Future<void> _validateToken(String tokenCode) async {
    final success = await _tokenController.validateToken(tokenCode);

    if (success) {
      _scannerController?.stop();
      _showRedemptionDialog();
    } else {
      Get.snackbar(
        'Code invalide',
        _tokenController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      setState(() => _isScanning = true);
    }
  }

  void _showRedemptionDialog() {
    Get.dialog(
      Obx(() {
        final token = _tokenController.scannedToken.value;
        if (token == null) return const SizedBox();

        return AlertDialog(
          title: const Text('Valider le jeton'),
          content: SingleChildScrollView(
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Token Info
                  Container(
                    padding: const EdgeInsets.all(Spacing.base),
                    decoration: BoxDecoration(
                      color: AppColors.lightBackground,
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Jeton: ${token.formattedCode}',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                fontFamily: 'monospace',
                              ),
                        ),
                        const SizedBox(height: Spacing.sm),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Disponible:',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                            Text(
                              token.formattedRemaining,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.bold,
                                    color: Theme.of(context).colorScheme.primary,
                                  ),
                            ),
                          ],
                        ),
                        if (!token.isValid) ...[
                          const SizedBox(height: Spacing.sm),
                          Text(
                            token.isExpired ? 'Jeton expiré' : 'Jeton épuisé',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.error,
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                        ],
                      ],
                    ),
                  ),

                  if (token.isValid) ...[
                    const SizedBox(height: Spacing.lg),

                    // Amount Input
                    TextFormField(
                      controller: _amountController,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        labelText: 'Montant de l\'achat *',
                        hintText: '0',
                        suffix: const Text('FCFA'),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Veuillez entrer le montant';
                        }
                        final amount = double.tryParse(value);
                        if (amount == null || amount <= 0) {
                          return 'Montant invalide';
                        }
                        if (amount > token.remainingValue) {
                          return 'Montant supérieur au solde disponible';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Receipt Photo
                    Text(
                      'Photo de la facture (optionnel)',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    if (_receiptPhotoPath != null)
                      Stack(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(Spacing.radiusMd),
                            child: Image.asset(
                              _receiptPhotoPath!,
                              height: 150,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            ),
                          ),
                          Positioned(
                            top: 8,
                            right: 8,
                            child: IconButton(
                              icon: const Icon(Icons.close),
                              style: IconButton.styleFrom(
                                backgroundColor: Colors.white,
                              ),
                              onPressed: () {
                                setState(() => _receiptPhotoPath = null);
                              },
                            ),
                          ),
                        ],
                      )
                    else
                      OutlinedButton.icon(
                        onPressed: _pickReceiptPhoto,
                        icon: const Icon(Icons.camera_alt),
                        label: const Text('Prendre une photo'),
                      ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Get.back();
                _resetScanner();
              },
              child: const Text('Annuler'),
            ),
            if (token.isValid)
              ElevatedButton(
                onPressed: _tokenController.isRedeeming.value
                    ? null
                    : _processRedemption,
                child: _tokenController.isRedeeming.value
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Valider'),
              ),
          ],
        );
      }),
      barrierDismissible: false,
    );
  }

  Future<void> _pickReceiptPhoto() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(
      source: ImageSource.camera,
      maxWidth: 1920,
      maxHeight: 1080,
      imageQuality: 85,
    );

    if (image != null) {
      setState(() => _receiptPhotoPath = image.path);
    }
  }

  Future<void> _processRedemption() async {
    if (!_formKey.currentState!.validate()) return;

    final token = _tokenController.scannedToken.value;
    if (token == null) return;

    final amount = double.parse(_amountController.text);

    // First, check GPS proximity (using artisan's location from project)
    // For now, we'll use a dummy location - in production, get from project details
    final gpsValid = await _tokenController.checkGpsProximity(
      artisanLatitude: _searchController.currentPosition.value?.latitude ?? 0,
      artisanLongitude: _searchController.currentPosition.value?.longitude ?? 0,
    );

    if (!gpsValid) {
      final validation = _tokenController.gpsValidation.value;
      Get.back();
      Get.dialog(
        AlertDialog(
          title: Row(
            children: [
              Icon(Icons.location_off, color: AppColors.error),
              const SizedBox(width: Spacing.md),
              const Text('Validation GPS échouée'),
            ],
          ),
          content: Text(
            validation?.message ??
            'Vous devez être à moins de ${AppConstants.gpsValidationDistance}m de l\'artisan.\n\n'
            'Distance actuelle: ${validation?.distanceText ?? 'N/A'}',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Get.back();
                _resetScanner();
              },
              child: const Text('Annuler'),
            ),
            ElevatedButton(
              onPressed: () {
                Get.back();
                _showRedemptionDialog();
              },
              child: const Text('Réessayer'),
            ),
          ],
        ),
      );
      return;
    }

    // Upload receipt photo if provided
    String? receiptUrl;
    if (_receiptPhotoPath != null) {
      receiptUrl = await _tokenController.uploadReceipt(_receiptPhotoPath!);
      if (receiptUrl == null) {
        Get.snackbar(
          'Erreur',
          'Impossible de télécharger la photo',
          backgroundColor: AppColors.warning,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
      }
    }

    // Process redemption
    final success = await _tokenController.redeemToken(
      tokenCode: token.code,
      amount: amount,
      artisanLatitude: _searchController.currentPosition.value?.latitude ?? 0,
      artisanLongitude: _searchController.currentPosition.value?.longitude ?? 0,
      receiptPhoto: receiptUrl,
    );

    Get.back();

    if (success) {
      Get.dialog(
        AlertDialog(
          title: Row(
            children: [
              Icon(Icons.check_circle, color: AppColors.success),
              const SizedBox(width: Spacing.md),
              const Text('Succès'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${amount.toStringAsFixed(0)} FCFA validés',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: AppColors.success,
                    ),
              ),
              const SizedBox(height: Spacing.md),
              Text(
                'Nouveau solde: ${_tokenController.scannedToken.value?.formattedRemaining ?? '0 FCFA'}',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                'Le paiement sera effectué sous 24h',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
              ),
            ],
          ),
          actions: [
            ElevatedButton(
              onPressed: () {
                Get.back();
                _resetScanner();
              },
              child: const Text('OK'),
            ),
          ],
        ),
        barrierDismissible: false,
      );
    } else {
      Get.snackbar(
        'Erreur',
        _tokenController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      _resetScanner();
    }
  }

  void _resetScanner() {
    setState(() {
      _isScanning = true;
      _receiptPhotoPath = null;
    });
    _amountController.clear();
    _tokenController.reset();
    _scannerController?.start();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Scanner un jeton'),
        actions: [
          IconButton(
            icon: const Icon(Icons.flashlight_on),
            onPressed: () => _scannerController?.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        children: [
          // Scanner View
          MobileScanner(
            controller: _scannerController,
            onDetect: _onQRCodeDetected,
          ),

          // Overlay
          Container(
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.5),
            ),
            child: Column(
              children: [
                Expanded(
                  child: Container(
                    color: Colors.black.withValues(alpha: 0.5),
                  ),
                ),
                // Scan area
                SizedBox(
                  height: 300,
                  child: Row(
                    children: [
                      Expanded(
                        child: Container(
                          color: Colors.black.withValues(alpha: 0.5),
                        ),
                      ),
                      Container(
                        width: 300,
                        decoration: BoxDecoration(
                          border: Border.all(
                            color: _isScanning ? AppColors.success : AppColors.warning,
                            width: 3,
                          ),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        ),
                      ),
                      Expanded(
                        child: Container(
                          color: Colors.black.withValues(alpha: 0.5),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Container(
                    color: Colors.black.withValues(alpha: 0.5),
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(Spacing.screenPadding),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              _isScanning ? Icons.qr_code_scanner : Icons.check_circle,
                              color: Colors.white,
                              size: 48,
                            ),
                            const SizedBox(height: Spacing.md),
                            Text(
                              _isScanning
                                  ? 'Placez le QR code dans le cadre'
                                  : 'Code détecté',
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                  ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Loading overlay
          Obx(() {
            if (_tokenController.isValidating.value ||
                _tokenController.isCheckingGps.value ||
                _tokenController.isUploadingReceipt.value) {
              return Container(
                color: Colors.black.withValues(alpha: 0.7),
                child: Center(
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(Spacing.xl),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const CircularProgressIndicator(),
                          const SizedBox(height: Spacing.lg),
                          Text(
                            _tokenController.isValidating.value
                                ? 'Validation du code...'
                                : _tokenController.isCheckingGps.value
                                    ? 'Vérification GPS...'
                                    : 'Upload de la photo...',
                            style: Theme.of(context).textTheme.bodyLarge,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            }
            return const SizedBox();
          }),
        ],
      ),
    );
  }
}
