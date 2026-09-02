import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/devis_controller.dart';
import 'creation_empty_state.dart';
import 'jalon_card.dart';

/// Section jalons de paiement : liste des jalons du brouillon et dialogue
/// d'ajout (description + montant + date cible).
class JalonsSection extends StatelessWidget {
  const JalonsSection({required this.controller, super.key});

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
              'Jalons de paiement',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            TextButton.icon(
              onPressed: () => _showAddJalonDialog(context),
              icon: const Icon(Icons.add_circle_outline, size: 18),
              label: const Text('Ajouter'),
              style: TextButton.styleFrom(foregroundColor: AppColors.primary),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() {
          if (controller.jalons.isEmpty) {
            return const CreationEmptyState(
              icon: Icons.flag_outlined,
              message: 'Aucun jalon défini',
              hint: 'Définissez les étapes de validation du projet',
            );
          }

          return Column(
            children: controller.jalons
                .asMap()
                .entries
                .map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: JalonCard(
                      jalon: entry.value,
                      onDelete: () => controller.removeJalon(entry.key),
                    ),
                  ),
                )
                .toList(),
          );
        }),
      ],
    );
  }

  void _showAddJalonDialog(BuildContext context) {
    final descController = TextEditingController();
    final montantController = TextEditingController();
    final dateController = TextEditingController();
    DateTime? selectedDate;

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
                'Ajouter un jalon',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 20),
              const _FieldLabel('Description'),
              const SizedBox(height: 8),
              TextField(
                controller: descController,
                maxLines: 2,
                decoration: _fieldDecoration('Ex: Livraison des fondations'),
              ),
              const SizedBox(height: 16),
              const _FieldLabel('Montant (FCFA)'),
              const SizedBox(height: 8),
              TextField(
                controller: montantController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: _fieldDecoration('0'),
              ),
              const SizedBox(height: 16),
              const _FieldLabel('Date cible'),
              const SizedBox(height: 8),
              StatefulBuilder(
                builder: (context, setState) => TextField(
                  controller: dateController,
                  readOnly: true,
                  onTap: () async {
                    final date = await showDatePicker(
                      context: context,
                      initialDate: DateTime.now().add(const Duration(days: 7)),
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (date != null) {
                      selectedDate = date;
                      dateController.text =
                          DateFormat('dd/MM/yyyy').format(date);
                      setState(() {});
                    }
                  },
                  decoration: _fieldDecoration('Sélectionner une date').copyWith(
                    suffixIcon: const Icon(Icons.calendar_today, size: 20),
                  ),
                ),
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
                        final montantStr = montantController.text.trim();

                        if (desc.isEmpty ||
                            montantStr.isEmpty ||
                            selectedDate == null) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez remplir tous les champs',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        final montant = int.tryParse(montantStr);
                        if (montant == null || montant <= 0) {
                          Get.snackbar(
                            'Erreur',
                            'Montant invalide',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        controller.addJalon(
                          description: desc,
                          montant: montant,
                          dateCible: selectedDate!.toIso8601String(),
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

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: AppColors.textPrimary,
      ),
    );
  }
}
