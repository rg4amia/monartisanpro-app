import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/missions_controller.dart';

class MissionRequestScreen extends StatefulWidget {
  const MissionRequestScreen({super.key});

  @override
  State<MissionRequestScreen> createState() => _MissionRequestScreenState();
}

class _MissionRequestScreenState extends State<MissionRequestScreen> {
  final _descCtrl = TextEditingController();
  final _selectedCategory = ''.obs;
  final _urgency = 'moyen'.obs;
  final _isDescValid = false.obs;

  static const _categories = [
    'Plomberie',
    'Électricité',
    'Maçonnerie',
    'Menuiserie',
    'Peinture',
    'Carrelage',
    'Autre',
  ];

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>?;
    if (args != null && args['category'] != null) {
      _selectedCategory.value = args['category'] as String;
    }

    // Listen to text changes
    _descCtrl.addListener(() {
      _isDescValid.value = _descCtrl.text.length > 10;
    });
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MissionsController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Nouvelle demande')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Category
            const Text('Catégorie de travaux',
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary)),
            const SizedBox(height: 10),
            Obx(() => Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: _categories.map((cat) {
                    final selected = _selectedCategory.value == cat;
                    return GestureDetector(
                      onTap: () => _selectedCategory.value = cat,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: selected ? AppColors.primary : AppColors.card,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color:
                                selected ? AppColors.primary : AppColors.border,
                          ),
                        ),
                        child: Text(cat,
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                color: selected
                                    ? Colors.white
                                    : AppColors.textSecondary)),
                      ),
                    );
                  }).toList(),
                )),
            const SizedBox(height: 20),

            // Description
            const Text('Décrivez vos travaux',
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: _descCtrl,
              maxLines: 5,
              maxLength: 500,
              decoration: const InputDecoration(
                hintText:
                    'Décrivez en détail les travaux à réaliser (nature, superficie, matériaux souhaités...)',
              ),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 20),

            // Urgency
            const Text('Urgence',
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary)),
            const SizedBox(height: 10),
            Obx(() => Row(
                  children: [
                    _UrgencyChip(
                        label: 'Faible',
                        value: 'faible',
                        color: AppColors.success,
                        selected: _urgency.value == 'faible',
                        onTap: () => _urgency.value = 'faible'),
                    const SizedBox(width: 10),
                    _UrgencyChip(
                        label: 'Moyen',
                        value: 'moyen',
                        color: AppColors.warning,
                        selected: _urgency.value == 'moyen',
                        onTap: () => _urgency.value = 'moyen'),
                    const SizedBox(width: 10),
                    _UrgencyChip(
                        label: 'Urgent',
                        value: 'urgent',
                        color: AppColors.danger,
                        selected: _urgency.value == 'urgent',
                        onTap: () => _urgency.value = 'urgent'),
                  ],
                )),
            const SizedBox(height: 24),

            // Estimate button
            Obx(() => OutlinedButton.icon(
                  onPressed: _descCtrl.text.length > 20 &&
                          _selectedCategory.value.isNotEmpty &&
                          !c.isEstimating.value
                      ? () =>
                          c.estimate(_descCtrl.text, _selectedCategory.value)
                      : null,
                  icon: c.isEstimating.value
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.auto_awesome),
                  label: Text(c.isEstimating.value
                      ? 'Génération en cours...'
                      : 'Estimation IA (Gemini)'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(double.infinity, 48),
                  ),
                )),

            // Estimate result
            Obx(() {
              final est = c.estimateResult.value;
              if (est == null) return const SizedBox.shrink();
              return Container(
                margin: const EdgeInsets.only(top: 14),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.05),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                      color: AppColors.primary.withValues(alpha: 0.2)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.auto_awesome,
                            color: AppColors.primary, size: 16),
                        SizedBox(width: 6),
                        Text('Estimation Gemini AI',
                            style: TextStyle(
                                fontWeight: FontWeight.w700,
                                color: AppColors.primary)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    if (est['estimation_min'] != null)
                      Text(
                        '${Formatters.fcfa(est['estimation_min'] as int)} — ${Formatters.fcfa(est['estimation_max'] as int)}',
                        style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary),
                      ),
                    if (est['urgency'] != null)
                      Text('Urgence détectée : ${est['urgency']}',
                          style: const TextStyle(
                              fontSize: 13, color: AppColors.textSecondary)),
                  ],
                ),
              );
            }),
            const SizedBox(height: 24),

            // Submit
            Obx(() => ElevatedButton(
                  onPressed: _isDescValid.value &&
                          _selectedCategory.value.isNotEmpty &&
                          !c.isLoading.value &&
                          !c.isEstimating.value
                      ? () async {
                          // Navigate to artisan selection after creating
                          Get.toNamed(Routes.artisanProfile);
                        }
                      : null,
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size(double.infinity, 48),
                  ),
                  child: const Text('Trouver un artisan'),
                )),
          ],
        ),
      ),
    );
  }
}

class _UrgencyChip extends StatelessWidget {
  final String label, value;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _UrgencyChip({
    required this.label,
    required this.value,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: selected ? color : color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: color.withValues(alpha: 0.3)),
          ),
          child: Center(
            child: Text(label,
                style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: selected ? Colors.white : color)),
          ),
        ),
      ),
    );
  }
}
