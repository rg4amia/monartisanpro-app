import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/settings_controller.dart';
import 'settings_colors.dart';

void confirmLogout(BuildContext context, SettingsController controller) {
  Get.dialog(
    AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text('Déconnexion'),
      content: const Text('Êtes-vous sûr de vouloir vous déconnecter ?'),
      actions: [
        TextButton(
          onPressed: () => Get.back(),
          child: const Text('Annuler'),
        ),
        ElevatedButton(
          onPressed: () {
            Get.back();
            controller.logout();
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: SettingsColors.danger,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          child: const Text('Déconnexion'),
        ),
      ],
    ),
  );
}

void confirmDeleteAccount(
  BuildContext context,
  SettingsController controller,
) {
  Get.dialog(
    AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text(
        'Suppression de compte',
        style: TextStyle(fontWeight: FontWeight.bold),
      ),
      content: const Text(
        'Attention : Cette action est irréversible et supprimera définitivement votre compte, vos coordonnées et votre historique sur la plateforme ProsArtisan. Souhaitez-vous continuer ?',
      ),
      actions: [
        TextButton(
          onPressed: () => Get.back(),
          child: const Text('Annuler'),
        ),
        ElevatedButton(
          onPressed: () {
            Get.back();
            controller.deleteAccount();
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: SettingsColors.danger,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
          child: const Text('Supprimer définitivement'),
        ),
      ],
    ),
  );
}

void showPaymentPhoneDialog(
  BuildContext context,
  SettingsController controller,
) {
  final selectedProvider = (controller.preferredPaymentProvider.value.isNotEmpty
          ? controller.preferredPaymentProvider.value
          : 'wave')
      .obs;
  final phoneCtrl = TextEditingController(text: controller.paymentPhone.value);
  final isClient = controller.userRole.value == 'client';
  controller.paymentPhoneError.value = null;

  Get.dialog(
    Obx(
      () => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFECFDF5),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                isClient
                    ? Icons.payments_rounded
                    : Icons.account_balance_wallet_rounded,
                color: const Color(0xFF10B981),
                size: 22,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                isClient ? 'Moyen de paiement' : 'Reversement Mobile Money',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
            ),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                isClient
                    ? 'Associez votre compte Mobile Money pour régler vos devis rapidement et recevoir automatiquement vos remboursements en cas de litige.'
                    : 'Renseignez le numéro Mobile Money sur lequel vos gains et fonds débloqués seront virés.',
                style: const TextStyle(
                  color: SettingsColors.muted,
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Opérateur Mobile Money',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: SettingsColors.ink,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _buildDialogProviderChip(
                    'wave',
                    'Wave',
                    const Color(0xFF00A3FF),
                    selectedProvider,
                  ),
                  const SizedBox(width: 6),
                  _buildDialogProviderChip(
                    'orange_money',
                    'Orange',
                    const Color(0xFFFF7900),
                    selectedProvider,
                  ),
                  const SizedBox(width: 6),
                  _buildDialogProviderChip(
                    'mtn_money',
                    'MTN',
                    const Color(0xFFFFCC00),
                    selectedProvider,
                  ),
                  const SizedBox(width: 6),
                  _buildDialogProviderChip(
                    'moov_money',
                    'Moov',
                    const Color(0xFF005BA6),
                    selectedProvider,
                  ),
                ],
              ),
              const SizedBox(height: 18),
              TextField(
                controller: phoneCtrl,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: 'Numéro Mobile Money (10 chiffres)',
                  hintText: 'Ex: 0701020304',
                  prefixIcon: const Icon(Icons.phone_android_rounded, size: 20),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              if (controller.paymentPhoneError.value != null) ...[
                const SizedBox(height: 10),
                Text(
                  controller.paymentPhoneError.value!,
                  style: const TextStyle(
                    color: SettingsColors.danger,
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
          ElevatedButton(
            onPressed: controller.isSavingPaymentPhone.value
                ? null
                : () async {
                    final phone = phoneCtrl.text.trim();
                    if (phone.isEmpty || phone.length < 10) {
                      Get.snackbar(
                        'Numéro invalide',
                        'Veuillez entrer un numéro à 10 chiffres (ex: 0701020304)',
                        backgroundColor: SettingsColors.danger,
                        colorText: Colors.white,
                      );
                      return;
                    }

                    final success = await controller.updatePaymentPhone(
                      newPaymentPhone: phone,
                      provider: selectedProvider.value,
                    );

                    if (success) {
                      Get.back();
                      Get.snackbar(
                        'Compte associé',
                        'Votre numéro Mobile Money a été mis à jour avec succès.',
                        backgroundColor: SettingsColors.success,
                        colorText: Colors.white,
                      );
                    } else {
                      Get.snackbar(
                        'Erreur',
                        controller.paymentPhoneError.value ??
                            'Impossible d\'enregistrer le numéro.',
                        backgroundColor: SettingsColors.danger,
                        colorText: Colors.white,
                      );
                    }
                  },
            style: ElevatedButton.styleFrom(
              backgroundColor: SettingsColors.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            child: controller.isSavingPaymentPhone.value
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Enregistrer'),
          ),
        ],
      ),
    ),
  );
}

