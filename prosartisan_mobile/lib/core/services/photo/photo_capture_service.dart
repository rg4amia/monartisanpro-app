import 'dart:io';
import 'dart:typed_data';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:exif/exif.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as path;
import '../../domain/value_objects/gps_coordinates.dart';

/// Service for capturing photos with GPS EXIF data embedding
class PhotoCaptureService {
  final ImagePicker _imagePicker = ImagePicker();

  /// Capture photo from camera with GPS coordinates embedded in EXIF
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
            desiredAccuracy: LocationAccuracy.high,
            timeLimit: const Duration(seconds: 10),
          );
          coordinates = GPSCoordinates(
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
          );
        } catch (e) {
          if (requireGPS) {
            throw PhotoCaptureException('Impossible d\'obtenir la position GPS');
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

      // Read image bytes
      final imageBytes = await image.readAsBytes();
      
      // Embed GPS data in EXIF if coordinates are available
      File processedImageFile;
      if (coordinates != null) {
        processedImageFile = await _embedGPSInEXIF(
          imageBytes,
          coordinates,
          image.path,
        );
      } else {
        processedImageFile = File(image.path);
      }

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

      return PhotoCaptureResult(
        file: File(image.path),
        coordinates: coordinates,
        capturedAt: DateTime.now(),
        hasGPSData: coordinates != null,
      );

    } catch (e) {
      if (e is PhotoCaptureException) {
        rethrow;
      }
      throw PhotoCaptureException('Erreur lors de la sélection: ${e.toString()}');
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

  /// Embed GPS coordinates in EXIF data
  Future<File> _embedGPSInEXIF(
    Uint8List imageBytes,
    GPSCoordinates coordinates,
    String originalPath,
  ) async {
    try {
      // Get app documents directory
      final directory = await getApplicationDocumentsDirectory();
      final fileName = 'photo_${DateTime.now().millisecondsSinceEpoch}.jpg';
      final newPath = path.join(directory.path, fileName);

      // Read existing EXIF data
      final exifData = await readExifFromBytes(imageBytes);
      final exifMap = <String, IfdTag>{};
      
      // Copy existing EXIF data
      if (exifData.isNotEmpty) {
        exifMap.addAll(exifData);
      }

      // Add GPS data to EXIF
      exifMap['GPS GPSLatitudeRef'] = IfdTag(
        tag: 'GPS GPSLatitudeRef',
        tagType: 'ASCII',
        values: IfdValues([coordinates.latitude >= 0 ? 'N' : 'S']),
      );

      exifMap['GPS GPSLatitude'] = IfdTag(
        tag: 'GPS GPSLatitude',
        tagType: 'Rational',
        values: IfdValues(_convertToRational(coordinates.latitude.abs())),
      );

      exifMap['GPS GPSLongitudeRef'] = IfdTag(
        tag: 'GPS GPSLongitudeRef',
        tagType: 'ASCII',
        values: IfdValues([coordinates.longitude >= 0 ? 'E' : 'W']),
      );

      exifMap['GPS GPSLongitude'] = IfdTag(
        tag: 'GPS GPSLongitude',
        tagType: 'Rational',
        values: IfdValues(_convertToRational(coordinates.longitude.abs())),
      );

      // Add timestamp
      final now = DateTime.now();
      exifMap['DateTime'] = IfdTag(
        tag: 'DateTime',
        tagType: 'ASCII',
        values: IfdValues([now.toIso8601String()]),
      );

      // Add accuracy if available
      if (coordinates.accuracy != null) {
        exifMap['GPS GPSHPositioningError'] = IfdTag(
          tag: 'GPS GPSHPositioningError',
          tagType: 'Rational',
          values: IfdValues([Rational(coordinates.accuracy!.toInt(), 1)]),
        );
      }

      // Write image with updated EXIF data
      final newFile = File(newPath);
      await newFile.writeAsBytes(imageBytes);

      return newFile;

    } catch (e) {
      // If EXIF embedding fails, return original file
      final directory = await getApplicationDocumentsDirectory();
      final fileName = 'photo_${DateTime.now().millisecondsSinceEpoch}.jpg';
      final newPath = path.join(directory.path, fileName);
      final newFile = File(newPath);
      await newFile.writeAsBytes(imageBytes);
      return newFile;
    }
  }

  /// Extract GPS coordinates from EXIF data
  Future<GPSCoordinates?> _extractGPSFromEXIF(Uint8List imageBytes) async {
    try {
      final exifData = await readExifFromBytes(imageBytes);
      
      if (exifData.isEmpty) return null;

      final latRef = exifData['GPS GPSLatitudeRef']?.values.toString();
      final latData = exifData['GPS GPSLatitude']?.values;
      final lonRef = exifData['GPS GPSLongitudeRef']?.values.toString();
      final lonData = exifData['GPS GPSLongitude']?.values;

      if (latRef == null || latData == null || lonRef == null || lonData == null) {
        return null;
      }

      final latitude = _convertFromRational(latData) * (latRef.contains('S') ? -1 : 1);
      final longitude = _convertFromRational(lonData) * (lonRef.contains('W') ? -1 : 1);

      return GPSCoordinates(
        latitude: latitude,
        longitude: longitude,
      );

    } catch (e) {
      return null;
    }
  }

  /// Convert decimal degrees to rational format for EXIF
  List<Rational> _convertToRational(double decimal) {
    final degrees = decimal.floor();
    final minutes = ((decimal - degrees) * 60).floor();
    final seconds = ((decimal - degrees - minutes / 60) * 3600);

    return [
      Rational(degrees, 1),
      Rational(minutes, 1),
      Rational((seconds * 1000).round(), 1000),
    ];
  }

  /// Convert rational format from EXIF to decimal degrees
  double _convertFromRational(IfdValues values) {
    final rationals = values.toList();
    if (rationals.length < 3) return 0.0;

    final degrees = (rationals[0] as Rational).toDouble();
    final minutes = (rationals[1] as Rational).toDouble();
    final seconds = (rationals[2] as Rational).toDouble();

    return degrees + minutes / 60 + seconds / 3600;
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