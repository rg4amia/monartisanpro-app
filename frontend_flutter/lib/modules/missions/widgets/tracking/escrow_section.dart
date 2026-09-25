import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/jalon_model.dart';
import '../../../../data/models/mission_model.dart';
import 'budget_bar.dart';
import 'section_container.dart';

/// Vue séquestre côté client : rappel de la protection OTP et répartition
/// matériaux bloqués / main d'œuvre libérée / main d'œuvre restante.
class EscrowSection extends StatelessWidget {
  const EscrowSection({
    required this.mission,
    required this.jalons,
    super.key,
  });

  final MissionModel mission;
  final List<JalonModel> jalons;

  @override
  Widget build(BuildContext context) {
    final totalMo = mission.montantMo;
    final libereMo = jalons
        .where((j) => j.statut == 'paye')
        .fold<int>(0, (sum, j) => sum + j.montant);
    final restMo = totalMo - libereMo;
    final total = mission.montantTotal;
    int pct(int amount) => total > 0 ? amount * 100 ~/ total : 0;

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Expanded(
                child: Text(
                  'Coffre de Sécurité — Paiement Garanti',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Icon(Icons.shield_outlined, color: AppColors.primary, size: 20),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.secondary.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Votre argent est protégé dans le coffre ProsArtisan. Il ne sera versé à l\'artisan qu\'après votre validation par code secret.',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 16),
          BudgetBar(
            label: 'Part Matériaux (Réservée Quincaillerie)',
            amount: mission.montantMateriaux,
            percentage: pct(mission.montantMateriaux),
            color: AppColors.accent,
          ),
          const SizedBox(height: 14),
          BudgetBar(
            label: 'Part Travail (Déjà versée à l\'artisan)',
            amount: libereMo,
            percentage: pct(libereMo),
            color: AppColors.success,
          ),
          const SizedBox(height: 14),
          BudgetBar(
            label: 'Part Travail (Gardée au coffre)',
            amount: restMo,
            percentage: pct(restMo),
            color: AppColors.primary,
          ),
        ],
      ),
    );
  }
}
