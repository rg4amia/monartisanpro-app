import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../data/models/jalon_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/mission_repository.dart';

class MissionsController extends GetxController {
  final MissionRepository _repo = MissionRepository();

  final missions = <MissionModel>[].obs;
  final currentMission = Rx<MissionModel?>(null);
  final jalons = <JalonModel>[].obs;
  final isLoading = false.obs;
  final isRefreshing = false.obs;
  final isSubmittingJalon = false.obs;
  final selectedFilter = 'all'.obs;
  final errorMsg = Rx<String?>(null);

  // Estimate response from Gemini AI
  final estimateResult = Rx<Map<String, dynamic>?>(null);
  final isEstimating = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadMissions();
  }

  /// Charge la liste des missions avec gestion d'erreur améliorée
  ///
  /// [status] - Filtre par statut (null = toutes)
  /// [isRefresh] - Indique si c'est un pull-to-refresh (forceRefresh API)
  Future<void> loadMissions({String? status, bool isRefresh = false}) async {
    if (isRefresh) {
      isRefreshing.value = true;
    } else {
      isLoading.value = true;
    }
    errorMsg.value = null;

    try {
      final result = await _repo.getMissions(
        status: status == 'all' ? null : status,
        forceRefresh: isRefresh, // Force API call on pull-to-refresh
      );
      missions.value = result;

      // Clear error on success
      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);

      // Ne pas afficher d'erreur si on a réussi à charger du cache
      if (missions.isEmpty) {
        _showErrorSnackbar(errorMsg.value!);
      }
    } catch (e) {
      errorMsg.value = 'Une erreur inattendue est survenue';

      if (missions.isEmpty) {
        _showErrorSnackbar(errorMsg.value!);
      }
    } finally {
      isLoading.value = false;
      isRefreshing.value = false;
    }
  }

  /// Charge une mission spécifique avec ses jalons
  Future<void> loadMission(int id, {bool showLoader = true}) async {
    if (showLoader) {
      isLoading.value = true;
    }
    errorMsg.value = null;

    try {
      // Charger la mission et les jalons en parallèle
      final results = await Future.wait([
        _repo.getMission(id),
        _repo.getJalons(id),
      ]);

      currentMission.value = results[0] as MissionModel;
      jalons.value = results[1] as List<JalonModel>;

      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
    } catch (e) {
      errorMsg.value = 'Impossible de charger les détails de la mission';
      _showErrorSnackbar(errorMsg.value!);
    } finally {
      if (showLoader) {
        isLoading.value = false;
      }
    }
  }

  /// Demande une estimation Gemini AI
  Future<void> estimate(String description, String category) async {
    isEstimating.value = true;
    estimateResult.value = null;
    errorMsg.value = null;

    try {
      final result = await _repo.estimate(
        description: description,
        category: category,
      );
      estimateResult.value = result;

      // Show success message
      Get.snackbar(
        'Estimation IA',
        'Estimation générée avec succès',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 2),
      );
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      estimateResult.value = null;
      _showErrorSnackbar('Échec de l\'estimation IA: ${errorMsg.value}');
    } catch (e) {
      estimateResult.value = null;
      _showErrorSnackbar('Impossible de générer une estimation');
    } finally {
      isEstimating.value = false;
    }
  }

  /// Soumet un jalon pour validation
  Future<bool> submitJalon(int jalonId) async {
    isSubmittingJalon.value = true;
    errorMsg.value = null;

    try {
      await _repo.submitJalon(jalonId);

      // Recharger la mission en arrière-plan
      if (currentMission.value != null) {
        await loadMission(currentMission.value!.id, showLoader: false);
      }

      Get.snackbar(
        'Jalon soumis',
        'Le jalon a été soumis avec succès',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 2),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Échec de la soumission: ${errorMsg.value}');
      return false;
    } catch (e) {
      _showErrorSnackbar('Impossible de soumettre le jalon');
      return false;
    } finally {
      isSubmittingJalon.value = false;
    }
  }

  /// Demande un OTP pour valider un jalon
  Future<bool> requestOtp(int jalonId) async {
    errorMsg.value = null;

    try {
      await _repo.requestOtp(jalonId);

      Get.snackbar(
        'OTP envoyé',
        'Code envoyé par SMS au client',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Échec de l\'envoi OTP: ${errorMsg.value}');
      return false;
    } catch (e) {
      _showErrorSnackbar('Impossible d\'envoyer le code OTP');
      return false;
    }
  }

  /// Valide un OTP et libère le paiement du jalon
  Future<bool> validateOtp(int jalonId, String otp) async {
    errorMsg.value = null;

    try {
      await _repo.validateOtp(jalonId, otp);

      // Recharger la mission en arrière-plan
      if (currentMission.value != null) {
        await loadMission(currentMission.value!.id, showLoader: false);
      }

      Get.snackbar(
        'Jalon validé',
        'Paiement libéré avec succès',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);

      // Message spécifique pour code OTP invalide
      if (e.response?.statusCode == 422) {
        _showErrorSnackbar('Code OTP invalide ou expiré');
      } else {
        _showErrorSnackbar('Échec de la validation: ${errorMsg.value}');
      }

      return false;
    } catch (e) {
      _showErrorSnackbar('Impossible de valider le code OTP');
      return false;
    }
  }

  /// Applique un filtre et recharge les missions
  void applyFilter(String filter) {
    selectedFilter.value = filter;
    loadMissions(status: filter);
  }

  /// Rafraîchit la liste des missions
  Future<void> refresh() async {
    await loadMissions(status: selectedFilter.value, isRefresh: true);
  }

  /// Gère les erreurs Dio et retourne un message en français
  String _handleDioError(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Délai d\'attente dépassé. Vérifiez votre connexion.';
    }

    if (e.type == DioExceptionType.connectionError) {
      return 'Pas de connexion internet';
    }

    if (e.response != null) {
      final statusCode = e.response!.statusCode;

      switch (statusCode) {
        case 401:
          return 'Session expirée. Veuillez vous reconnecter.';
        case 403:
          return 'Accès refusé';
        case 404:
          return 'Ressource introuvable';
        case 422:
          // Erreurs de validation
          final data = e.response!.data;
          if (data is Map && data.containsKey('message')) {
            return data['message'] as String;
          }
          return 'Données invalides';
        case 500:
          return 'Erreur serveur. Veuillez réessayer.';
        default:
          return 'Erreur réseau (code $statusCode)';
      }
    }

    return 'Erreur de connexion au serveur';
  }

  /// Affiche un snackbar d'erreur
  void _showErrorSnackbar(String message) {
    Get.snackbar(
      'Erreur',
      message,
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 4),
      backgroundColor: Get.theme.colorScheme.error.withOpacity(0.9),
      colorText: Get.theme.colorScheme.onError,
    );
  }

  List<MissionModel> get filteredMissions => missions;
}
