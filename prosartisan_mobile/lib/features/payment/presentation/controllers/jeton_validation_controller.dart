import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import '../../data/repositories/jeton_repository.dart';
import '../../domain/entities/jeton.dart';
import '../../domain/entities/jeton_validation_result.dart';

/// Controller for jeton validation by suppliers
///
/// Requirements: 5.3
class JetonValidationController extends GetxController {
  final JetonRepository _jetonRepository = Get.find<JetonRepository>();

  final RxBool isLoading = false.obs;
  final RxBool showManualInput = false.obs;
  final RxBool hasGpsPermission = false.obs;
  final RxDouble gpsAccuracy = 0.0.obs;
  final RxString scannedJetonCode = ''.obs;
  final Rx<Jeton?> jetonInfo = Rx<Jeton?>(null);
  final RxString errorMessage = ''.obs;

  final TextEditingController manualCodeController = TextEditingController();
  final TextEditingController amountController = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    _checkGpsPermission();
  }

  @override
  void onClose() {
    manualCodeController.dispose();
    amountController.dispose();
    super.onClose();
  }

  /// Check GPS permission and accuracy
  Future<void> _checkGpsPermission() async {
    try {
      LocationPermission permission = await Geolocator.checkPermission();

      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      hasGpsPermission.value =
          permission == LocationPermission.whileInUse ||
          permission == LocationPermission.always;

      if (hasGpsPermission.value) {
        // Get current position to check accuracy
        try {
          Position position = await Geolocator.getCurrentPosition(
            desiredAccuracy: LocationAccuracy.high,
          );
          gpsAccuracy.value = position.accuracy;
        } catch (e) {
          gpsAccuracy.value = 999.0; // High value indicates poor accuracy
        }
      }
    } catch (e) {
      hasGpsPermission.value = false;
    }
  }

  /// Toggle between QR scanner and manual input
  void toggleInputMethod() {
    showManualInput.value = !showManualInput.value;
    if (showManualInput.value) {
      manualCodeController.text = scannedJetonCode.value;
    }
  }

  /// Set scanned jeton code and load jeton info
  void setScannedCode(String code) {
    scannedJetonCode.value = code.toUpperCase();
    manualCodeController.text = scannedJetonCode.value;

    if (code.isNotEmpty && _isValidJetonCode(code)) {
      _loadJetonInfo(code);
    } else {
      jetonInfo.value = null;
    }
  }

  /// Set amount to use
  void setAmount(String amount) {
    // Validate amount format and limits
    final amountCentimes = _parseAmountToCentimes(amount);
    final maxAmount = jetonInfo.value?.remainingAmountCentimes ?? 0;

    if (amountCentimes > maxAmount) {
      amountController.text = _formatAmountFromCentimes(maxAmount);
    }
  }

  /// Check if validation can proceed
  bool get canValidate {
    return scannedJetonCode.value.isNotEmpty &&
        jetonInfo.value != null &&
        amountController.text.isNotEmpty &&
        hasGpsPermission.value &&
        gpsAccuracy.value <= 10.0 && // GPS accuracy must be <= 10m
        _parseAmountToCentimes(amountController.text) > 0;
  }

  /// Validate jeton with GPS verification
  Future<void> validateJeton({
    required double supplierLatitude,
    required double supplierLongitude,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final amountCentimes = _parseAmountToCentimes(amountController.text);

      // Get artisan location (this would typically come from the jeton or be provided)
      // For now, we'll use a mock location or get it from the jeton info
      final artisanLatitude = jetonInfo.value?.artisanLocation?.latitude ?? 0.0;
      final artisanLongitude =
          jetonInfo.value?.artisanLocation?.longitude ?? 0.0;

      final result = await _jetonRepository.validateJeton(
        jetonCode: scannedJetonCode.value,
        amountCentimes: amountCentimes,
        artisanLatitude: artisanLatitude,
        artisanLongitude: artisanLongitude,
        supplierLatitude: supplierLatitude,
        supplierLongitude: supplierLongitude,
      );

      if (result.isSuccess) {
        _handleValidationSuccess(result);
      } else {
        errorMessage.value =
            result.errorMessage ?? 'Erreur lors de la validation';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      errorMessage.value = 'Erreur de validation: ${e.toString()}';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoading.value = false;
    }
  }

  /// Load jeton information
  Future<void> _loadJetonInfo(String code) async {
    try {
      final jeton = await _jetonRepository.getJetonByCode(code);
      jetonInfo.value = jeton;

      if (jeton == null) {
        errorMessage.value = 'Jeton non trouvé ou invalide';
        _showErrorSnackbar(errorMessage.value);
      } else if (jeton.isExpired) {
        errorMessage.value = 'Ce jeton a expiré';
        _showErrorSnackbar(errorMessage.value);
      } else if (jeton.remainingAmountCentimes <= 0) {
        errorMessage.value = 'Ce jeton a été entièrement utilisé';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors du chargement du jeton';
      _showErrorSnackbar(errorMessage.value);
    }
  }

  /// Handle successful validation
  void _handleValidationSuccess(JetonValidationResult result) {
    // Clear form
    scannedJetonCode.value = '';
    manualCodeController.clear();
    amountController.clear();
    jetonInfo.value = null;

    Get.snackbar(
      'Validation réussie',
      'Montant validé: ${_formatAmountFromCentimes(result.amountUsed)}',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: Get.theme.primaryColor,
      colorText: Get.theme.colorScheme.onPrimary,
      duration: const Duration(seconds: 4),
    );
  }

  /// Validate jeton code format (PA-XXXX)
  bool _isValidJetonCode(String code) {
    final regex = RegExp(r'^PA-[A-Z0-9]{4}$');
    return regex.hasMatch(code.toUpperCase());
  }

  /// Parse amount string to centimes
  int _parseAmountToCentimes(String amount) {
    try {
      final cleanAmount = amount.replaceAll(RegExp(r'[^\d.]'), '');
      final francs = double.parse(cleanAmount);
      return (francs * 100).round();
    } catch (e) {
      return 0;
    }
  }

  /// Format amount from centimes to display string
  String _formatAmountFromCentimes(int centimes) {
    final francs = centimes / 100;
    return francs.toStringAsFixed(0);
  }

  /// Show error snackbar
  void _showErrorSnackbar(String message) {
    Get.snackbar(
      'Erreur',
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: Get.theme.colorScheme.error,
      colorText: Get.theme.colorScheme.onError,
      duration: const Duration(seconds: 4),
    );
  }
}
