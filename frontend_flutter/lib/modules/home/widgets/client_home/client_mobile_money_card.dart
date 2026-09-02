import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Carte « Compte Mobile Money » du tableau de bord client : affiche l'opérateur
/// et le numéro associé, ou invite à en associer un via une modale.
class ClientMobileMoneyCard extends StatelessWidget {
  const ClientMobileMoneyCard({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final hasPhone = controller.paymentPhone.value.isNotEmpty;
      final provider = controller.preferredPaymentProvider.value;
      final providerName = _providerName(provider);
      final providerColor = _providerColor(provider);

      return Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.border),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.payments_rounded,
                    color: Color(0xFF10B981),
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Compte Mobile Money',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        hasPhone
                            ? 'Actif pour règlements & remboursements'
                            : 'Aucun numéro associé',
                        style: TextStyle(
                          fontSize: 12,
                          color: hasPhone
                              ? const Color(0xFF10B981)
                              : AppColors.textSecondary,
                          fontWeight:
                              hasPhone ? FontWeight.w600 : FontWeight.w400,
                        ),
                      ),
                    ],
                  ),
                ),
                if (hasPhone)
                  IconButton(
                    onPressed: () =>
                        _showPaymentPhoneModal(context, controller),
                    icon: const Icon(
                      Icons.edit_outlined,
                      size: 20,
                      color: AppColors.textSecondary,
                    ),
                    tooltip: 'Modifier',
                  ),
              ],
            ),
            const SizedBox(height: 14),
            if (hasPhone) ...[
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: providerColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(14),
                  border:
                      Border.all(color: providerColor.withValues(alpha: 0.3)),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: providerColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        providerName,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        controller.paymentPhone.value,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                    const Icon(
                      Icons.check_circle_rounded,
                      color: Color(0xFF10B981),
                      size: 18,
                    ),
                  ],
                ),
              ),
            ] else ...[
              const Text(
                'Associez votre compte Wave, Orange, MTN ou Moov pour régler vos devis en 1 clic et recevoir automatiquement vos remboursements en cas de litige.',
                style: TextStyle(
                  fontSize: 12.5,
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              ElevatedButton.icon(
                onPressed: () => _showPaymentPhoneModal(context, controller),
                icon: const Icon(Icons.add_card_rounded, size: 18),
                label: const Text('Associer mon numéro Mobile Money'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.client,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(double.infinity, 44),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ],
        ),
      );
    });
  }
}

String _providerName(String key) {
  switch (key.toLowerCase()) {
    case 'wave':
      return 'WAVE';
    case 'orange_money':
      return 'ORANGE';
    case 'mtn_money':
      return 'MTN';
    case 'moov_money':
      return 'MOOV';
    default:
      return key.toUpperCase();
  }
}

Color _providerColor(String key) {
  switch (key.toLowerCase()) {
    case 'wave':
      return const Color(0xFF00A3FF);
    case 'orange_money':
      return const Color(0xFFFF7900);
    case 'mtn_money':
      return const Color(0xFFFFCC00);
    case 'moov_money':
      return const Color(0xFF005BA6);
    default:
      return AppColors.client;
  }
}

void _showPaymentPhoneModal(BuildContext context, HomeController controller) {
  final selectedProvider = (controller.preferredPaymentProvider.value.isNotEmpty
          ? controller.preferredPaymentProvider.value
          : 'wave')
      .obs;
  final phoneCtrl = TextEditingController(text: controller.paymentPhone.value);
  final errorMsg = RxnString();

  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      return Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(ctx).viewInsets.bottom,
        ),
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 20),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE5E7EB),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFECFDF5),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.payments_rounded,
                      color: Color(0xFF10B981),
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Moyen de paiement Mobile Money',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Associer votre compte Wave, Orange, MTN ou Moov',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              const Text(
                'Opérateur de paiement',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              Obx(
                () => Row(
                  children: [
                    _providerChip(
                      'wave',
                      'Wave',
                      const Color(0xFF00A3FF),
                      selectedProvider,
                    ),
                    const SizedBox(width: 8),
                    _providerChip(
                      'orange_money',
                      'Orange',
                      const Color(0xFFFF7900),
                      selectedProvider,
                    ),
                    const SizedBox(width: 8),
                    _providerChip(
                      'mtn_money',
                      'MTN',
                      const Color(0xFFFFCC00),
                      selectedProvider,
                    ),
                    const SizedBox(width: 8),
                    _providerChip(
                      'moov_money',
                      'Moov',
                      const Color(0xFF005BA6),
                      selectedProvider,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: phoneCtrl,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: 'Numéro Mobile Money (10 chiffres)',
                  hintText: 'Ex: 0701020304',
                  prefixIcon: const Icon(Icons.phone_android_rounded),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              Obx(
                () => errorMsg.value != null
                    ? Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          errorMsg.value!,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
              const SizedBox(height: 20),
              Obx(
                () => ElevatedButton(
                  onPressed: controller.isSavingPaymentPhone.value
                      ? null
                      : () async {
                          final phone = phoneCtrl.text.trim();
                          if (phone.isEmpty || phone.length < 10) {
                            errorMsg.value =
                                'Veuillez saisir un numéro valide à 10 chiffres';
                            return;
                          }
                          errorMsg.value = null;
                          final ok = await controller.updatePaymentPhone(
                            newPaymentPhone: phone,
                            provider: selectedProvider.value,
                          );
                          if (ok) {
                            Get.back();
                            Get.snackbar(
                              'Numéro associé',
                              'Votre compte Mobile Money a été mis à jour avec succès.',
                              backgroundColor: const Color(0xFFD1FAE5),
                              colorText: const Color(0xFF065F46),
                            );
                          } else {
                            errorMsg.value =
                                'Échec de l\'enregistrement. Veuillez réessayer.';
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.client,
                    foregroundColor: Colors.white,
                    minimumSize: const Size(double.infinity, 50),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: controller.isSavingPaymentPhone.value
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text(
                          'Enregistrer mon numéro',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
              const SizedBox(height: 10),
            ],
          ),
        ),
      );
    },
  );
}

Widget _providerChip(
  String providerKey,
  String label,
  Color color,
  RxString selectedProvider,
) {
  final isSelected = selectedProvider.value == providerKey;
  return Expanded(
    child: GestureDetector(
      onTap: () => selectedProvider.value = providerKey,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: isSelected
              ? color.withValues(alpha: 0.14)
              : const Color(0xFFF3F4F6),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? color : Colors.transparent,
            width: 1.5,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isSelected ? color : Colors.grey.shade400,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w500,
                color: isSelected ? color : AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
