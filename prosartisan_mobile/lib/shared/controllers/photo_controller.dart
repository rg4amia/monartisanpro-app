import 'dart:io';
import 'package:get/get.dart';
import '../../core/services/photo/photo_capture_service.dart';
import '../../core/domain/value_objects/gps_coordinates.dart';

/// Controller for managing photo capture operations
class PhotoController extends GetxController {
  final PhotoCaptureService _photoCaptureService = PhotoCaptureService();

  final RxBool isCapturing = false.obs;
  final RxString errorMessage = ''.obs;
  final Rx<File?> capturedPhoto = Rx<File?>(null);
  final Rx<GPSCoordinates?> photoCoordinates = Rx<GPSCoordinates?>(null);

  /// Capture photo from camera with GPS
  Future<PhotoCaptureResult?> capturePhotoWithGPS({
    bool requireGPS = true,
  }) async {
    try {
      isCapturing.value = true;
      errorMessage.value = '';

      final result = await _photoCaptureService.capturePhotoWithGPS(
        requireGPS: requireGPS,
      );

      capturedPhoto.value = result.file;
      photoCoordinates.value = result.coordinates;

      return result;
    } catch (e) {
      errorMessage.value = e.toString();
      return null;
    } finally {
      isCapturing.value = false;
    }
  }

  /// Pick photo from gallery
  Future<PhotoCaptureResult?> pickPhotoFromGallery() async {
    try {
      isCapturing.value = true;
      errorMessage.value = '';

      final result = await _photoCaptureService.pickPhotoFromGallery();

      capturedPhoto.value = result.file;
      photoCoordinates.value = result.coordinates;

      return result;
    } catch (e) {
      errorMessage.value = e.toString();
      return null;
    } finally {
      isCapturing.value = false;
    }
  }

  /// Verify photo integrity and GPS data
  Future<PhotoVerificationResult?> verifyPhoto(File photoFile) async {
    try {
      return await _photoCaptureService.verifyPhoto(photoFile);
    } catch (e) {
      errorMessage.value = e.toString();
      return null;
    }
  }

  /// Clear captured photo
  void clearPhoto() {
    capturedPhoto.value = null;
    photoCoordinates.value = null;
    errorMessage.value = '';
  }

  /// Clear error message
  void clearError() {
    errorMessage.value = '';
  }
}
