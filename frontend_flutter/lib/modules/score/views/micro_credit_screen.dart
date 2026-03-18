import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';

class MicroCreditController extends GetxController {
  final amountCtrl = TextEditingController();
  final isLoading = false.obs;
  final maxAmount = 150000.obs; // Mocked max amount

  Future<void> apply() async {
    final amount = int.tryParse(amountCtrl.text);
    if (amount == null || amount <= 0) {
      Get.snackbar('Erreur', 'Veuillez saisir un montant valide');
      return;
    }
    
    if (amount > maxAmount.value) {
      Get.snackbar('Erreur', 'Le montant dépasse votre plafond autorisé');
      return;
    }

    isLoading.value = true;
    try {
      // Logic to call API
      await Future.delayed(const Duration(seconds: 2));
      Get.back();
      Get.snackbar(
        'Succès',
        'Votre demande de crédit a été soumise. Déblocage sous 2h.',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } finally {
      isLoading.value = false;
    }
  }
}

class MicroCreditScreen extends GetView<MicroCreditController> {
  const MicroCreditScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Demande de Micro-crédit')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Montant souhaité',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller.amountCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                hintText: 'Ex: 50000',
                suffixText: 'FCFA',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            Obx(() => Text(
              'Votre plafond actuel : ${controller.maxAmount.value} FCFA',
              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
            )),
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
            Obx(() => SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: controller.isLoading.value ? null : () => controller.apply(),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                ),
                child: controller.isLoading.value
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Soumettre la demande'),
              ),
            )),
          ],
        ),
      ),
    );
  }
}
