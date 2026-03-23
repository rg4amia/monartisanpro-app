import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../data/models/devis_model.dart';
import '../../../data/repositories/devis_repository.dart';

class DevisController extends GetxController {
  final DevisRepository _repo = DevisRepository();

  final devisList = <DevisModel>[].obs;
  final currentDevis = Rx<DevisModel?>(null);
  final isLoading = false.obs;
  final isSubmitting = false.obs;
  final errorMsg = Rx<String?>(null);

  // Données pour la création de devis (artisan)
  final lignes = <DevisLigne>[].obs;
  final jalons = <DevisJalon>[].obs;

  // Mission associée
  int? missionId;

  /// Initialise le controller avec les données de la mission
  void initializeWithMission(int id) {
    missionId = id;
    loadMissionDevis(id);
  }

  /// Charge tous les devis d'une mission
  Future<void> loadMissionDevis(int missionId) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final result = await _repo.getMissionDevis(missionId);
      devisList.value = result;

      // Si un devis existe, le définir comme courant
      if (result.isNotEmpty) {
        currentDevis.value = result.first;
      }

      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
    } catch (e) {
      errorMsg.value = 'Impossible de charger les devis';
      _showErrorSnackbar(errorMsg.value!);
    } finally {
      isLoading.value = false;
    }
  }

  /// Charge un devis spécifique
  Future<void> loadDevis(int id) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final result = await _repo.getDevis(id);
      currentDevis.value = result;
      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
    } catch (e) {
      errorMsg.value = 'Impossible de charger le devis';
      _showErrorSnackbar(errorMsg.value!);
    } finally {
      isLoading.value = false;
    }
  }

  /// Ajoute une ligne au devis (artisan)
  void addLigne({
    required String type,
    required String description,
    required int montant,
    String? source,
    int? quantity,
    int? unitPrice,
    String? sku,
    int? supplierProductId,
  }) {
    lignes.add(DevisLigne(
      type: type,
      description: description,
      montant: montant,
      source: source,
      quantity: quantity,
      unitPrice: unitPrice,
      sku: sku,
      supplierProductId: supplierProductId,
    ));
  }

  /// Supprime une ligne du devis
  void removeLigne(int index) {
    if (index >= 0 && index < lignes.length) {
      lignes.removeAt(index);
    }
  }

  /// Ajoute un jalon au devis (artisan)
  void addJalon({
    required String description,
    required int montant,
    required String dateCible,
  }) {
    jalons.add(DevisJalon(
      ordre: jalons.length + 1,
      description: description,
      montant: montant,
      dateCible: dateCible,
    ));
  }

  /// Supprime un jalon du devis
  void removeJalon(int index) {
    if (index >= 0 && index < jalons.length) {
      jalons.removeAt(index);

      // Réorganiser les ordres
      for (int i = 0; i < jalons.length; i++) {
        jalons[i] = DevisJalon(
          ordre: i + 1,
          description: jalons[i].description,
          montant: jalons[i].montant,
          dateCible: jalons[i].dateCible,
        );
      }
    }
  }

  /// Calcule le montant total des lignes main d'œuvre
  int get totalMo =>
      lignes.where((l) => l.type == 'mo').fold(0, (sum, l) => sum + l.montant);

  /// Calcule le montant total des lignes matériaux
  int get totalMat =>
      lignes.where((l) => l.type == 'mat').fold(0, (sum, l) => sum + l.montant);

  /// Calcule le montant total général
  int get totalGeneral => totalMo + totalMat;

  /// Calcule le ratio matériaux/total (0.0 à 1.0)
  double get ratioMateriaux {
    if (totalGeneral == 0) return 0.0;
    return totalMat / totalGeneral;
  }

  /// Valide que le devis peut être soumis
  bool validateDevis() {
    errorMsg.value = null;

    if (lignes.isEmpty) {
      errorMsg.value = 'Ajoutez au moins une ligne au devis';
      return false;
    }

    if (jalons.isEmpty) {
      errorMsg.value = 'Ajoutez au moins un jalon au devis';
      return false;
    }

    // Vérifier que la somme des jalons = total général
    final totalJalons = jalons.fold(0, (sum, j) => sum + j.montant);
    if (totalJalons != totalGeneral) {
      errorMsg.value =
          'La somme des jalons (${_formatFCFA(totalJalons)}) doit être égale au total général (${_formatFCFA(totalGeneral)})';
      return false;
    }

    return true;
  }

  /// Crée un nouveau devis (artisan)
  Future<bool> createDevis({required int missionId}) async {
    if (!validateDevis()) {
      _showErrorSnackbar(errorMsg.value!);
      return false;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final devis = await _repo.createDevis(
        missionId: missionId,
        lignes: lignes.toList(),
        jalons: jalons.toList(),
      );

      currentDevis.value = devis;

      Get.snackbar(
        'Devis créé',
        'Votre devis a été envoyé au client pour validation',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      // Réinitialiser les listes
      lignes.clear();
      jalons.clear();

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de la création: ${errorMsg.value}');
      return false;
    } catch (e) {
      errorMsg.value = 'Impossible de créer le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Met à jour un devis existant (artisan)
  Future<bool> updateDevis(int devisId) async {
    if (!validateDevis()) {
      _showErrorSnackbar(errorMsg.value!);
      return false;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final devis = await _repo.updateDevis(
        id: devisId,
        lignes: lignes.toList(),
        jalons: jalons.toList(),
      );

      currentDevis.value = devis;

      Get.snackbar(
        'Devis modifié',
        'Les modifications ont été envoyées au client',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de la modification: ${errorMsg.value}');
      return false;
    } catch (e) {
      errorMsg.value = 'Impossible de modifier le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Accepte un devis (client)
  Future<bool> acceptDevis(int devisId, {String provider = 'wave'}) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      await _repo.acceptDevis(devisId, provider: provider);

      // Recharger le devis pour obtenir le nouveau statut
      await loadDevis(devisId);

      Get.snackbar(
        'Devis accepté',
        'Vous allez être redirigé vers le paiement',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de l\'acceptation: ${errorMsg.value}');
      return false;
    } catch (e) {
      errorMsg.value = 'Impossible d\'accepter le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Refuse un devis (client)
  Future<bool> refuseDevis(int devisId) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      await _repo.refuseDevis(devisId);

      // Recharger le devis pour obtenir le nouveau statut
      await loadDevis(devisId);

      Get.snackbar(
        'Devis refusé',
        'L\'artisan en sera informé',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors du refus: ${errorMsg.value}');
      return false;
    } catch (e) {
      errorMsg.value = 'Impossible de refuser le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Réinitialise le formulaire de création de devis
  void resetForm() {
    lignes.clear();
    jalons.clear();
    errorMsg.value = null;
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
          return 'Devis introuvable';
        case 422:
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
      backgroundColor: Get.theme.colorScheme.error.withValues(alpha: 0.9),
      colorText: Get.theme.colorScheme.onError,
    );
  }

  /// Formate un montant en FCFA
  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}
