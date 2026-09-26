import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';
import 'stat_card.dart';

/// Grille 2×2 du pipeline de missions de l'artisan
/// (à deviser / financées / travaux / litiges).
class StatGrid extends StatelessWidget {
  const StatGrid({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Pipeline de mission',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        // Chaque rangée est haute de son contenu (IntrinsicHeight) plutôt que
        // forcée par un ratio fixe (GridView.count) : sur police système
        // agrandie ou écran étroit, un texte plus haut que le ratio calculé
        // provoquait un "BOTTOM OVERFLOWED" sur les 4 cartes du pipeline.
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: StatCard(
                  label: 'A deviser',
                  value: '${controller.pendingMissionCount}',
                  subtitle: 'Demandes en attente',
                  color: AppColors.accent,
                  icon: Icons.receipt_long_outlined,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: StatCard(
                  label: 'Financees',
                  value: '${controller.fundedMissionCount}',
                  subtitle: 'Bons matériaux prêts',
                  color: AppColors.primary,
                  icon: Icons.account_balance_wallet_outlined,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: StatCard(
                  label: 'Travaux',
                  value: '${controller.ongoingMissionCount}',
                  subtitle: 'Chantiers actifs',
                  color: AppColors.success,
                  icon: Icons.construction_outlined,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: StatCard(
                  label: 'Litiges',
                  value: '${controller.disputedMissionCount}',
                  subtitle: 'A suivre',
                  color: AppColors.danger,
                  icon: Icons.gpp_bad_outlined,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
