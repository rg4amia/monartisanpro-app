import 'package:get/get.dart';
import '../../data/repositories/jeton_repository.dart';
import '../../domain/entities/jeton.dart';

/// Controller for jeton display and management
///
/// Requirements: 5.1, 5.2
class JetonController extends GetxController {
  final JetonRepository _jetonRepository = Get.find<JetonRepository>();

  final RxBool isLoading = false.obs;
  final Rx<Jeton?> currentJeton = Rx<Jeton?>(null);
  final RxString errorMessage = ''.obs;

  /// Load jeton details by ID
  Future<void> loadJetonDetails(String jetonId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final jeton = await _jetonRepository.getJetonById(jetonId);

      if (jeton != null) {
        currentJeton.value = jeton;
      } else {
        errorMessage.value = 'Jeton non trouvé';
        _showErrorSnackbar(errorMessage.value);
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors du chargement du jeton';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoading.value = false;
    }
  }

  /// Generate a new jeton for the current sequestre
  Future<void> generateNewJeton() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final currentSequestreId = currentJeton.value?.sequestreId;
      if (currentSequestreId == null) {
        throw Exception('Aucun séquestre associé');
      }

      final newJeton = await _jetonRepository.generateJeton(
        sequestreId: currentSequestreId,
      );

      if (newJeton != null) {
        currentJeton.value = newJeton;
        Get.snackbar(
          'Nouveau jeton généré',
          'Un nouveau jeton a été créé avec succès',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: Get.theme.primaryColor,
          colorText: Get.theme.colorScheme.onPrimary,
          duration: const Duration(seconds: 3),
        );
      } else {
        throw Exception('Impossible de générer un nouveau jeton');
      }
    } catch (e) {
      errorMessage.value = 'Erreur lors de la génération du jeton';
      _showErrorSnackbar(errorMessage.value);
    } finally {
      isLoading.value = false;
    }
  }

  /// Check if jeton is about to expire (within 24 hours)
  bool get isJetonAboutToExpire {
    final jeton = currentJeton.value;
    if (jeton == null || jeton.isExpired) return false;

    final now = DateTime.now();
    final expiresAt = jeton.expiresAt;
    final hoursUntilExpiry = expiresAt.difference(now).inHours;

    return hoursUntilExpiry <= 24;
  }

  /// Get formatted time until expiration
  String get timeUntilExpiration {
    final jeton = currentJeton.value;
    if (jeton == null || jeton.isExpired) return '';

    final now = DateTime.now();
    final expiresAt = jeton.expiresAt;
    final duration = expiresAt.difference(now);

    if (duration.inDays > 0) {
      return '${duration.inDays} jour(s)';
    } else if (duration.inHours > 0) {
      return '${duration.inHours} heure(s)';
    } else if (duration.inMinutes > 0) {
      return '${duration.inMinutes} minute(s)';
    } else {
      return 'Expire bientôt';
    }
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
