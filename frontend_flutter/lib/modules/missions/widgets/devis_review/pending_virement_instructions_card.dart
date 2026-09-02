import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/payment_model.dart';

class PendingVirementInstructionsCard extends StatelessWidget {
  final VirementInstructionsModel? instructions;

  const PendingVirementInstructionsCard({
    super.key,
    required this.instructions,
  });

  @override
  Widget build(BuildContext context) {
    if (instructions == null) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
        boxShadow: [
          BoxShadow(
            color: AppColors.textPrimary.withValues(alpha: 0.05),
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
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.secondary,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.account_balance,
                  color: AppColors.primary,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Text(
                'Instructions de Virement',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const Divider(height: 24),
          const Text(
            'Veuillez effectuer un virement bancaire sur le compte séquestre ProsArtisan :',
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 16),
          _InstructionDetail(
            label: 'Banque',
            value: instructions!.bankName,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'Titulaire du compte',
            value: instructions!.accountName,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'IBAN',
            value: instructions!.iban,
            canCopy: true,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'Référence du virement (à indiquer obligatoirement)',
            value: instructions!.reference,
            canCopy: true,
            highlighted: true,
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.secondary,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(Icons.info, color: AppColors.primary, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Le traitement d\'un virement bancaire peut prendre de 24h à 48h ouvrables. Cliquez sur "Vérifier le paiement" une fois le virement émis.',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.primary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InstructionDetail extends StatelessWidget {
  final String label;
  final String value;
  final bool canCopy;
  final bool highlighted;

  const _InstructionDetail({
    required this.label,
    required this.value,
    this.canCopy = false,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: highlighted ? AppColors.secondary : AppColors.background,
            borderRadius: BorderRadius.circular(8),
            border: highlighted
                ? Border.all(color: AppColors.primary.withValues(alpha: 0.3))
                : null,
          ),
          child: Row(
            children: [
              Expanded(
                child: SelectableText(
                  value,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: highlighted ? FontWeight.w700 : FontWeight.w600,
                    color:
                        highlighted ? AppColors.primary : AppColors.textPrimary,
                  ),
                ),
              ),
              if (canCopy) ...[
                const SizedBox(width: 8),
                GestureDetector(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: value));
                    Get.snackbar(
                      'Copié !',
                      '$value copié dans le presse-papiers.',
                      snackPosition: SnackPosition.TOP,
                      duration: const Duration(seconds: 2),
                    );
                  },
                  child: Icon(
                    Icons.copy,
                    size: 16,
                    color: highlighted
                        ? AppColors.primary
                        : AppColors.textSecondary,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
