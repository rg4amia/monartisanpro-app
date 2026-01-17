import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
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
  final _selectedCategory = Rx<TradeCategory?>(null);
  final _selectedLocation = Rx<GPSCoordinates?>(null);
  final _missions = <Mission>[].obs;

  // Form validation
  final formKey = GlobalKey<FormState>();

  // Getters
  bool get isLoading => _isLoading.value;
  TradeCategory? get selectedCategory => _selectedCategory.value;
  GPSCoordinates? get selectedLocation => _selectedLocation.value;
  List<Mission> get missions => _missions;

  bool get canSubmit =>
      selectedCategory != null &&
      selectedLocation != null &&
      descriptionController.text.trim().isNotEmpty &&
      budgetMinController.text.isNotEmpty &&
      budgetMaxController.text.isNotEmpty;

  void setCategory(TradeCategory category) {
    _selectedCategory.value = category;
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

      final mission = await _createMissionUseCase.execute(
        description: descriptionController.text.trim(),
        category: selectedCategory!,
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
