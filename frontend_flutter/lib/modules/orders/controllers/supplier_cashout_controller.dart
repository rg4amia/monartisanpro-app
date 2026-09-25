import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/supplier_cashout_model.dart';
import '../../../data/repositories/supplier_cashout_repository.dart';

class SupplierCashoutController extends GetxController {
  final SupplierCashoutRepository _repository = SupplierCashoutRepository();

  final RxBool isLoading = false.obs;
  final RxBool isSubmitting = false.obs;
  final RxnString errorMsg = RxnString();

  final Rx<SupplierCashoutStatsModel> stats =
      SupplierCashoutStatsModel.empty().obs;
  final RxList<SupplierCashoutModel> cashouts = <SupplierCashoutModel>[].obs;

  // Formulaire de retrait
  final TextEditingController amountController = TextEditingController();
  final TextEditingController beneficiaryNameController = TextEditingController();
  final TextEditingController beneficiaryPhoneController = TextEditingController();
  final TextEditingController bankNameController = TextEditingController();
  final TextEditingController bankAccountController = TextEditingController();
  final TextEditingController notesController = TextEditingController();

  final RxString selectedMode = 'wave'.obs;
  final RxInt calculatedCommission = 0.obs;
  final RxInt calculatedNet = 0.obs;

  @override
  void onInit() {
    super.onInit();
    amountController.addListener(_onAmountChanged);
    load();
  }

  @override
  void onClose() {
    amountController.dispose();
    beneficiaryNameController.dispose();
    beneficiaryPhoneController.dispose();
    bankNameController.dispose();
    bankAccountController.dispose();
    notesController.dispose();
    super.onClose();
  }

  void _onAmountChanged() {
    final raw = int.tryParse(amountController.text.trim()) ?? 0;
    final commission = (raw * 0.025).round();
    calculatedCommission.value = commission;
    calculatedNet.value = raw > commission ? raw - commission : 0;
  }

  void setQuickPercent(double percent) {
    final available = stats.value.availableBalance;
    if (available <= 0) return;
    final target = (available * percent).floor();
    amountController.text = '$target';
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final res = await _repository.getCashouts();
      stats.value = res['stats'] as SupplierCashoutStatsModel;
      cashouts.assignAll(res['cashouts'] as List<SupplierCashoutModel>);
    } catch (e) {
      errorMsg.value = 'Erreur lors du chargement des informations de retrait.';
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> submitCashout() async {
    final amount = int.tryParse(amountController.text.trim()) ?? 0;
    if (amount < 1000) {
      Get.snackbar(
        'Montant invalide',
        'Le montant minimum de retrait est de 1 000 FCFA.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }

    if (amount > stats.value.availableBalance) {
      Get.snackbar(
        'Solde insuffisant',
        'Le montant demandé dépasse votre solde disponible.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }

    if (selectedMode.value == 'virement_bancaire' &&
        (bankNameController.text.trim().isEmpty ||
            bankAccountController.text.trim().isEmpty)) {
      Get.snackbar(
        'Informations bancaires requises',
        'Veuillez renseigner le nom de la banque et le numéro de compte / RIB.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }

    isSubmitting.value = true;
    try {
      final newCashout = await _repository.requestCashout(
        montantBrut: amount,
        modeRetrait: selectedMode.value,
        beneficiaryName: beneficiaryNameController.text.trim(),
        beneficiaryPhone: beneficiaryPhoneController.text.trim(),
        bankName: selectedMode.value == 'virement_bancaire'
            ? bankNameController.text.trim()
            : null,
        bankAccountNumber: selectedMode.value == 'virement_bancaire'
            ? bankAccountController.text.trim()
            : null,
        notes: notesController.text.trim(),
      );

      cashouts.insert(0, newCashout);
      amountController.clear();
      notesController.clear();

      Get.snackbar(
        'Demande enregistrée',
        'Votre demande ${newCashout.reference} est enregistrée (règlement garanti J+1).',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );

      // Recharger les statistiques à jour
      await load();
      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        e.toString().replaceAll('Exception: ', ''),
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }
}
