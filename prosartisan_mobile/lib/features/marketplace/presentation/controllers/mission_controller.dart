import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/trade.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/usecases/create_mission_usecase.dart';

class MissionController extends GetxController {
  final CreateMissionUseCase _createMissionUseCase;

  MissionController(this._createMissionUseCase);

  // Form controllers
  final descriptionController = TextEditingController();
  final budgetMinController = TextEditingController();
  final budgetMaxController = TextEditingController();

  // Observable state
  final _isLoading = false.obs;
  // ignore: unused_field
  final _selectedCategory = Rx<TradeCategory?>(
    null,
  ); // Deprecated but might keep for compatibility
  final _selectedTrade = Rx<Trade?>(null);
  final _selectedLocation = Rx<GPSCoordinates?>(null);
  final _missions = <Mission>[].obs;

  // Form validation
  final formKey = GlobalKey<FormState>();

  // Getters
  bool get isLoading => _isLoading.value;
  // ignore: deprecated_member_use_from_same_package
  TradeCategory? get selectedCategory => _selectedCategory.value;
  Trade? get selectedTrade => _selectedTrade.value;
  GPSCoordinates? get selectedLocation => _selectedLocation.value;
  List<Mission> get missions => _missions;

  bool get canSubmit =>
      selectedTrade != null &&
      selectedLocation != null &&
      descriptionController.text.trim().isNotEmpty &&
      budgetMinController.text.isNotEmpty &&
      budgetMaxController.text.isNotEmpty;

  void setCategory(TradeCategory category) {
    _selectedCategory.value = category;
  }

  void setTrade(Trade trade) {
    _selectedTrade.value = trade;
    // Optionally derive category from trade if needed, but we rely on tradeId now.
    // Ideally we map Sector to TradeCategory here if we still need it for backend enum constraint.
    // For now we will pass a default or try to map.
  }

  void setLocation(GPSCoordinates location) {
    _selectedLocation.value = location;
  }

  Future<void> createMission() async {
    if (!formKey.currentState!.validate() || !canSubmit) {
      Get.snackbar('Erreur', 'Veuillez remplir tous les champs requis');
      return;
    }

    try {
      _isLoading.value = true;

      final budgetMin = double.tryParse(budgetMinController.text);
      final budgetMax = double.tryParse(budgetMaxController.text);

      if (budgetMin == null || budgetMax == null) {
        Get.snackbar('Erreur', 'Budget invalide');
        return;
      }

      // Default category fallback to satisfy non-nullable requirement in UseCase/Repo
      // In real app, we should map Sector to TradeCategory properly or update backend to not require it.
      // We'll effectively send "OTHER" or rely on backend to ignore it if trade_id is set?
      // But CreateMissionRequest still requires it to be PLUMBER/ELECTRICIAN/MASON.
      // So we MUST pick one. This is a hack until backend validation is fully relaxed or categories aligned.
      // Since specific trades map to specific categories, we should try to map it.
      // For now, let's default to MASON if unknown, or try to guess.
      // Actually, TradeCategory is required by UseCase.
      TradeCategory category = TradeCategory.mason; // Default
      // In a real scenario, the Trade entity would have a category or we fetch it.

      final mission = await _createMissionUseCase.execute(
        description: descriptionController.text.trim(),
        category: category,
        tradeId: selectedTrade!.id,
        location: selectedLocation!,
        budgetMin: budgetMin,
        budgetMax: budgetMax,
      );

      _missions.add(mission);

      // Clear form
      _clearForm();

      Get.snackbar(
        'Succès',
        'Mission créée avec succès',
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );

      // Navigate back or to mission detail
      Get.back();
    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors de la création: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  void _clearForm() {
    descriptionController.clear();
    budgetMinController.clear();
    budgetMaxController.clear();
    _selectedCategory.value = null;
    _selectedLocation.value = null;
  }

  String? validateDescription(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Description requise';
    }
    if (value.trim().length < 10) {
      return 'Description trop courte (minimum 10 caractères)';
    }
    return null;
  }

  String? validateBudget(String? value, {required bool isMin}) {
    if (value == null || value.isEmpty) {
      return 'Budget requis';
    }

    final budget = double.tryParse(value);
    if (budget == null) {
      return 'Budget invalide';
    }

    if (budget <= 0) {
      return 'Budget doit être supérieur à 0';
    }

    if (!isMin) {
      final minBudget = double.tryParse(budgetMinController.text);
      if (minBudget != null && budget <= minBudget) {
        return 'Budget max doit être supérieur au budget min';
      }
    }

    return null;
  }

  @override
  void onClose() {
    descriptionController.dispose();
    budgetMinController.dispose();
    budgetMaxController.dispose();
    super.onClose();
  }
}
