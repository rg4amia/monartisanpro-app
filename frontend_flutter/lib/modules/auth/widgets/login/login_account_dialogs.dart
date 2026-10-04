import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../controllers/auth_controller.dart';
import 'login_tokens.dart';

void showLoginResetOptionsDialog(AuthController c) {
  Get.dialog(
    AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text(
        'Réinitialisation de connexion',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      content: const Text(
        'Choisissez l\'action de réinitialisation appropriée pour votre situation :',
        style: TextStyle(color: LoginTokens.muted),
      ),
      actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      actions: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            OutlinedButton.icon(
              onPressed: () async {
                Get.back();
                await c.resetLocalSession();
                Get.snackbar(
                  'Session réinitialisée',
                  'Le cache local a été vidé. Vous pouvez à présent vous reconnecter.',
                  backgroundColor: LoginTokens.success,
                  colorText: Colors.white,
                  snackPosition: SnackPosition.TOP,
                );
              },
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Réinitialiser l\'application (local)'),
              style: OutlinedButton.styleFrom(
                foregroundColor: LoginTokens.primary,
                side: const BorderSide(color: LoginTokens.primary),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: () {
                Get.back();
                showRecoverAccountDialog(c);
              },
              icon: const Icon(Icons.swap_calls_rounded),
              label: const Text('Changement de numéro de téléphone'),
              style: ElevatedButton.styleFrom(
                backgroundColor: LoginTokens.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Get.back(),
              child: const Text('Fermer'),
            ),
          ],
        ),
      ],
    ),
  );
}

/// Récupération d'un compte dont la carte SIM est perdue.
///
/// Le changement de numéro ne se fait plus dans l'application : l'ancien
/// numéro, le nom et le rôle suffisaient à prendre le compte d'un autre. Le
/// support vérifie désormais l'identité du titulaire avant de changer le numéro.
void showRecoverAccountDialog(AuthController c) {
  Get.dialog(const RecoverAccountDialog());
}

class RecoverAccountDialog extends StatelessWidget {
  const RecoverAccountDialog({super.key});

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text(
        'Récupération de compte',
        style: TextStyle(fontWeight: FontWeight.w800),
      ),
      content: const Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Vous avez perdu votre carte SIM ou changé de numéro ?',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
          ),
          SizedBox(height: 10),
          Text(
            'Pour protéger votre compte et vos gains, le changement de numéro '
            'se fait auprès du support ProsArtisan, qui vérifie votre identité.',
            style: TextStyle(color: LoginTokens.muted, fontSize: 13, height: 1.4),
          ),
          SizedBox(height: 10),
          Text(
            'Préparez votre pièce d\'identité et votre ancien numéro.',
            style: TextStyle(color: LoginTokens.muted, fontSize: 13, height: 1.4),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Get.back(),
          child: const Text('Fermer'),
        ),
        ElevatedButton(
          onPressed: () {
            Get.back();
            unawaited(Get.toNamed(Routes.support));
          },
          child: const Text('Contacter le support'),
        ),
      ],
    );
  }
}
