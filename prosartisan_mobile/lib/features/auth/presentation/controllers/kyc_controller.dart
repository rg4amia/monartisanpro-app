import 'dart:io';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../domain/usecases/upload_kyc_usecase.dart';

/// Controller for KYC document upload
class KycController extends GetxController {
  final UploadKycUseCase _uploadKycUseCase;
  final ImagePicker _imagePicker = ImagePicker();

  KycController(this._uploadKycUseCase);

  // Observable state
  final RxBool isLoading = false.obs;
  final RxString errorMessage = ''.obs;
  final Rx<File?> idDocument = Rx<File?>(null);
  final Rx<File?> selfie = Rx<File?>(null);
  final RxString idType = 'CNI'.obs;
  final RxString idNumber = ''.obs;

  /// Pick ID document from camera
  Future<void> pickIdDocumentFromCamera() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );

      if (image != null) {
        idDocument.value = File(image.path);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la capture de la photo';
    }
  }

  /// Pick ID document from gallery
  Future<void> pickIdDocumentFromGallery() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );

      if (image != null) {
        idDocument.value = File(image.path);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la sélection de la photo';
    }
  }

  /// Pick selfie from camera
  Future<void> pickSelfieFromCamera() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
        preferredCameraDevice: CameraDevice.front,
      );

      if (image != null) {
        selfie.value = File(image.path);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la capture du selfie';
    }
  }

  /// Pick selfie from gallery
  Future<void> pickSelfieFromGallery() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );

      if (image != null) {
        selfie.value = File(image.path);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la sélection du selfie';
    }
  }

  /// Upload KYC documents
  Future<bool> uploadKyc(String userId) async {
    try {
      // Validate inputs
      if (idType.value.isEmpty) {
        errorMessage.value = 'Type de pièce d\'identité requis';
        return false;
      }

      if (idNumber.value.isEmpty) {
        errorMessage.value = 'Numéro de pièce requis';
        return false;
      }

      if (idDocument.value == null) {
        errorMessage.value = 'Document d\'identité requis';
        return false;
      }

      if (selfie.value == null) {
        errorMessage.value = 'Selfie requis';
        return false;
      }

      isLoading.value = true;
      errorMessage.value = '';

      await _uploadKycUseCase(
        userId: userId,
        idType: idType.value,
        idNumber: idNumber.value,
        idDocument: idDocument.value!,
        selfie: selfie.value!,
      );

      return true;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Set ID type
  void setIdType(String type) {
    idType.value = type;
  }

  /// Set ID number
  void setIdNumber(String number) {
    idNumber.value = number;
  }

  /// Clear error message
  void clearError() {
    errorMessage.value = '';
  }

  /// Reset form
  void reset() {
    idDocument.value = null;
    selfie.value = null;
    idType.value = 'CNI';
    idNumber.value = '';
    errorMessage.value = '';
  }
}
