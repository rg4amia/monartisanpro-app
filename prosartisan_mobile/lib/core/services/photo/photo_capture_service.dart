import 'dart:io';
import 'dart:typed_data';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:exif/exif.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as path;
import '../../domain/value_objects/gps_coordinates.dart';

/// Service for capturing photos with GPS coordinates
class PhotoCaptureService {
  final ImagePicker _imagePicker = ImagePicker();

  /// Capture photo from camera with GPS coordinates
  Future<PhotoCaptureResult> capturePhotoWithGPS({
    bool requireGPS = true,
    double maxWidth = 1920,
    double maxHeight = 1080,
    int imageQuality = 85,
  }) async {
    try {
      // Check and request location permissions
      final locationPermission = await _checkLocationPermission();
      if (!locationPermission && requireGPS) {
        throw PhotoCaptureException('Permission de localisation requise');
      }

      // Get current GPS coordinates
      GPSCoordinates? coordinates;
      if (locationPermission) {
        try {
          final position = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              timeLimit: Duration(seconds: 10),
            ),
          );
          coordinates = GPSCoordinates(
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
          );
        } catch (e) {
          if (requireGPS) {
            throw PhotoCaptureException(
              'Impossible d\'obtenir la position GPS',
            );
          }
        }
      }

      // Capture photo
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: maxWidth,
        maxHeight: maxHeight,
        imageQuality: imageQuality,
      );

      if (image == null) {
        throw PhotoCaptureException('Aucune photo capturée');
      }

      // Read image bytes and save to app directory
      final imageBytes = await image.readAsBytes();
      final processedImageFile = await _saveImageToAppDirectory(imageBytes);

      return PhotoCaptureResult(
        file: processedImageFile,
        coordinates: coordinates,
        capturedAt: DateTime.now(),
        hasGPSData: coordinates != null,
      );
    } catch (e) {
      if (e is PhotoCaptureException) {
        rethrow;
      }
      throw PhotoCaptureException('Erreur lors de la capture: ${e.toString()}');
    }
  }

  /// Pick photo from gallery and extract GPS data if available
  Future<PhotoCaptureResult> pickPhotoFromGallery() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
      );

      if (image == null) {
        throw PhotoCaptureException('Aucune photo sélectionnée');
      }

      final imageBytes = await image.readAsBytes();
      final coordinates = await _extractGPSFromEXIF(imageBytes);
      final processedImageFile = await _saveImageToAppDirectory(imageBytes);

      return PhotoCaptureResult(
        file: processedImageFile,
        coordinates: coordinates,
        capturedAt: DateTime.now(),
        hasGPSData: coordinates != null,
      );
    } catch (e) {
      if (e is PhotoCaptureException) {
        rethrow;
      }
      throw PhotoCaptureException(
        'Erreur lors de la sélection: ${e.toString()}',
      );
    }
  }

  /// Check and request location permissions
  Future<bool> _checkLocationPermission() async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        return false;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      return false;
    }

    return true;
  }

  /// Save image to app documents directory
  Future<File> _saveImageToAppDirectory(Uint8List imageBytes) async {
    try {
      final directory = await getApplicationDocumentsDirectory();
      final fileName = 'photo_${DateTime.now().millisecondsSinceEpoch}.jpg';
      final newPath = path.join(directory.path, fileName);
      final newFile = File(newPath);
      await newFile.writeAsBytes(imageBytes);
      return newFile;
    } catch (e) {
      throw PhotoCaptureException(
        'Erreur lors de la sauvegarde: ${e.toString()}',
      );
    }
  }

  /// Extract GPS coordinates from EXIF data
  Future<GPSCoordinates?> _extractGPSFromEXIF(Uint8List imageBytes) async {
    try {
      final exifData = await readExifFromBytes(imageBytes);

      if (exifData.isEmpty) return null;

      // Try to extract GPS data from EXIF
      final latRef = exifData['GPS GPSLatitudeRef']?.printable;
      final lonRef = exifData['GPS GPSLongitudeRef']?.printable;
      final latData = exifData['GPS GPSLatitude']?.values;
      final lonData = exifData['GPS GPSLongitude']?.values;

      if (latRef == null ||
          lonRef == null ||
          latData == null ||
          lonData == null) {
        return null;
      }

      // Convert GPS coordinates from EXIF format
      final latitude =
          _convertGPSCoordinate(latData) * (latRef.contains('S') ? -1 : 1);
      final longitude =
          _convertGPSCoordinate(lonData) * (lonRef.contains('W') ? -1 : 1);

      return GPSCoordinates(latitude: latitude, longitude: longitude);
    } catch (e) {
      return null;
    }
  }

  /// Convert GPS coordinate from EXIF rational format to decimal degrees
  double _convertGPSCoordinate(dynamic values) {
    try {
      if (values is List && values.length >= 3) {
        // EXIF GPS format: [degrees, minutes, seconds] as rationals
        final degrees = _parseRational(values[0]);
        final minutes = _parseRational(values[1]);
        final seconds = _parseRational(values[2]);

        return degrees + minutes / 60 + seconds / 3600;
      }
      return 0.0;
    } catch (e) {
      return 0.0;
    }
  }

  /// Parse rational value from EXIF data
  double _parseRational(dynamic value) {
    try {
      if (value is num) {
        return value.toDouble();
      }
      if (value.toString().contains('/')) {
        final parts = value.toString().split('/');
        if (parts.length == 2) {
          final numerator = double.tryParse(parts[0]) ?? 0;
          final denominator = double.tryParse(parts[1]) ?? 1;
          return denominator != 0 ? numerator / denominator : 0;
        }
      }
      return double.tryParse(value.toString()) ?? 0;
    } catch (e) {
      return 0.0;
    }
  }

  /// Verify photo integrity and GPS data
  Future<PhotoVerificationResult> verifyPhoto(File photoFile) async {
    try {
      final imageBytes = await photoFile.readAsBytes();
      final coordinates = await _extractGPSFromEXIF(imageBytes);

      // Check if file exists and is readable
      final exists = await photoFile.exists();
      final size = await photoFile.length();

      // Basic integrity checks
      final hasValidSize = size > 1024; // At least 1KB
      final hasGPSData = coordinates != null;

      return PhotoVerificationResult(
        isValid: exists && hasValidSize,
        hasGPSData: hasGPSData,
        coordinates: coordinates,
        fileSize: size,
        verifiedAt: DateTime.now(),
      );
    } catch (e) {
      return PhotoVerificationResult(
        isValid: false,
        hasGPSData: false,
        coordinates: null,
        fileSize: 0,
        verifiedAt: DateTime.now(),
        error: e.toString(),
      );
    }
  }
}

/// Result of photo capture operation
class PhotoCaptureResult {
  final File file;
  final GPSCoordinates? coordinates;
  final DateTime capturedAt;
  final bool hasGPSData;

  PhotoCaptureResult({
    required this.file,
    this.coordinates,
    required this.capturedAt,
    required this.hasGPSData,
  });
}

/// Result of photo verification
class PhotoVerificationResult {
  final bool isValid;
  final bool hasGPSData;
  final GPSCoordinates? coordinates;
  final int fileSize;
  final DateTime verifiedAt;
  final String? error;

  PhotoVerificationResult({
    required this.isValid,
    required this.hasGPSData,
    this.coordinates,
    required this.fileSize,
    required this.verifiedAt,
    this.error,
  });
}

/// Exception thrown during photo capture operations
class PhotoCaptureException implements Exception {
  final String message;

  PhotoCaptureException(this.message);

  @override
  String toString() => 'PhotoCaptureException: $message';
}
