import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/micro_credit_model.dart';
import '../../../data/repositories/micro_credit_repository.dart';

class MicroCreditController extends GetxController {
  final MicroCreditRepository _repo = MicroCreditRepository();

  final amountCtrl = TextEditingController();
  final repayCtrl = TextEditingController();
  final isLoading = false.obs;
  final isSubmitting = false.obs;
  final isRepaying = false.obs;
  final eligibility = Rxn<MicroCreditEligibilityModel>();

  @override
  void onInit() {
    super.onInit();
    loadEligibility();
  }

  @override
  void onClose() {
    amountCtrl.dispose();
    repayCtrl.dispose();
    super.onClose();
  }

  Future<void> loadEligibility() async {
    isLoading.value = true;
    try {
      final result = await _repo.getEligibility(forceRefresh: true);
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
      Get.snackbar(
        'Crédit débloqué !',
        application.status == 'debourse' || application.status == 'approuve'
            ? 'Votre crédit de $amount FCFA a été approuvé et déboursé sur votre Mobile Money.'
            : 'Votre demande de crédit a été soumise avec succès.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
      await loadEligibility();
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

  Future<void> repay() async {
    final active = eligibility.value?.activeCredit;
    if (active == null) {
      Get.snackbar(
        'Erreur',
        'Aucun crédit actif trouvé.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final amount = int.tryParse(repayCtrl.text.trim());
    if (amount == null || amount <= 0) {
      Get.snackbar(
        'Erreur',
        'Veuillez saisir un montant de remboursement valide.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    isRepaying.value = true;
    try {
      await _repo.repay(amount);
      repayCtrl.clear();
      Get.snackbar(
        'Remboursement validé',
        'Votre versement de $amount FCFA a été enregistré avec succès.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
      await loadEligibility();
    } on DioException catch (e) {
      Get.snackbar(
        'Erreur',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isRepaying.value = false;
    }
  }

  String _extractMessage(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    return 'Une erreur est survenue lors de l\'opération de crédit.';
  }
}

class MicroCreditScreen extends GetView<MicroCreditController> {
  const MicroCreditScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Micro-crédit d\'urgence')),
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

        final activeCredit = eligibility.activeCredit;

        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (activeCredit != null) ...[
                // Carte du crédit en cours
                Card(
                  elevation: 2,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Crédit en cours',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                activeCredit.status.toUpperCase(),
                                style: const TextStyle(
                                  color: AppColors.primary,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Montant emprunté :'),
                            Text(
                              '${activeCredit.amount} FCFA',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Déjà remboursé :'),
                            Text(
                              '${activeCredit.repaidAmount} FCFA',
                              style: const TextStyle(
                                color: AppColors.success,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Solde restant dû :'),
                            Text(
                              '${activeCredit.remainingAmount} FCFA',
                              style: const TextStyle(
                                color: AppColors.warning,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            value: activeCredit.amount > 0
                                ? (activeCredit.repaidAmount / activeCredit.amount).clamp(0.0, 1.0)
                                : 0.0,
                            minHeight: 8,
                            backgroundColor: Colors.grey.shade200,
                            valueColor: const AlwaysStoppedAnimation<Color>(AppColors.success),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Card(
                  color: AppColors.infoLight,
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Icon(Icons.autorenew, color: AppColors.info),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Amortissement automatique : 20% sont automatiquement prélevés sur chaque jalon libéré de vos chantiers jusqu\'au remboursement intégral.',
                            style: TextStyle(fontSize: 13),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Rembourser par anticipation',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: controller.repayCtrl,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    hintText: 'Ex: ${activeCredit.remainingAmount}',
                    suffixText: 'FCFA',
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: controller.isRepaying.value
                        ? null
                        : controller.repay,
                    icon: controller.isRepaying.value
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                          )
                        : const Icon(Icons.payment),
                    label: const Text('Effectuer un remboursement'),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      backgroundColor: AppColors.success,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ),
              ] else ...[
                // Statut d'éligibilité standard
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
                          'Plafond accordable : ${eligibility.maxAmount} FCFA',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        if ((eligibility.reason ?? '').isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text(
                            eligibility.reason!,
                            style: const TextStyle(color: AppColors.textSecondary),
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
                            'En tant qu\'artisan certifié ProsArtisan (score ≥ 700), votre crédit d\'urgence est débloqué sur votre Mobile Money sous 2 heures après approbation.',
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
                    onPressed: controller.isSubmitting.value || !eligibility.eligible
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
            ],
          ),
        );
      }),
    );
  }
}
