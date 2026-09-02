import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/devis_controller.dart';
import 'creation_money_format.dart';

/// Récapitulatif du devis : fournisseur, totaux main d'œuvre / matériaux,
/// total général et ratio matériaux.
class RecapSection extends StatelessWidget {
  const RecapSection({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Récapitulatif',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 16),
          Obx(
            () => Column(
              children: [
                if (controller.selectedSupplier.value != null) ...[
                  _RecapRow(
                    label: 'Fournisseur',
                    value: controller.selectedSupplier.value!.shopName,
                    valueColor: AppColors.primary,
                  ),
                  const SizedBox(height: 8),
                ],
                _RecapRow(
                  label: 'Main d\'œuvre',
                  value: formatCreationFcfa(controller.totalMo),
                  valueColor: AppColors.success,
                ),
                const SizedBox(height: 8),
                _RecapRow(
                  label: 'Matériaux',
                  value: formatCreationFcfa(controller.totalMat),
                  valueColor: AppColors.accent,
                ),
                const Divider(height: 24),
                _RecapRow(
                  label: 'TOTAL',
                  value: formatCreationFcfa(controller.totalGeneral),
                  valueColor: AppColors.primary,
                  isBold: true,
                ),
                const SizedBox(height: 8),
                _RecapRow(
                  label: 'Ratio matériaux',
                  value:
                      '${(controller.ratioMateriaux * 100).toStringAsFixed(1)}%',
                  valueColor: AppColors.textSecondary,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RecapRow extends StatelessWidget {
  const _RecapRow({
    required this.label,
    required this.value,
    required this.valueColor,
    this.isBold = false,
  });

  final String label;
  final String value;
  final Color valueColor;
  final bool isBold;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: isBold ? 16 : 14,
            fontWeight: isBold ? FontWeight.w700 : FontWeight.w500,
            color: AppColors.textPrimary,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isBold ? 16 : 14,
            fontWeight: FontWeight.w700,
            color: valueColor,
          ),
        ),
      ],
    );
  }
}
