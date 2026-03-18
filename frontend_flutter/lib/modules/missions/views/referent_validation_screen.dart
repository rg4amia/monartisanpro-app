import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/app_colors.dart';

class ReferentValidationController extends GetxController {
  final photos = <XFile>[].obs;
  final notesCtrl = TextEditingController();
  final isLoading = false.obs;
  final missionId = Get.arguments['missionId'] as int;

  Future<void> pickImage() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.camera);
    if (image != null) photos.add(image);
  }

  Future<void> submit() async {
    if (photos.length < 2) {
      Get.snackbar('Erreur', 'Veuillez prendre au moins 2 photos du chantier');
      return;
    }

    isLoading.value = true;
    try {
      // Logic to call /api/v1/missions/{mission}/referent-validate
      await Future.delayed(const Duration(seconds: 2));
      Get.back();
      Get.snackbar(
        'Succès',
        'Mission validée. Les paiements ont été libérés.',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } finally {
      isLoading.value = false;
    }
  }
}

class ReferentValidationScreen extends GetView<ReferentValidationController> {
  const ReferentValidationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Validation Référent')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Mission #${controller.missionId}',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
            const SizedBox(height: 8),
            const Text(
              'En tant que référent, vous devez certifier physiquement que les travaux correspondant aux jalons ont été effectués.',
              style: TextStyle(color: AppColors.textMuted, fontSize: 14),
            ),
            const SizedBox(height: 32),
            const Text(
              'Photos du chantier (min. 2)',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            Obx(() => Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                ...controller.photos.map((file) => Stack(
                  children: [
                    Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(8),
                        color: AppColors.shadowColor,
                      ),
                      child: const Icon(Icons.image, color: AppColors.textMuted),
                    ),
                    Positioned(
                      right: 0,
                      child: IconButton(
                        icon: const Icon(Icons.cancel, color: Colors.red),
                        onPressed: () => controller.photos.remove(file),
                      ),
                    ),
                  ],
                )),
                GestureDetector(
                  onTap: controller.pickImage,
                  child: Container(
                    width: 100,
                    height: 100,
                    decoration: BoxDecoration(
                      border: Border.all(color: AppColors.primary),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.add_a_photo, color: AppColors.primary),
                  ),
                ),
              ],
            )),
            const SizedBox(height: 32),
            const Text(
              'Notes d\'inspection',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller.notesCtrl,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Observations sur la qualité des travaux...',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 48),
            Obx(() => SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: controller.isLoading.value ? null : () => controller.submit(),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: controller.isLoading.value
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Confirmer la validation physique'),
              ),
            )),
          ],
        ),
      ),
    );
  }
}