Widget _buildDialogProviderChip(
  String providerKey,
  String label,
  Color color,
  RxString selectedProvider,
) {
  return Obx(() {
    final isSelected = selectedProvider.value == providerKey;
    return Expanded(
      child: GestureDetector(
        onTap: () => selectedProvider.value = providerKey,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? color.withValues(alpha: 0.12)
                : const Color(0xFFF3F4F6),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? color : Colors.transparent,
              width: 1.5,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 7,
                height: 7,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isSelected ? color : Colors.grey.shade400,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  color: isSelected ? color : SettingsColors.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  });
}

void showChangePhoneDialog(
  BuildContext context,
  SettingsController controller,
) {
  final phoneCtrl = TextEditingController(text: controller.userPhone.value);
  final otpCtrl = TextEditingController();

  controller.isChangePhoneOtpSent.value = false;
  controller.changePhoneError.value = null;

  Get.dialog(
    Obx(
      () => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Modifier le numéro',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Entrez votre nouveau numéro de téléphone (+225). Un code de validation OTP vous sera envoyé.',
                style: TextStyle(color: SettingsColors.muted, fontSize: 13),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: phoneCtrl,
                enabled: !controller.isChangePhoneOtpSent.value,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Nouveau numéro (+225)',
                  border: OutlineInputBorder(),
                  hintText: '+2250707000000',
                ),
              ),
              if (controller.isChangePhoneOtpSent.value) ...[
                const SizedBox(height: 16),
                const Divider(),
                const SizedBox(height: 8),
                const Text(
                  'Saisissez le code OTP reçu :',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: otpCtrl,
                  keyboardType: TextInputType.number,
                  maxLength: 4,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 8,
                  ),
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    counterText: '',
                    hintText: '0000',
                  ),
                ),
              ],
              if (controller.changePhoneError.value != null) ...[
                const SizedBox(height: 12),
                Text(
                  controller.changePhoneError.value!,
                  style: const TextStyle(
                    color: SettingsColors.danger,
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
          // À l'étape OTP, le champ numéro est désactivé : sans ce retour,
          // un numéro mal saisi obligeait à annuler tout le formulaire.
          if (controller.isChangePhoneOtpSent.value)
            TextButton(
              onPressed: controller.isChangingPhone.value
                  ? null
                  : () {
                      controller.isChangePhoneOtpSent.value = false;
                      controller.changePhoneError.value = null;
                      otpCtrl.clear();
                    },
              child: const Text('Précédent'),
            ),
          ElevatedButton(
            onPressed: controller.isChangingPhone.value
                ? null
                : () async {
                    final newPhone = phoneCtrl.text.trim();
                    if (newPhone.isEmpty ||
                        !newPhone.startsWith('+225') ||
                        newPhone.length < 14) {
                      Get.snackbar(
                        'Numéro invalide',
                        'Veuillez entrer un numéro valide au format +225XXXXXXXXXX',
                        backgroundColor: SettingsColors.danger,
                        colorText: Colors.white,
                      );
                      return;
                    }

                    if (!controller.isChangePhoneOtpSent.value) {
                      final success =
                          await controller.requestChangePhone(newPhone);
                      if (success) {
                        Get.snackbar(
                          'OTP envoyé',
                          'Un code OTP a été envoyé sur le nouveau numéro.',
                          backgroundColor: SettingsColors.success,
                          colorText: Colors.white,
                        );
                      } else {
                        Get.snackbar(
                          'Erreur',
                          controller.changePhoneError.value ??
                              'Impossible d\'envoyer le code.',
                          backgroundColor: SettingsColors.danger,
                          colorText: Colors.white,
                        );
                      }
                    } else {
                      final otp = otpCtrl.text.trim();
                      if (otp.length != 4) {
                        Get.snackbar(
                          'OTP requis',
                          'Veuillez saisir le code OTP à 4 chiffres.',
                          backgroundColor: SettingsColors.danger,
                          colorText: Colors.white,
                        );
                        return;
                      }

                      final success = await controller.confirmChangePhone(
                        newPhone,
                        otp,
                      );
                      if (success) {
                        Get.back();
                        Get.snackbar(
                          'Numéro modifié',
                          'Votre numéro de téléphone de connexion a été mis à jour.',
                          backgroundColor: SettingsColors.success,
                          colorText: Colors.white,
                        );
                      } else {
                        Get.snackbar(
                          'Code OTP erroné',
                          controller.changePhoneError.value ??
                              'Le code saisi est invalide.',
                          backgroundColor: SettingsColors.danger,
                          colorText: Colors.white,
                        );
                      }
                    }
                  },
            child: controller.isChangingPhone.value
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Text(
                    controller.isChangePhoneOtpSent.value
                        ? 'Confirmer'
                        : 'Suivant',
                  ),
          ),
        ],
      ),
    ),
  );
}
