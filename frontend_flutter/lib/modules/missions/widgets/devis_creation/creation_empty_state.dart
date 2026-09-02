import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// État vide réutilisé par les sections de l'écran de création de devis
/// (fournisseur, matériaux, main d'œuvre, jalons).
class CreationEmptyState extends StatelessWidget {
  const CreationEmptyState({
    required this.icon,
    required this.message,
    required this.hint,
    super.key,
  });

  final IconData icon;
  final String message;
  final String hint;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          Icon(icon, size: 48, color: AppColors.textSecondary),
          const SizedBox(height: 12),
          Text(
            message,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            hint,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}
