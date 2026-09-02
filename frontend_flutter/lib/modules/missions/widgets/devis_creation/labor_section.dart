import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/devis_controller.dart';
import 'creation_empty_state.dart';
import 'ligne_card.dart';

/// Section main d'œuvre : liste des lignes de chiffrage et dialogue
/// d'ajout d'une ligne.
class LaborSection extends StatelessWidget {
  const LaborSection({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Main d\'œuvre',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            TextButton.icon(
              onPressed: () => _showAddLigneDialog(context),
              icon: const Icon(Icons.add_circle_outline, size: 18),
              label: const Text('Ajouter'),
              style: TextButton.styleFrom(foregroundColor: AppColors.primary),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() {
          final laborLines = controller.laborLines;

          if (laborLines.isEmpty) {
            return const CreationEmptyState(
              icon: Icons.build_circle_outlined,
              message: 'Aucune ligne de main d\'œuvre',
              hint:
                  'Ajoutez votre chiffrage de main d\'œuvre pour compléter le devis.',
            );
          }

          return Column(
            children: laborLines
                .map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: LigneCard(
                      ligne: entry,
                      onDelete: () => controller.removeLigneItem(entry),
                    ),
                  ),
                )
                .toList(),
          );
        }),
      ],
    );
  }

  void _showAddLigneDialog(BuildContext context) {
    final descController = TextEditingController();
    final montantController = TextEditingController();

    Get.dialog(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Ajouter une ligne de main d\'œuvre',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Description',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: descController,
                maxLines: 2,
                decoration: _fieldDecoration(
                  'Ex: Pose, raccordement et finitions',
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Montant (FCFA)',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: montantController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: _fieldDecoration('0'),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.back(),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        final desc = descController.text.trim();
                        final montant =
                            int.tryParse(montantController.text.trim());

                        if (desc.isEmpty) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez renseigner une description',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        if (montant == null || montant <= 0) {
                          Get.snackbar(
                            'Erreur',
                            'Montant invalide',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        controller.addLigne(
                          type: 'mo',
                          description: desc,
                          montant: montant,
                        );

                        Get.back();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Ajouter'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _fieldDecoration(String hint) {
    return InputDecoration(
      hintText: hint,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.primary, width: 2),
      ),
    );
  }
}
