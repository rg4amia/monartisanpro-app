import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/devis_controller.dart';

/// Demande le numéro Mobile Money de l'artisan lorsqu'il n'en a pas encore
/// renseigné dans son profil. Le serveur refuse la création du devis tant que
/// ce numéro n'est pas fourni (voir CreateDevisRequest côté backend) ; ce
/// champ évite un échec générique "Données invalides" à l'envoi.
class PaymentPhoneSection extends StatelessWidget {
  const PaymentPhoneSection({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (!controller.needsPaymentPhone) {
        return const SizedBox.shrink();
      }

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Numéro Mobile Money',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Indiquez le numéro sur lequel vous serez payé pour cette mission.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                hintText: 'Ex: 0709090909',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 12,
                ),
              ),
              onChanged: (value) => controller.paymentPhone.value = value,
            ),
            const SizedBox(height: 12),
            Obx(
              () => Row(
                children: [
                  Expanded(
                    child: _ProviderChip(
                      label: 'Wave',
                      selected: controller.preferredProvider.value == 'wave',
                      onTap: () => controller.preferredProvider.value = 'wave',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _ProviderChip(
                      label: 'Orange Money',
                      selected:
                          controller.preferredProvider.value == 'orange_money',
                      onTap: () =>
                          controller.preferredProvider.value = 'orange_money',
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    });
  }
}

class _ProviderChip extends StatelessWidget {
  const _ProviderChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: selected ? AppColors.primary : AppColors.border,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : AppColors.textPrimary,
          ),
        ),
      ),
    );
  }
}
