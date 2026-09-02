import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Carte « Score ProsArtisan & Solvabilité » : score radial /1000, les 4 piliers
/// pondérés et l'état d'éligibilité au micro-crédit d'urgence (seuil 700).
class ProsArtisanScoreCard extends StatelessWidget {
  const ProsArtisanScoreCard({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    final score = controller.fluidityScore.value;
    final isEligible = score >= 700;

    return Container(
      margin: const EdgeInsets.only(bottom: 24),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isEligible
              ? AppColors.gold.withValues(alpha: 0.3)
              : AppColors.border,
          width: isEligible ? 1.5 : 1.0,
        ),
        boxShadow: AppColors.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        gradient: AppColors.gradientGold,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.gold.withValues(alpha: 0.3),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.stars_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Text(
                        'Score ProsArtisan & Solvabilité',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                          letterSpacing: -0.2,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: isEligible
                      ? AppColors.success.withValues(alpha: 0.12)
                      : AppColors.textSecondary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isEligible
                        ? AppColors.success.withValues(alpha: 0.25)
                        : Colors.transparent,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      isEligible
                          ? Icons.verified_rounded
                          : Icons.lock_outline_rounded,
                      color: isEligible
                          ? AppColors.success
                          : AppColors.textSecondary,
                      size: 13,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      isEligible
                          ? 'Crédit Débloqué (<2h)'
                          : 'Seuil Crédit : 700',
                      style: TextStyle(
                        color: isEligible
                            ? AppColors.success
                            : AppColors.textSecondary,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 90,
                height: 90,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.background,
                  border: Border.all(
                    color: isEligible ? AppColors.gold : AppColors.border,
                    width: 3,
                  ),
                  boxShadow: [
                    if (isEligible)
                      BoxShadow(
                        color: AppColors.gold.withValues(alpha: 0.2),
                        blurRadius: 10,
                        spreadRadius: 2,
                      ),
                  ],
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '$score',
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w900,
                        color:
                            isEligible ? AppColors.gold : AppColors.textPrimary,
                        letterSpacing: -1,
                      ),
                    ),
                    const Text(
                      '/ 1000',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  children: [
                    _detailRow(
                      'Fiabilité',
                      '40%',
                      controller.scoreFiabilite.value,
                      AppColors.tradePlumbing,
                    ),
                    const SizedBox(height: 6),
                    _detailRow(
                      'Intégrité',
                      '30%',
                      controller.scoreIntegrite.value,
                      AppColors.success,
                    ),
                    const SizedBox(height: 6),
                    _detailRow(
                      'Qualité',
                      '20%',
                      controller.scoreQualite.value,
                      AppColors.gold,
                    ),
                    const SizedBox(height: 6),
                    _detailRow(
                      'Réactivité',
                      '10%',
                      controller.scoreReactivite.value,
                      AppColors.tradeElectricity,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  color:
                      isEligible ? AppColors.success : AppColors.textSecondary,
                  size: 15,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    isEligible
                        ? 'Accès garanti au micro-crédit d\'urgence pour approvisionnement en quincaillerie.'
                        : 'Cumulez des avis 5 étoiles pour atteindre 700 et débloquer le crédit automatique.',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textSecondary,
                      height: 1.3,
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

  Widget _detailRow(
    String metric,
    String weight,
    double value,
    Color color,
  ) {
    final pct = (value * 100).toInt();
    return Row(
      children: [
        SizedBox(
          width: 72,
          child: Text(
            '$metric ($weight)',
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: value.clamp(0.0, 1.0),
              backgroundColor: AppColors.border,
              valueColor: AlwaysStoppedAnimation<Color>(color),
              minHeight: 6,
            ),
          ),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 32,
          child: Text(
            '$pct%',
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w800,
            ),
            textAlign: TextAlign.right,
          ),
        ),
      ],
    );
  }
}
