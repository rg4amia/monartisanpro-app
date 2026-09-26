import 'dart:io';
import '../storage/storage_service.dart';

class DeviceFingerprintService {
  static final DeviceFingerprintService _instance = DeviceFingerprintService._internal();

  factory DeviceFingerprintService() => _instance;

  DeviceFingerprintService._internal();

  /// Retourne l'identifiant matériel persistant de l'appareil
  String getFingerprint() {
    return StorageService.getDeviceFingerprint();
  }

  /// Retourne le modèle / version de l'appareil
  String getDeviceModel() {
    try {
      final os = Platform.operatingSystem;
      final version = Platform.operatingSystemVersion;
      return '$os-$version';
    } catch (_) {
      return 'flutter-client';
    }
  }

  /// Retourne la map d'en-têtes HTTP de télémétrie et détection anti-collusion
  Map<String, String> getHeaders() {
    final fp = getFingerprint();
    return {
      'X-Device-Fingerprint': fp,
      'X-App-Installation-Id': fp,
      'X-Device-Model': getDeviceModel(),
    };
  }
}
