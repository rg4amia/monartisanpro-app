import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/rating_controller.dart';

class RatingScreen extends GetView<RatingController> {
  const RatingScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final roleColor = controller.roleColor;
      final roleIcon = controller.roleIcon;
      final screenTitle = controller.screenTitle;
      final targetName = controller.targetName.value;
      final targetRole = controller.targetRole.value;
      final targetSubtitle = controller.targetSubtitle.value;

      return Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: Text(screenTitle),
          backgroundColor: AppColors.surface,
          surfaceTintColor: AppColors.surface,
          elevation: 0,
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // ── Top Actor Summary Card ──
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: roleColor.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: roleColor.withValues(alpha: 0.25),
                          width: 2,
                        ),
                      ),
                      child: Icon(roleIcon, size: 32, color: roleColor),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      targetName,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: roleColor.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        targetRole == 'artisan'
                            ? 'Artisan Professionnel'
                            : targetRole == 'livreur'
                                ? 'Livreur Express'
                                : 'Quincaillerie & Fournisseur',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: roleColor,
                        ),
                      ),
                    ),
                    if (targetSubtitle.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        targetSubtitle,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.textSecondary,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ── Global Star Rating ──
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                ),
                child: _StarPicker(
                  title: 'Note globale de la prestation',
                  subtitle: 'Attribuez une note générale de 1 à 5 étoiles',
                  note: controller.selectedNote.value,
                  onChanged: controller.setNote,
                  starSize: 40,
                ),
              ),

              const SizedBox(height: 20),

              // ── Detailed Criteria Card ──
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(
                          Icons.tune_rounded,
                          size: 18,
                          color: AppColors.primary,
                        ),
                        SizedBox(width: 8),
                        Text(
                          'Évaluation par critères (Score ProsArtisan)',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 24, color: AppColors.border),
                    _StarPicker(
                      title: controller.criterionFiabiliteLabel,
                      note: controller.fiabilite.value,
                      onChanged: (note) =>
                          controller.setCriterion('fiabilite', note),
                    ),
                    const SizedBox(height: 16),
                    _StarPicker(
                      title: controller.criterionIntegriteLabel,
                      note: controller.integrite.value,
                      onChanged: (note) =>
                          controller.setCriterion('integrite', note),
                    ),
                    const SizedBox(height: 16),
                    _StarPicker(
                      title: controller.criterionQualiteLabel,
                      note: controller.qualite.value,
                      onChanged: (note) =>
                          controller.setCriterion('qualite', note),
                    ),
                    const SizedBox(height: 16),
                    _StarPicker(
                      title: controller.criterionReactiviteLabel,
                      note: controller.reactivite.value,
                      onChanged: (note) =>
                          controller.setCriterion('reactivite', note),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // ── Comment Field ──
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Commentaire & Avis (facultatif)',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      maxLines: 4,
                      onChanged: (v) => controller.commentaire.value = v,
                      decoration: InputDecoration(
                        hintText:
                            'Partagez votre expérience sur cette prestation...',
                        hintStyle: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                        filled: true,
                        fillColor: const Color(0xFFF9FAFB),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(color: AppColors.border),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: const BorderSide(color: AppColors.border),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide(color: roleColor, width: 1.5),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 28),

              // ── Submit Button ──
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed:
                      controller.isLoading.value ? null : controller.submit,
                  icon: controller.isLoading.value
                      ? const SizedBox.shrink()
                      : const Icon(Icons.check_circle_rounded, size: 20),
                  label: controller.isLoading.value
                      ? const SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2.5,
                          ),
                        )
                      : const Text(
                          'Valider l\'évaluation',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: roleColor,
                    foregroundColor: Colors.white,
                    minimumSize: const Size.fromHeight(54),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    elevation: 2,
                  ),
                ),
              ),

              const SizedBox(height: 24),
            ],
          ),
        ),
      );
    });
  }
}

class _StarPicker extends StatelessWidget {
  final String title;
  final String? subtitle;
  final int note;
  final ValueChanged<int> onChanged;
  final double starSize;

  const _StarPicker({
    required this.title,
    this.subtitle,
    required this.note,
    required this.onChanged,
    this.starSize = 28,
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
            fontSize: 13,
            color: AppColors.textPrimary,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 2),
          Text(
            subtitle!,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
        ],
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(5, (i) {
            final star = i + 1;
            return GestureDetector(
              onTap: () => onChanged(star),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: Icon(
                  note >= star
                      ? Icons.star_rounded
                      : Icons.star_outline_rounded,
                  size: starSize,
                  color: note >= star
                      ? const Color(0xFFF59E0B)
                      : const Color(0xFFD1D5DB),
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
              fontSize: 12,
              color: note > 0 ? const Color(0xFFD97706) : AppColors.textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }

  String _label(int value) {
    const labels = [
      'Touchez les étoiles pour noter',
      '⭐ Insatisfaisant (1/5)',
      '⭐⭐ Passable (2/5)',
      '⭐⭐⭐ Satisfaisant (3/5)',
      '⭐⭐⭐⭐ Très bien (4/5)',
      '⭐⭐⭐⭐⭐ Excellent (5/5)',
    ];
    return labels[value.clamp(0, 5)];
  }
}
