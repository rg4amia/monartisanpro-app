import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/devis_model.dart';
import 'devis_money_format.dart';

class LignesSection extends StatelessWidget {
  final DevisModel devis;
  const LignesSection({super.key, required this.devis});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Détail des travaux',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        ...devis.lignes.map(
          (ligne) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _LigneCard(ligne: ligne),
          ),
        ),
      ],
    );
  }
}

class _LigneCard extends StatelessWidget {
  final DevisLigne ligne;
  const _LigneCard({required this.ligne});

  @override
  Widget build(BuildContext context) {
    final isMo = ligne.type == 'mo';
    final materialDetail = !isMo
        ? '${ligne.resolvedQuantity} x ${formatDevisFcfa(ligne.resolvedUnitPrice)}'
        : null;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isMo ? AppColors.supplierSoft : AppColors.artisanSoft,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              isMo ? Icons.build : Icons.category,
              size: 20,
              color: isMo ? AppColors.success : AppColors.accent,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  ligne.description,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  isMo ? 'Main d\'œuvre' : 'Matériaux',
                  style: TextStyle(
                    fontSize: 12,
                    color: isMo ? AppColors.success : AppColors.accent,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                if (materialDetail != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    materialDetail,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Text(
            formatDevisFcfa(ligne.montant),
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: isMo ? AppColors.success : AppColors.accent,
            ),
          ),
        ],
      ),
    );
  }
}
