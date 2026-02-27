import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/rating_controller.dart';

class RatingScreen extends GetView<RatingController> {
  const RatingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Évaluer l\'artisan')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const SizedBox(height: 16),
            const Icon(Icons.handyman, size: 72, color: AppColors.primary),
            const SizedBox(height: 16),
            const Text(
              'Comment s\'est passée la mission ?',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppColors.primary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            const Text(
              'Votre avis aide d\'autres clients et contribue\nau Score N\'Zassa de l\'artisan.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
            ),
            const SizedBox(height: 32),

            // Étoiles
            Obx(() => Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(5, (i) {
                    final star = i + 1;
                    return GestureDetector(
                      onTap: () => controller.setNote(star),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 6),
                        child: Icon(
                          controller.selectedNote.value >= star
                              ? Icons.star_rounded
                              : Icons.star_outline_rounded,
                          size: 48,
                          color: controller.selectedNote.value >= star
                              ? const Color(0xFFF39C12)
                              : AppColors.border,
                        ),
                      ),
                    );
                  }),
                )),
            const SizedBox(height: 8),
            Obx(() => Text(
                  _noteLabel(controller.selectedNote.value),
                  style: TextStyle(
                    color: controller.selectedNote.value > 0
                        ? const Color(0xFFF39C12)
                        : AppColors.textMuted,
                    fontWeight: FontWeight.w600,
                    fontSize: 16,
                  ),
                )),
            const SizedBox(height: 32),

            // Commentaire
            TextField(
              maxLines: 4,
              onChanged: (v) => controller.commentaire.value = v,
              decoration: const InputDecoration(
                hintText: 'Laissez un commentaire (facultatif)...',
                alignLabelWithHint: true,
              ),
            ),
            const Spacer(),

            Obx(() => ElevatedButton(
                  onPressed: controller.isLoading.value
                      ? null
                      : controller.submit,
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size.fromHeight(54),
                  ),
                  child: controller.isLoading.value
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Envoyer l\'évaluation',
                          style: TextStyle(fontSize: 16)),
                )),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  String _noteLabel(int note) {
    const labels = [
      '',
      'Très insatisfait',
      'Insatisfait',
      'Correct',
      'Satisfait',
      'Excellent !',
    ];
    return note > 0 ? labels[note] : 'Sélectionnez une note';
  }
}
