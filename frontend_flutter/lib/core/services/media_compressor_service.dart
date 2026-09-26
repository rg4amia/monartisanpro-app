import 'package:image_picker/image_picker.dart';
import '../storage/storage_service.dart';

/// Service unifié d'optimisation et compression de médias
/// Respecte le mode Data Saver et les contraintes de bande passante en Côte d'Ivoire.
class MediaCompressorService {
  /// Sélectionne une image optimisée avec compression adaptative.
  static Future<XFile?> pickOptimizedImage({
    required ImagePicker picker,
    required ImageSource source,
    int? customQuality,
    double? maxWidth,
    double? maxHeight,
  }) async {
    final bool isDataSaver = StorageService.isDataSaverEnabled();

    // Mode Data Saver : 70% de qualité et max 1280px pour économiser la data (taille divisée par ~4).
    // Mode Standard : 80% de qualité et max 1920px.
    final int quality = customQuality ?? (isDataSaver ? 70 : 80);
    final double maxW = maxWidth ?? (isDataSaver ? 1280.0 : 1920.0);
    final double maxH = maxHeight ?? (isDataSaver ? 1280.0 : 1920.0);

    return picker.pickImage(
      source: source,
      imageQuality: quality,
      maxWidth: maxW,
      maxHeight: maxH,
    );
  }

  /// Sélectionne une vidéo avec durée maximale contrainte (défaut: 30s) pour éviter les téléversements trop lourds.
  static Future<XFile?> pickOptimizedVideo({
    required ImagePicker picker,
    required ImageSource source,
    Duration maxDuration = const Duration(seconds: 30),
  }) async {
    return picker.pickVideo(
      source: source,
      maxDuration: maxDuration,
    );
  }

  /// Formate une taille en octets en chaîne lisible (Ko / Mo).
  static String formatBytes(int bytes) {
    if (bytes < 1024) return '$bytes o';
    if (bytes < 1024 * 1024) {
      return '${(bytes / 1024).toStringAsFixed(1)} Ko';
    }
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} Mo';
  }

  /// Vérifie si la taille du fichier respecte le plafond autorisé (défaut: 25 Mo).
  static bool isSizeAllowed(int bytes, {int maxBytes = 25 * 1024 * 1024}) {
    return bytes <= maxBytes;
  }
}
