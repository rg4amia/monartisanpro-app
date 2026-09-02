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
                  'Fonds Séquestrés',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Icon(Icons.lock_outline, color: AppColors.primary, size: 20),
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
              'Vos fonds sont sécurisés et ne sont libérés à l\'artisan qu\'après votre validation par OTP.',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 16),
          BudgetBar(
            label: 'Matériaux (Bloqué/Fournisseur)',
            amount: mission.montantMateriaux,
            percentage: pct(mission.montantMateriaux),
            color: AppColors.accent,
          ),
          const SizedBox(height: 14),
          BudgetBar(
            label: 'Main d\'œuvre (Libéré)',
            amount: libereMo,
            percentage: pct(libereMo),
            color: AppColors.success,
          ),
          const SizedBox(height: 14),
          BudgetBar(
            label: 'Main d\'œuvre (Restant bloqué)',
            amount: restMo,
            percentage: pct(restMo),
            color: AppColors.primary,
          ),
        ],
      ),
    );
  }
}
