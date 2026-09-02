import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/devis_model.dart';
import 'creation_money_format.dart';

/// Carte d'une ligne de devis (main d'œuvre ou matériau), réutilisée par
/// la section matériaux et la section main d'œuvre.
class LigneCard extends StatelessWidget {
  const LigneCard({
    required this.ligne,
    required this.onDelete,
    super.key,
  });

  final DevisLigne ligne;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final isMo = ligne.type == 'mo';
    final detail = !isMo
        ? '${ligne.resolvedQuantity} x ${formatCreationFcfa(ligne.resolvedUnitPrice)}'
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
                  formatCreationFcfa(ligne.montant),
                  style: TextStyle(
                    fontSize: 13,
                    color: isMo ? AppColors.success : AppColors.accent,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (detail != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    detail,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            onPressed: onDelete,
            icon: const Icon(
              Icons.delete_outline,
              color: AppColors.danger,
              size: 20,
            ),
          ),
        ],
      ),
    );
  }
}
