import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/devis_controller.dart';

/// Bouton flottant d'envoi du devis (désactivé pendant la soumission ou
/// sans mission cible).
class SubmitButton extends StatelessWidget {
  const SubmitButton({
    required this.controller,
    required this.missionId,
    super.key,
  });

  final DevisController controller;
  final int? missionId;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Obx(
        () => ElevatedButton(
          onPressed: controller.isSubmitting.value || missionId == null
              ? null
              : () async {
                  final success = await controller.createDevis(
                    missionId: missionId!,
                  );
                  if (success) {
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      if (Navigator.of(context).canPop()) {
                        Navigator.of(context).pop(true);
                        return;
                      }
                      Get.back(result: true);
                    });
                  }
                },
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          child: controller.isSubmitting.value
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text(
                  'Envoyer le devis',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
        ),
      ),
    );
  }
}
