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
      body: Obx(
        () => SingleChildScrollView(
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
                'Votre avis aide d\'autres clients et contribue au Score N\'Zassa de l\'artisan.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary, fontSize: 14),
              ),
              const SizedBox(height: 32),
              _StarPicker(
                title: 'Note globale',
                note: controller.selectedNote.value,
                onChanged: controller.setNote,
              ),
              const SizedBox(height: 24),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      _StarPicker(
                        title: 'Fiabilité',
                        note: controller.fiabilite.value,
                        onChanged: (note) =>
                            controller.setCriterion('fiabilite', note),
                      ),
                      const SizedBox(height: 16),
                      _StarPicker(
                        title: 'Intégrité',
                        note: controller.integrite.value,
                        onChanged: (note) =>
                            controller.setCriterion('integrite', note),
                      ),
                      const SizedBox(height: 16),
                      _StarPicker(
                        title: 'Qualité',
                        note: controller.qualite.value,
                        onChanged: (note) =>
                            controller.setCriterion('qualite', note),
                      ),
                      const SizedBox(height: 16),
                      _StarPicker(
                        title: 'Réactivité',
                        note: controller.reactivite.value,
                        onChanged: (note) =>
                            controller.setCriterion('reactivite', note),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              TextField(
                maxLines: 4,
                onChanged: (v) => controller.commentaire.value = v,
                decoration: const InputDecoration(
                  hintText: 'Laissez un commentaire (facultatif)...',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed:
                      controller.isLoading.value ? null : controller.submit,
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size.fromHeight(54),
                  ),
                  child: controller.isLoading.value
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text(
                          'Envoyer l\'évaluation',
                          style: TextStyle(fontSize: 16),
                        ),
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

class _StarPicker extends StatelessWidget {
  final String title;
  final int note;
  final ValueChanged<int> onChanged;

  const _StarPicker({
    required this.title,
    required this.note,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(5, (i) {
            final star = i + 1;
            return GestureDetector(
              onTap: () => onChanged(star),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 6),
                child: Icon(
                  note >= star
                      ? Icons.star_rounded
                      : Icons.star_outline_rounded,
                  size: 34,
                  color:
                      note >= star ? const Color(0xFFF39C12) : AppColors.border,
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 4),
        Center(
          child: Text(
            _label(note),
            style: TextStyle(
              color: note > 0 ? const Color(0xFFF39C12) : AppColors.textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }

  String _label(int value) {
    const labels = [
      'Non noté',
      'Très faible',
      'Faible',
      'Correct',
      'Bon',
      'Excellent',
    ];
    return labels[value.clamp(0, 5)];
  }
}
