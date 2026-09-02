import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Rappel du parcours de création de devis + bannière de règle métier
/// (total des jalons == total du devis, OTP client au déblocage).
class WorkflowCard extends StatelessWidget {
  const WorkflowCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Rappel du parcours',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: 8),
          Text(
            '1. Chiffrez la main d\'œuvre et les matériaux.',
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
          SizedBox(height: 4),
          Text(
            '2. Répartissez les paiements en jalons cohérents avec le chantier.',
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
          SizedBox(height: 4),
          Text(
            '3. Après validation client, le ratio matériaux / main d\'œuvre devient immuable.',
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          SizedBox(height: 12),
          _RuleBanner(),
        ],
      ),
    );
  }
}

class _RuleBanner extends StatelessWidget {
  const _RuleBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.supplierSoft.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Text(
        'Le total des jalons doit égaler le total du devis. Les déblocages main d\'œuvre se feront ensuite avec OTP client.',
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: AppColors.success,
          height: 1.35,
        ),
      ),
    );
  }
}
