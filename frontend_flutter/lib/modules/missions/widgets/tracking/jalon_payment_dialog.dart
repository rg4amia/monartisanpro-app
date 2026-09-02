import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/jalon_model.dart';
import '../../controllers/devis_controller.dart';
import '../../controllers/missions_controller.dart';
import 'otp_validation_dialog.dart';

/// Boîte de dialogue de financement d'un jalon (paiement hybride) : choix du
/// provider Mobile Money / virement, appel `payJalon`, puis enchaînement
/// automatique sur la validation OTP.
void showJalonPaymentDialog(BuildContext context, JalonModel jalon) {
  final DevisController devisController = Get.isRegistered<DevisController>()
      ? Get.find<DevisController>()
      : Get.put(DevisController());

  String selectedProvider = 'wave';
  Get.dialog(
    Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: StatefulBuilder(
          builder: (dialogContext, setState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.payment, color: AppColors.primary, size: 36),
              const SizedBox(height: 12),
              Text(
                'Financer le Jalon ${jalon.ordre}',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Montant : ${Formatters.fcfa(jalon.montant)}\nLes fonds seront sécurisés sur le wallet de la mission.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 16),
              _ProviderTile(
                icon: Icons.waves,
                label: 'Wave CI',
                value: 'wave',
                selected: selectedProvider,
                onTap: () => setState(() => selectedProvider = 'wave'),
              ),
              _ProviderTile(
                icon: Icons.phone_android,
                label: 'Orange Money CI',
                value: 'orange_money',
                selected: selectedProvider,
                onTap: () => setState(() => selectedProvider = 'orange_money'),
              ),
              _ProviderTile(
                icon: Icons.account_balance,
                label: 'Virement Bancaire',
                value: 'virement_bancaire',
                selected: selectedProvider,
                onTap: () =>
                    setState(() => selectedProvider = 'virement_bancaire'),
              ),
              const SizedBox(height: 20),
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
                        Get.back();
                        final success = await devisController.payJalon(
                          jalon.id,
                          provider: selectedProvider,
                        );
                        if (success) {
                          unawaited(
                            Get.find<MissionsController>().loadMission(
                              jalon.missionId,
                              forceRefresh: true,
                            ),
                          );
                          if (context.mounted) {
                            showOtpValidationDialog(context, jalon);
                          }
                        }
                      },
                      child: const Text('Payer'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _ProviderTile extends StatelessWidget {
  const _ProviderTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String value;
  final String selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isSelected = selected == value;
    return ListTile(
      leading: Icon(icon, color: AppColors.primary),
      title: Text(
        label,
        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
      ),
      trailing: Icon(
        isSelected
            ? Icons.radio_button_checked
            : Icons.radio_button_unchecked,
        color: isSelected ? AppColors.primary : AppColors.textSecondary,
      ),
      onTap: onTap,
    );
  }
}
