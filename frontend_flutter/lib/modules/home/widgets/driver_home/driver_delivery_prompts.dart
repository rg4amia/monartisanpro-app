import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/home_controller.dart';

void promptPickupCode(HomeController controller, MissionModel mission) {
  final textController = TextEditingController();
  Get.dialog(
    AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text(
        'Code d\'enlèvement magasin',
        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Entrez le code d\'enlèvement fourni par la quincaillerie.',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                hintText: 'Code de retrait fournisseur',
                helperText:
                    'Communiqué par la quincaillerie lors de l\'enlèvement.',
                border:
                    OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Get.back(), child: const Text('Annuler')),
        ElevatedButton(
          onPressed: () {
            Get.back();
            controller.handleDriverPickupFromStore(
              mission,
              textController.text,
            );
          },
          child: const Text('Confirmer'),
        ),
      ],
    ),
  );
}

void promptDropoffCode(HomeController controller, MissionModel mission) {
  final textController = TextEditingController();
  Get.dialog(
    AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text(
        'Code de réception client',
        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Saisissez le code de confirmation OTP envoyé au client.',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                hintText: 'Code de réception client (OTP)',
                helperText: 'Demandez le code OTP affiché sur l\'app du client.',
                border:
                    OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Get.back(), child: const Text('Annuler')),
        ElevatedButton(
          onPressed: () {
            Get.back();
            controller.handleDriverDropoffToClient(
              mission,
              textController.text,
            );
          },
          child: const Text('Valider'),
        ),
      ],
    ),
  );
}

void promptWaitingSurge(HomeController controller, MissionModel mission) {
  final minutesController = TextEditingController();
  bool isSubmitting = false;

  Get.dialog(
    StatefulBuilder(
      builder: (context, setDialogState) {
        Future<void> submit() async {
          final minutes = int.tryParse(minutesController.text.trim());
          if (minutes == null || minutes < 1) {
            Get.snackbar(
              'Valeur invalide',
              'Indiquez un nombre de minutes valide (minimum 1).',
              snackPosition: SnackPosition.BOTTOM,
              backgroundColor: AppColors.danger,
              colorText: Colors.white,
            );
            return;
          }

          setDialogState(() => isSubmitting = true);
          final success = await controller.handleDriverWaitingSurge(
            mission,
            minutes,
          );
          if (success) {
            Get.back();
          } else {
            setDialogState(() => isSubmitting = false);
          }
        }

        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: const Text(
            'Signaler un temps d\'attente',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Indiquez le temps d\'attente au retrait ou à la livraison. '
                  'Des frais supplémentaires seront ajoutés à la commande.',
                  style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [5, 10, 15, 30].map((minutes) {
                    final selected = minutesController.text == '$minutes';
                    return ChoiceChip(
                      label: Text('$minutes min'),
                      selected: selected,
                      onSelected: isSubmitting
                          ? null
                          : (_) => setDialogState(
                                () => minutesController.text = '$minutes',
                              ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: minutesController,
                  enabled: !isSubmitting,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    hintText: 'Minutes d\'attente',
                    helperText: 'Minimum 1 minute.',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onChanged: (_) => setDialogState(() {}),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: isSubmitting ? null : () => Get.back(),
              child: const Text('Annuler'),
            ),
            ElevatedButton(
              onPressed: isSubmitting ? null : submit,
              child: isSubmitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Confirmer'),
            ),
          ],
        );
      },
    ),
  );
}
