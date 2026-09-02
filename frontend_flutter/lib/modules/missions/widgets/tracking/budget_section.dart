import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/mission_model.dart';
import 'budget_bar.dart';
import 'section_container.dart';

/// Vue budget côté artisan : total séquestre, répartition wallet matériaux /
/// wallet main d'œuvre et rappel de l'immuabilité du ratio.
class BudgetSection extends StatelessWidget {
  const BudgetSection({required this.mission, super.key});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final materiauxPct = (mission.ratioMateriaux * 100).round();
    final moPct = 100 - materiauxPct;

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Sequestre et budget',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Text(
                Formatters.fcfa(mission.montantTotal),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          BudgetBar(
            label: 'Wallet materiaux',
            amount: mission.montantMateriaux,
            percentage: materiauxPct,
            color: AppColors.success,
          ),
          const SizedBox(height: 14),
          BudgetBar(
            label: 'Wallet main d\'oeuvre',
            amount: mission.montantMo,
            percentage: moPct,
            color: AppColors.primary,
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.supplierSoft.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Le ratio materiaux / main d\'oeuvre est fige apres acceptation du devis.',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.success,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
