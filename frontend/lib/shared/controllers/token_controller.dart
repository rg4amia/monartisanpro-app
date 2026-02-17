import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/network/token_service.dart';
import '../models/escrow_model.dart';

class TokenController extends GetxController {
  final TokenService _tokenService = TokenService();

  // Token state
  final Rx<MaterialToken?> scannedToken = Rx<MaterialToken?>(null);
  final RxList<TokenRedemption> redemptionHistory = <TokenRedemption>[].obs;
  final Rx<GpsValidationResult?> gpsValidation = Rx<GpsValidationResult?>(null);

  // Loading states
  final RxBool isValidating = false.obs;
  final RxBool isRedeeming = false.obs;
  final RxBool isCheckingGps = false.obs;
  final RxBool isUploadingReceipt = false.obs;
  final RxString errorMessage = ''.obs;

  // Current location
  final Rx<Position?> currentPosition = Rx<Position?>(null);

  /// Validate token code
  Future<bool> validateToken(String tokenCode) async {
    try {
      isValidating.value = true;
      errorMessage.value = '';

      final response = await _tokenService.validateToken(tokenCode);

      if (response.success && response.data != null) {
        scannedToken.value = response.data;
        return true;
      } else {
        errorMessage.value = response.message ?? 'Code invalide';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur de connexion: ${e.toString()}';
      return false;
    } finally {
      isValidating.value = false;
    }
  }

  /// Check GPS proximity before redemption
  Future<bool> checkGpsProximity({
    required double artisanLatitude,
    required double artisanLongitude,
  }) async {
    try {
      isCheckingGps.value = true;
      errorMessage.value = '';

      // Get current position
      final position = await _getCurrentPosition();
      if (position == null) {
        errorMessage.value = 'Impossible d\'obtenir votre position GPS';
        return false;
      }

      currentPosition.value = position;

      final response = await _tokenService.checkGpsProximity(
        vendorLatitude: position.latitude,
        vendorLongitude: position.longitude,
        artisanLatitude: artisanLatitude,
        artisanLongitude: artisanLongitude,
      );

      if (response.success && response.data != null) {
        gpsValidation.value = response.data;
        return response.data!.isValid;
      } else {
        errorMessage.value = response.message ?? 'Erreur de validation GPS';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur GPS: ${e.toString()}';
      return false;
    } finally {
      isCheckingGps.value = false;
    }
  }

  /// Redeem token
  Future<bool> redeemToken({
    required String tokenCode,
    required double amount,
    required double artisanLatitude,
    required double artisanLongitude,
    String? receiptPhoto,
  }) async {
    try {
      isRedeeming.value = true;
      errorMessage.value = '';

      // Get current position
      final position = currentPosition.value ?? await _getCurrentPosition();
      if (position == null) {
        errorMessage.value = 'Position GPS requise';
        return false;
      }

      final request = RedeemTokenRequest(
        tokenCode: tokenCode,
        amount: amount,
        vendorLatitude: position.latitude,
        vendorLongitude: position.longitude,
        artisanLatitude: artisanLatitude,
        artisanLongitude: artisanLongitude,
        receiptPhoto: receiptPhoto,
      );

      final response = await _tokenService.redeemToken(request);

      if (response.success && response.data != null) {
        // Refresh token to get updated remaining value
        await validateToken(tokenCode);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Échec de la validation';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      return false;
    } finally {
      isRedeeming.value = false;
    }
  }

  /// Upload receipt photo
  Future<String?> uploadReceipt(String filePath) async {
    try {
      isUploadingReceipt.value = true;
      errorMessage.value = '';

      // Get current position
      final position = currentPosition.value ?? await _getCurrentPosition();
      if (position == null) {
        errorMessage.value = 'Position GPS requise pour la photo';
        return null;
      }

      final response = await _tokenService.uploadReceipt(
        filePath: filePath,
        latitude: position.latitude,
        longitude: position.longitude,
      );

      if (response.success && response.data != null) {
        return response.data;
      } else {
        errorMessage.value = response.message ?? 'Échec du téléchargement';
        return null;
      }
    } catch (e) {
      errorMessage.value = 'Erreur upload: ${e.toString()}';
      return null;
    } finally {
      isUploadingReceipt.value = false;
    }
  }

  /// Get redemption history for a token
  Future<void> fetchRedemptionHistory(int tokenId) async {
    try {
      final response = await _tokenService.getRedemptionHistory(tokenId);

      if (response.success && response.data != null) {
        redemptionHistory.value = response.data!;
      }
    } catch (e) {
      // Silent fail
      redemptionHistory.value = [];
    }
  }

  /// Get current GPS position
  Future<Position?> _getCurrentPosition() async {
    try {
      // Check permission
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final requested = await Geolocator.requestPermission();
        if (requested == LocationPermission.denied ||
            requested == LocationPermission.deniedForever) {
          errorMessage.value = 'Permission GPS refusée';
          return null;
        }
      }

      // Get position with high accuracy
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      return position;
    } catch (e) {
      errorMessage.value = 'Erreur GPS: ${e.toString()}';
      return null;
    }
  }

  /// Reset state
  void reset() {
    scannedToken.value = null;
    redemptionHistory.clear();
    gpsValidation.value = null;
    currentPosition.value = null;
    errorMessage.value = '';
  }
}
