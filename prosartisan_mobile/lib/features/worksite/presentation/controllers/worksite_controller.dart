import 'dart:io';
import 'package:get/get.dart';
import 'package:prosartisan_mobile/features/worksite/data/repositories/worksite_repository.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/chantier.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';

/// Controller for worksite management
///
/// Manages state for chantiers and jalons, handles API calls
/// Requirements: 6.1, 6.2, 6.3
class WorksiteController extends GetxController {
  final WorksiteRepository _repository;

  WorksiteController(this._repository);

  // Observable state
  final _chantiers = <Chantier>[].obs;
  final _currentChantier = Rxn<Chantier>();
  final _currentJalon = Rxn<Jalon>();
  final _isLoading = false.obs;
  final _error = Rxn<String>();

  // Getters
  List<Chantier> get chantiers => _chantiers.value;
  Chantier? get currentChantier => _currentChantier.value;
  Jalon? get currentJalon => _currentJalon.value;
  bool get isLoading => _isLoading.value;
  String? get error => _error.value;

  // Filtered chantiers
  List<Chantier> get activeChantiers =>
      _chantiers.where((c) => c.isInProgress).toList();

  List<Chantier> get completedChantiers =>
      _chantiers.where((c) => c.isCompleted).toList();

  @override
  void onInit() {
    super.onInit();
    loadChantiers();
  }

  /// Load all chantiers for the current user
  Future<void> loadChantiers({String? type}) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final chantiers = await _repository.getChantiers(type: type);
      _chantiers.value = chantiers;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger les chantiers: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Load chantier details
  Future<void> loadChantier(String chantierId) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final chantier = await _repository.getChantier(chantierId);
      _currentChantier.value = chantier;

      // Update the chantier in the list if it exists
      final index = _chantiers.indexWhere((c) => c.id == chantierId);
      if (index != -1) {
        _chantiers[index] = chantier;
      }
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger le chantier: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Create a new chantier
  Future<Chantier?> createChantier({
    required String missionId,
    required String clientId,
    required String artisanId,
    List<Map<String, dynamic>>? milestones,
  }) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final chantier = await _repository.createChantier(
        missionId: missionId,
        clientId: clientId,
        artisanId: artisanId,
        milestones: milestones,
      );

      _chantiers.add(chantier);
      _currentChantier.value = chantier;

      Get.snackbar(
        'Succès',
        'Chantier créé avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return chantier;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de créer le chantier: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    } finally {
      _isLoading.value = false;
    }
  }

  /// Load jalon details
  Future<void> loadJalon(String jalonId) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final jalon = await _repository.getJalon(jalonId);
      _currentJalon.value = jalon;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger le jalon: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoading.value = false;
    }
  }

  /// Submit proof for a jalon
  Future<bool> submitProof({
    required String jalonId,
    required File photo,
    required double latitude,
    required double longitude,
    double? accuracy,
    DateTime? capturedAt,
    Map<String, dynamic>? exifData,
  }) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final updatedJalon = await _repository.submitProof(
        jalonId: jalonId,
        photo: photo,
        latitude: latitude,
        longitude: longitude,
        accuracy: accuracy,
        capturedAt: capturedAt,
        exifData: exifData,
      );

      _currentJalon.value = updatedJalon;
      _updateJalonInChantier(updatedJalon);

      Get.snackbar(
        'Succès',
        'Preuve soumise avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de soumettre la preuve: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      _isLoading.value = false;
    }
  }

  /// Validate a jalon
  Future<bool> validateJalon(String jalonId, {String? comment}) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final updatedJalon = await _repository.validateJalon(
        jalonId,
        comment: comment,
      );
      _currentJalon.value = updatedJalon;
      _updateJalonInChantier(updatedJalon);

      Get.snackbar(
        'Succès',
        'Jalon validé avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de valider le jalon: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      _isLoading.value = false;
    }
  }

  /// Contest a jalon
  Future<bool> contestJalon(String jalonId, String reason) async {
    try {
      _isLoading.value = true;
      _error.value = null;

      final updatedJalon = await _repository.contestJalon(jalonId, reason);
      _currentJalon.value = updatedJalon;
      _updateJalonInChantier(updatedJalon);

      Get.snackbar(
        'Succès',
        'Jalon contesté avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      _error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de contester le jalon: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      _isLoading.value = false;
    }
  }

  /// Refresh current chantier
  Future<void> refreshCurrentChantier() async {
    if (_currentChantier.value != null) {
      await loadChantier(_currentChantier.value!.id);
    }
  }

  /// Clear error
  void clearError() {
    _error.value = null;
  }

  /// Set current chantier
  void setCurrentChantier(Chantier chantier) {
    _currentChantier.value = chantier;
  }

  /// Set current jalon
  void setCurrentJalon(Jalon jalon) {
    _currentJalon.value = jalon;
  }

  /// Update jalon in current chantier
  void _updateJalonInChantier(Jalon updatedJalon) {
    if (_currentChantier.value != null) {
      final chantier = _currentChantier.value!;
      final updatedMilestones = chantier.milestones.map((jalon) {
        return jalon.id == updatedJalon.id ? updatedJalon : jalon;
      }).toList();

      // Create updated chantier with new milestones
      // Note: This is a simplified update - in a real app you might want to
      // recalculate progress and other derived properties
      final updatedChantier = Chantier(
        id: chantier.id,
        missionId: chantier.missionId,
        clientId: chantier.clientId,
        artisanId: chantier.artisanId,
        status: chantier.status,
        statusLabel: chantier.statusLabel,
        startedAt: chantier.startedAt,
        completedAt: chantier.completedAt,
        progressPercentage: chantier.progressPercentage,
        canBeCompleted: chantier.canBeCompleted,
        milestonesCount: chantier.milestonesCount,
        completedMilestonesCount: chantier.completedMilestonesCount,
        pendingMilestonesCount: chantier.pendingMilestonesCount,
        totalLaborAmount: chantier.totalLaborAmount,
        completedLaborAmount: chantier.completedLaborAmount,
        nextMilestone: chantier.nextMilestone,
        milestones: updatedMilestones,
      );

      _currentChantier.value = updatedChantier;

      // Update in the list as well
      final index = _chantiers.indexWhere((c) => c.id == chantier.id);
      if (index != -1) {
        _chantiers[index] = updatedChantier;
      }
    }
  }
}
