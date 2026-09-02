import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/jalon_model.dart';
import '../../controllers/missions_controller.dart';

/// Boîte de dialogue de saisie de l'OTP à 4 chiffres reçu par SMS pour
/// valider un jalon (règle d'or : aucun déblocage sans OTP validé).
void showOtpValidationDialog(BuildContext context, JalonModel jalon) {
  final MissionsController controller = Get.find<MissionsController>();
  final TextEditingController otpController = TextEditingController();

  Get.dialog(
    Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.sms_outlined,
              color: AppColors.success,
              size: 36,
            ),
            const SizedBox(height: 12),
            Text(
              'Valider le Jalon ${jalon.ordre}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Saisissez le code de validation OTP à 4 chiffres envoyé par SMS.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: otpController,
              keyboardType: TextInputType.number,
              maxLength: 4,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 20,
                letterSpacing: 8,
                fontWeight: FontWeight.bold,
              ),
              decoration: const InputDecoration(
                hintText: '0000',
                counterText: '',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextButton.icon(
              onPressed: () async {
                await controller.requestOtp(jalon.id);
              },
              icon: const Icon(
                Icons.refresh,
                size: 16,
                color: AppColors.primary,
              ),
              label: const Text(
                'Renvoyer le code par SMS',
                style: TextStyle(
                  color: AppColors.primary,
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Get.back(),
                    child: const Text('Annuler'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () async {
                      final otp = otpController.text.trim();
                      if (otp.length != 4) {
                        Get.snackbar(
                          'Code invalide',
                          'Le code doit contenir 4 chiffres',
                        );
                        return;
                      }
                      Get.back();
                      final success =
                          await controller.validateOtp(jalon.id, otp);
                      if (success) {
                        unawaited(
                          controller.loadMission(
                            jalon.missionId,
                            forceRefresh: true,
                          ),
                        );
                      }
                    },
                    child: const Text('Valider'),
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
