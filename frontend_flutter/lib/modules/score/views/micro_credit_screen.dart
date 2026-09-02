import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/micro_credit_model.dart';
import '../../../data/repositories/micro_credit_repository.dart';

class MicroCreditController extends GetxController {
  final MicroCreditRepository _repo = MicroCreditRepository();

  final amountCtrl = TextEditingController();
  final isLoading = false.obs;
  final isSubmitting = false.obs;
  final eligibility = Rxn<MicroCreditEligibilityModel>();

  @override
  void onInit() {
    super.onInit();
    loadEligibility();
  }

  @override
  void onClose() {
    amountCtrl.dispose();
    super.onClose();
  }

  Future<void> loadEligibility() async {
    isLoading.value = true;
    try {
      final result = await _repo.getEligibility();
      eligibility.value = result;

      if (result.eligible && result.maxAmount > 0 && amountCtrl.text.isEmpty) {
        amountCtrl.text = result.maxAmount.toString();
      }
    } on DioException catch (e) {
      Get.snackbar(
        'Erreur',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> apply() async {
    final currentEligibility = eligibility.value;
    if (currentEligibility == null) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger votre éligibilité au micro-crédit.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    if (!currentEligibility.eligible) {
      Get.snackbar(
        'Non éligible',
        currentEligibility.reason ??
            'Votre score actuel ne permet pas encore d\'obtenir un micro-crédit.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final amount = int.tryParse(amountCtrl.text.trim());
    if (amount == null || amount <= 0) {
      Get.snackbar(
        'Erreur',
        'Veuillez saisir un montant valide.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    if (amount > currentEligibility.maxAmount) {
      Get.snackbar(
        'Erreur',
        'Le montant dépasse votre plafond autorisé.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    isSubmitting.value = true;
    try {
      final application = await _repo.apply(amount);
      Get.back();
      Get.snackbar(
        'Demande soumise',
        application.status == 'approuve'
            ? 'Votre demande de crédit a été approuvée. Déblocage prévu sous 2h.'
            : 'Votre demande de crédit a été soumise. Déblocage prévu sous 2h.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } on DioException catch (e) {
      Get.snackbar(
        'Erreur',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isSubmitting.value = false;
    }
  }

  String _extractMessage(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    return 'Une erreur est survenue lors de la demande de crédit.';
  }
}

class MicroCreditScreen extends GetView<MicroCreditController> {
  const MicroCreditScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Demande de Micro-crédit')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final eligibility = controller.eligibility.value;
        if (eligibility == null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Impossible de charger votre éligibilité au micro-crédit.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: controller.loadEligibility,
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            ),
          );
        }

        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Card(
                color: eligibility.eligible
                    ? AppColors.success.withValues(alpha: 0.08)
                    : AppColors.warning.withValues(alpha: 0.08),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        eligibility.eligible
                            ? 'Micro-crédit disponible'
                            : 'Micro-crédit verrouillé',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: eligibility.eligible
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text('Score actuel : ${eligibility.currentScore}/1000'),
                      Text('Seuil requis : ${eligibility.requiredScore}/1000'),
                      Text(
                        'Plafond actuel : ${eligibility.maxAmount} FCFA',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      if ((eligibility.reason ?? '').isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          eligibility.reason!,
                          style:
                              const TextStyle(color: AppColors.textSecondary),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 32),
              const Text(
                'Montant souhaité',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: controller.amountCtrl,
                keyboardType: TextInputType.number,
                enabled: eligibility.eligible,
                decoration: const InputDecoration(
                  hintText: 'Ex: 50000',
                  suffixText: 'FCFA',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 32),
              const Card(
                color: AppColors.infoLight,
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline, color: AppColors.info),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'En tant qu\'artisan certifié ProsArtisan, votre crédit est débloqué sur votre Mobile Money sous 2 heures après approbation.',
                          style: TextStyle(fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 48),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed:
                      controller.isSubmitting.value || !eligibility.eligible
                          ? null
                          : controller.apply,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                  ),
                  child: controller.isSubmitting.value
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Soumettre la demande'),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}
