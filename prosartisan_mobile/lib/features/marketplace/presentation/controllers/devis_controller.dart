import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/usecases/create_devis_usecase.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/usecases/accept_devis_usecase.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/repositories/devis_repository.dart';

class DevisController extends GetxController {
  final CreateDevisUseCase _createDevisUseCase;
  final AcceptDevisUseCase _acceptDevisUseCase;
  final DevisRepository _devisRepository;

  DevisController(
    this._createDevisUseCase,
    this._acceptDevisUseCase,
    this._devisRepository,
  );

  // Observable state
  final _isLoading = false.obs;
  final _devisList = <Devis>[].obs;
  final _lineItems = <DevisLine>[].obs;
  final _selectedMission = Rx<Mission?>(null);

  // Form controllers for line items
  final descriptionController = TextEditingController();
  final quantityController = TextEditingController();
  final unitPriceController = TextEditingController();
  final _selectedLineType = Rx<DevisLineType?>(null);

  // Form validation
  final formKey = GlobalKey<FormState>();

  // Getters
  bool get isLoading => _isLoading.value;
  List<Devis> get devisList => _devisList;
  List<DevisLine> get lineItems => _lineItems;
  Mission? get selectedMission => _selectedMission.value;
  DevisLineType? get selectedLineType => _selectedLineType.value;

  double get totalMaterialsAmount => _lineItems
      .where((item) => item.type == DevisLineType.material)
      .fold(0.0, (sum, item) => sum + item.total);

  double get totalLaborAmount => _lineItems
      .where((item) => item.type == DevisLineType.labor)
      .fold(0.0, (sum, item) => sum + item.total);

  double get totalAmount => totalMaterialsAmount + totalLaborAmount;

  bool get canSubmitDevis => 
      _lineItems.isNotEmpty && 
      selectedMission != null &&
      totalAmount > 0;

  void setMission(Mission mission) {
    _selectedMission.value = mission;
    loadDevisForMission(mission.id);
  }

  void setLineType(DevisLineType type) {
    _selectedLineType.value = type;
  }

  Future<void> loadDevisForMission(String missionId) async {
    try {
      _isLoading.value = true;
      final devis = await _devisRepository.getDevisByMissionId(missionId);
      _devisList.value = devis;
    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors du chargement des devis: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  void addLineItem() {
    if (!_validateLineItem()) return;

    final quantity = int.tryParse(quantityController.text);
    final unitPrice = double.tryParse(unitPriceController.text);

    if (quantity == null || unitPrice == null || selectedLineType == null) {
      Get.snackbar('Erreur', 'Données invalides');
      return;
    }

    final lineItem = DevisLine(
      description: descriptionController.text.trim(),
      quantity: quantity,
      unitPrice: unitPrice,
      type: selectedLineType!,
    );

    _lineItems.add(lineItem);
    _clearLineItemForm();
  }

  void removeLineItem(int index) {
    if (index >= 0 && index < _lineItems.length) {
      _lineItems.removeAt(index);
    }
  }

  bool _validateLineItem() {
    if (descriptionController.text.trim().isEmpty) {
      Get.snackbar('Erreur', 'Description requise');
      return false;
    }

    final quantity = int.tryParse(quantityController.text);
    if (quantity == null || quantity <= 0) {
      Get.snackbar('Erreur', 'Quantité invalide');
      return false;
    }

    final unitPrice = double.tryParse(unitPriceController.text);
    if (unitPrice == null || unitPrice <= 0) {
      Get.snackbar('Erreur', 'Prix unitaire invalide');
      return false;
    }

    if (selectedLineType == null) {
      Get.snackbar('Erreur', 'Type de ligne requis');
      return false;
    }

    return true;
  }

  void _clearLineItemForm() {
    descriptionController.clear();
    quantityController.clear();
    unitPriceController.clear();
    _selectedLineType.value = null;
  }

  Future<void> submitDevis() async {
    if (!canSubmitDevis) {
      Get.snackbar('Erreur', 'Impossible de soumettre le devis');
      return;
    }

    try {
      _isLoading.value = true;

      final devis = await _createDevisUseCase.execute(
        missionId: selectedMission!.id,
        lineItems: List.from(_lineItems),
      );

      _devisList.add(devis);
      _clearForm();

      Get.snackbar(
        'Succès',
        'Devis soumis avec succès',
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );

      Get.back();

    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors de la soumission: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  Future<void> acceptDevis(String devisId) async {
    try {
      _isLoading.value = true;

      final acceptedDevis = await _acceptDevisUseCase.execute(devisId);
      
      // Update the devis in the list
      final index = _devisList.indexWhere((d) => d.id == devisId);
      if (index != -1) {
        _devisList[index] = acceptedDevis;
      }

      Get.snackbar(
        'Succès',
        'Devis accepté avec succès',
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );

    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors de l\'acceptation: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  Future<void> rejectDevis(String devisId) async {
    try {
      _isLoading.value = true;

      final rejectedDevis = await _devisRepository.rejectDevis(devisId);
      
      // Update the devis in the list
      final index = _devisList.indexWhere((d) => d.id == devisId);
      if (index != -1) {
        _devisList[index] = rejectedDevis;
      }

      Get.snackbar(
        'Succès',
        'Devis rejeté',
        backgroundColor: Colors.orange,
        colorText: Colors.white,
      );

    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors du rejet: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  void _clearForm() {
    _lineItems.clear();
    _clearLineItemForm();
  }

  String formatCurrency(double amount) {
    return '${amount.toStringAsFixed(0).replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (Match m) => '${m[1]} ',
    )} FCFA';
  }

  @override
  void onClose() {
    descriptionController.dispose();
    quantityController.dispose();
    unitPriceController.dispose();
    super.onClose();
  }
}