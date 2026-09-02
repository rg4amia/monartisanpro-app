import 'dart:async';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/jalon_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/devis_repository.dart';
import '../../../data/repositories/mission_repository.dart';

class MissionsController extends GetxController {
  final MissionRepository _repo = MissionRepository();
  final DevisRepository _devisRepo = DevisRepository();

  final missions = <MissionModel>[].obs;
  final currentMission = Rx<MissionModel?>(null);
  final currentMissionDevis = <DevisModel>[].obs;
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
  ///
  /// [id] - ID de la mission
  /// [showLoader] - Afficher le loader (false pour refresh en arrière-plan)
  /// [forceRefresh] - Forcer l'appel API (ignorer le cache)
  Future<void> loadMission(
    int id, {
    bool showLoader = true,
    bool forceRefresh = false,
  }) async {
    if (showLoader) {
      isLoading.value = true;
    }
    errorMsg.value = null;

    try {
      currentMissionDevis.clear();

      final results = await Future.wait([
        _repo.getMission(id, forceRefresh: forceRefresh),
        _repo.getJalons(id, forceRefresh: forceRefresh).catchError((_) => <JalonModel>[]),
      ]);

      currentMission.value = results[0] as MissionModel;
      jalons.value = results[1] as List<JalonModel>;

      try {
        currentMissionDevis.value = await _devisRepo.getMissionDevis(id);
      } catch (_) {
        currentMissionDevis.clear();
      }

      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);

      // Ne pas afficher d'erreur si on a des données en cache
      if (currentMission.value == null && jalons.isEmpty) {
        _showErrorSnackbar(errorMsg.value!);
      }
    } catch (e) {
      errorMsg.value = 'Impossible de charger les détails de la mission';

      if (currentMission.value == null && jalons.isEmpty) {
        _showErrorSnackbar(errorMsg.value!);
      }
    } finally {
      if (showLoader) {
        isLoading.value = false;
      }
    }
  }

  DevisModel? get latestDevis =>
      currentMissionDevis.isEmpty ? null : currentMissionDevis.first;

  JalonModel? get nextPendingJalon {
    for (final jalon in jalons) {
      if (jalon.statut == 'en_attente') {
        return jalon;
      }
    }
    return null;
  }

  JalonModel? get nextSubmittedJalon {
    for (final jalon in jalons) {
      if (jalon.statut == 'soumis') {
        return jalon;
      }
    }
    return null;
  }

  bool get hasReferentPendingValidation {
    final mission = currentMission.value;
    if (mission == null || !mission.needsReferent) {
      return false;
    }

    return jalons.any((jalon) => jalon.isValidated && !jalon.isPaid);
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

  /// Crée une nouvelle mission
  ///
  /// [artisanId] - ID de l'artisan sélectionné
  /// [description] - Description des travaux
  /// [category] - Catégorie des travaux
  /// [urgency] - Niveau d'urgence (faible, moyen, urgent)
  /// [location] - Localisation optionnelle
  /// [photos] - Liste des URLs des photos/vidéos uploadées
  Future<MissionModel?> createMission({
    required int artisanId,
    required String description,
    required String category,
    required String urgency,
    int? sectorId,
    int? tradeId,
    double? lat,
    double? lng,
    String? location,
    List<String>? photos,
  }) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final mission = await _repo.createMission(
        artisanId: artisanId,
        description: description,
        category: category,
        urgency: urgency,
        sectorId: sectorId,
        tradeId: tradeId,
        lat: lat,
        lng: lng,
        location: location,
        photos: photos,
      );

      // Invalider le cache pour forcer le refresh
      await _repo.clearCache();

      // Recharger la liste des missions
      await loadMissions();

      // Message de succès avec détails
      Get.snackbar(
        'Mission créée',
        'Votre demande a été envoyée à l\'artisan. Vous recevrez une notification dès qu\'il aura soumis un devis.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 4),
      );

      return mission;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);

      // KYC non validé → rediriger vers le parcours KYC
      if (e.response?.statusCode == 403) {
        Get.snackbar(
          'KYC requis',
          errorMsg.value!,
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
        unawaited(Get.toNamed(Routes.kycCni));
        return null;
      }

      _showErrorSnackbar('Erreur lors de la création: ${errorMsg.value}');
      return null;
    } catch (e) {
      errorMsg.value = 'Impossible de créer la mission';
      _showErrorSnackbar(errorMsg.value!);
      return null;
    } finally {
      isLoading.value = false;
    }
  }

  /// Téléverse un fichier (image/vidéo) sur le serveur
  Future<String> uploadFile(String filePath) async {
    return await _repo.uploadFile(filePath);
  }

  /// Artisan accepte la demande de devis
  Future<bool> acceptMissionRequest(int missionId) async {
    isLoading.value = true;
    try {
      final updated = await _repo.acceptRequest(missionId);
      currentMission.value = updated;
      await loadMission(missionId, forceRefresh: true);
      Get.snackbar(
        'Demande acceptée',
        'Vous avez accepté la demande. Vous pouvez maintenant soumettre un devis.',
        snackPosition: SnackPosition.TOP,
      );
      return true;
    } on DioException catch (e) {
      final msg = _handleDioError(e);
      _showErrorSnackbar(msg);
      return false;
    } catch (e) {
      _showErrorSnackbar('Erreur lors de l\'acceptation');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Artisan refuse la demande de devis
  Future<bool> rejectMissionRequest(int missionId) async {
    isLoading.value = true;
    try {
      final updated = await _repo.rejectRequest(missionId);
      currentMission.value = updated;
      await loadMission(missionId, forceRefresh: true);
      Get.snackbar(
        'Demande refusée',
        'La demande de devis a été refusée.',
        snackPosition: SnackPosition.TOP,
      );
      return true;
    } on DioException catch (e) {
      final msg = _handleDioError(e);
      _showErrorSnackbar(msg);
      return false;
    } catch (e) {
      _showErrorSnackbar('Erreur lors du refus');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Soumet un jalon pour validation avec photos géolocalisées
  ///
  /// [jalonId] - ID du jalon à soumettre
  /// [photos] - Liste de photos géolocalisées (optionnel)
  ///
  /// Format photos: [{"url": "...", "lat": 5.3, "lng": -4.0, "taken_at": "2025-03-07T10:00:00Z"}]
  Future<bool> submitJalon(
    int jalonId, {
    List<Map<String, dynamic>>? photos,
  }) async {
    isSubmittingJalon.value = true;
    errorMsg.value = null;

    try {
      // Passer le missionId pour invalider le cache
      await _repo.submitJalon(
        jalonId,
        photos: photos,
        missionId: currentMission.value?.id,
      );

      // Recharger la mission en arrière-plan avec forceRefresh
      if (currentMission.value != null) {
        await loadMission(
          currentMission.value!.id,
          showLoader: false,
          forceRefresh: true,
        );
      }

      Get.snackbar(
        'Jalon soumis',
        'Le jalon a été soumis avec succès. Le client va recevoir un OTP.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
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
      // Passer le missionId pour invalider le cache
      await _repo.validateOtp(
        jalonId,
        otp,
        missionId: currentMission.value?.id,
      );

      // Recharger la mission en arrière-plan avec forceRefresh
      if (currentMission.value != null) {
        await loadMission(
          currentMission.value!.id,
          showLoader: false,
          forceRefresh: true,
        );
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

  /// Upload de preuves supplémentaires pour un jalon (artisan)
  Future<bool> uploadJalonPhotos(int jalonId, List<Map<String, dynamic>> localFiles) async {
    isSubmittingJalon.value = true;
    errorMsg.value = null;

    try {
      await _repo.uploadJalonPhotos(
        jalonId,
        localFiles,
        missionId: currentMission.value?.id,
      );

      // Recharger la mission en arrière-plan
      if (currentMission.value != null) {
        await loadMission(
          currentMission.value!.id,
          showLoader: false,
          forceRefresh: true,
        );
      }

      Get.snackbar(
        'Preuves envoyées',
        'Preuves supplémentaires uploadées avec succès.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Échec de l\'upload: ${errorMsg.value}');
      return false;
    } catch (e) {
      _showErrorSnackbar('Impossible d\'uploader les preuves');
      return false;
    } finally {
      isSubmittingJalon.value = false;
    }
  }

  /// Le client accepte directement les preuves de travail et valide le jalon sans OTP
  Future<bool> acceptJalonProofs(int jalonId) async {
    isSubmittingJalon.value = true;
    errorMsg.value = null;

    try {
      await _repo.acceptJalonProofs(
        jalonId,
        missionId: currentMission.value?.id,
      );

      // Recharger la mission en arrière-plan
      if (currentMission.value != null) {
        await loadMission(
          currentMission.value!.id,
          showLoader: false,
          forceRefresh: true,
        );
      }

      Get.snackbar(
        'Preuves acceptées',
        'Le jalon a été validé avec succès.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Échec de la validation: ${errorMsg.value}');
      return false;
    } catch (e) {
      _showErrorSnackbar('Impossible de valider le jalon');
      return false;
    } finally {
      isSubmittingJalon.value = false;
    }
  }

  Future<bool> updateMissionStatus(int missionId, String status) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      await _repo.updateStatus(missionId, status);
      await loadMission(missionId, showLoader: false, forceRefresh: true);
      await loadMissions(status: selectedFilter.value, isRefresh: true);

      Get.snackbar(
        'Mission mise a jour',
        'Le statut de la mission a ete actualise.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } catch (_) {
      _showErrorSnackbar('Impossible de mettre a jour la mission');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Applique un filtre et recharge les missions
  void applyFilter(String filter) {
    selectedFilter.value = filter;
    loadMissions(status: filter);
  }

  /// Rafraîchit la liste des missions
  @override
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
          // Extraire le message backend (ex: KYC non validé)
          final data403 = e.response!.data;
          if (data403 is Map && data403.containsKey('message')) {
            return data403['message'] as String;
          }
          return 'Accès refusé';
        case 404:
          return 'Ressource introuvable';
        case 422:
          // Erreurs de validation (ex: Rejet IA CV)
          final data = e.response!.data;
          if (data is Map) {
            if (data.containsKey('errors')) {
              final errors = data['errors'] as Map;
              if (errors.isNotEmpty) {
                final firstError = errors.values.first;
                if (firstError is List && firstError.isNotEmpty) {
                  return firstError.first.toString();
                }
              }
            }
            if (data.containsKey('message')) {
              return data['message'] as String;
            }
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
      backgroundColor: Get.theme.colorScheme.error.withValues(alpha: 0.9),
      colorText: Get.theme.colorScheme.onError,
    );
  }

  List<MissionModel> get filteredMissions => missions;
}
