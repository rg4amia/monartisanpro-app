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

void showRecoverAccountDialog(AuthController c) {
  final oldPhoneCtrl = TextEditingController(text: '+225');
  final newPhoneCtrl = TextEditingController(text: '+225');
  final nameCtrl = TextEditingController();
  final otpCtrl = TextEditingController();

  // Reset controller states
  c.resetOldPhone.value = '+225';
  c.resetNewPhone.value = '+225';
  c.resetName.value = '';
  c.resetRole.value = null;
  c.resetOtp.value = '';
  c.isResetOtpSent.value = false;
  c.errorMsg.value = null;

  Get.dialog(
    Obx(
      () => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Récupération de compte',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Rattachez votre ancien compte à votre nouveau numéro de téléphone.',
                style: TextStyle(color: LoginTokens.muted, fontSize: 13),
              ),
              const SizedBox(height: 16),

              // Role Selector
              DropdownButtonFormField<String>(
                initialValue: c.resetRole.value,
                decoration: const InputDecoration(
                  labelText: 'Votre espace / rôle',
                  border: OutlineInputBorder(),
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                items: const [
                  DropdownMenuItem(value: 'client', child: Text('Client')),
                  DropdownMenuItem(
                    value: 'artisan',
                    child: Text('Artisan'),
                  ),
                  DropdownMenuItem(
                    value: 'fournisseur',
                    child: Text('Fournisseur'),
                  ),
                  DropdownMenuItem(value: 'driver', child: Text('Livreur')),
                ],
                onChanged: c.isResetOtpSent.value
                    ? null
                    : (val) {
                        c.resetRole.value = val;
                      },
              ),
              const SizedBox(height: 12),

              // Name
              TextField(
                controller: nameCtrl,
                enabled: !c.isResetOtpSent.value,
                decoration: const InputDecoration(
                  labelText: 'Nom complet exact',
                  border: OutlineInputBorder(),
                  hintText: 'Ex: Jean Dupont',
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                onChanged: (val) => c.resetName.value = val,
              ),
              const SizedBox(height: 12),

              // Old Phone
              TextField(
                controller: oldPhoneCtrl,
                enabled: !c.isResetOtpSent.value,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Ancien numéro (+225)',
                  border: OutlineInputBorder(),
                  hintText: '+2250707000000',
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                onChanged: (val) => c.resetOldPhone.value = val,
              ),
              const SizedBox(height: 12),

              // New Phone
              TextField(
                controller: newPhoneCtrl,
                enabled: !c.isResetOtpSent.value,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Nouveau numéro (+225)',
                  border: OutlineInputBorder(),
                  hintText: '+2250707000000',
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                onChanged: (val) => c.resetNewPhone.value = val,
              ),

              if (c.isResetOtpSent.value) ...[
                const SizedBox(height: 16),
                const Divider(),
                const SizedBox(height: 8),
                const Text(
                  'Entrez le code OTP reçu sur votre nouveau numéro :',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: otpCtrl,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 8,
                  ),
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    counterText: '',
                    hintText: '0000',
                    contentPadding: EdgeInsets.symmetric(vertical: 8),
                  ),
                  onChanged: (val) => c.resetOtp.value = val,
                ),
              ],

              if (c.errorMsg.value != null) ...[
                const SizedBox(height: 12),
                Text(
                  c.errorMsg.value!,
                  style: const TextStyle(
                    color: Colors.red,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          // À l'étape OTP, les champs d'identité sont désactivés : sans ce
          // retour, une saisie erronée obligeait à annuler tout le
          // formulaire et à tout ressaisir.
          if (c.isResetOtpSent.value)
            TextButton(
              onPressed: c.isResetting.value
                  ? null
                  : () {
                      c.isResetOtpSent.value = false;
                      c.errorMsg.value = null;
                      c.resetOtp.value = '';
                      otpCtrl.clear();
                    },
              child: const Text('Précédent'),
            ),
          ElevatedButton(
            onPressed: c.isResetting.value
                ? null
                : () async {
                    if (!c.isResetOtpSent.value) {
                      // Envoyer OTP
                      await c.requestResetPhone();
                      if (c.errorMsg.value != null) {
                        Get.snackbar(
                          'Erreur',
                          c.errorMsg.value!,
                          backgroundColor: Colors.red,
                          colorText: Colors.white,
                        );
                      } else {
                        Get.snackbar(
                          'OTP envoyé',
                          'Un code de validation a été envoyé sur votre nouveau numéro.',
                          backgroundColor: LoginTokens.success,
                          colorText: Colors.white,
                        );
                      }
                    } else {
                      // Confirmer la récupération
                      final success = await c.confirmResetPhone();
                      if (success) {
                        Get.back();
                        Get.snackbar(
                          'Compte récupéré',
                          'Votre compte a été associé à votre nouveau numéro avec succès.',
                          backgroundColor: LoginTokens.success,
                          colorText: Colors.white,
                        );
                        unawaited(Get.offAllNamed(Routes.mainTab));
                      } else {
                        Get.snackbar(
                          'Code OTP erroné',
                          c.errorMsg.value ?? 'Le code saisi est invalide.',
                          backgroundColor: Colors.red,
                          colorText: Colors.white,
                        );
                      }
                    }
                  },
            child: c.isResetting.value
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Text(c.isResetOtpSent.value ? 'Confirmer' : 'Suivant'),
          ),
        ],
      ),
    ),
  );
}
