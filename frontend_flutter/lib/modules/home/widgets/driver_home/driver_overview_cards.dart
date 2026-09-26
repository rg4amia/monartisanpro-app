import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../client_home/section_empty_note.dart';

/// Carte des notes reçues par le livreur, lue depuis `GET /dashboard`.
///
/// Un livreur jamais évalué (`rating == null`) voit une mention explicite
/// plutôt qu'une note inventée : la carte affichait auparavant en dur
/// « 4.9/5 — Basé sur 48 courses » et « +0.2 ce mois » pour tous les comptes
/// (Règle d'or 29).
Widget buildDriverRatingEvolutionCard({
  required double? rating,
  required int ratingsCount,
  required Map<int, double> distribution,
}) {
  final hasRating = rating != null && ratingsCount > 0;

  return Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: AppColors.border),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.01),
          blurRadius: 10,
          offset: const Offset(0, 4),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Mes notations',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 16),
        if (!hasRating)
          const SectionEmptyNote(
            icon: Icons.star_outline_rounded,
            message:
                "Non évalué — votre note apparaîtra ici dès qu'un client aura évalué une de vos livraisons.",
          )
        else
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: [
                      Text(
                        rating.toStringAsFixed(1),
                        style: const TextStyle(
                          fontSize: 36,
                          fontWeight: FontWeight.w900,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const Text(
                        '/5',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: List.generate(5, (index) {
                      final IconData icon;
                      if (rating >= index + 1) {
                        icon = Icons.star_rounded;
                      } else if (rating >= index + 0.5) {
                        icon = Icons.star_half_rounded;
                      } else {
                        icon = Icons.star_outline_rounded;
                      }
                      return Icon(icon, color: Colors.amber, size: 18);
                    }),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    ratingsCount > 1
                        ? 'Basé sur $ratingsCount évaluations'
                        : 'Basé sur 1 évaluation',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 24),
              Expanded(
                child: Column(
                  children: [
                    for (final stars in const [5, 4, 3, 2, 1]) ...[
                      _buildRatingDistributionRow(
                        stars,
                        (distribution[stars] ?? 0).clamp(0, 1).toDouble(),
                      ),
                      if (stars > 1) const SizedBox(height: 4),
                    ],
                  ],
                ),
              ),
            ],
          ),
      ],
    ),
  );
}

Widget _buildRatingDistributionRow(int stars, double percentage) {
  return Row(
    children: [
      Text(
        '$stars★',
        style: const TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.bold,
          color: AppColors.textSecondary,
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        child: ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: percentage,
            backgroundColor: AppColors.border,
            valueColor: AlwaysStoppedAnimation<Color>(
              stars >= 4
                  ? AppColors.success
                  : (stars == 3 ? Colors.amber : Colors.red),
            ),
            minHeight: 6,
          ),
        ),
      ),
      const SizedBox(width: 8),
      SizedBox(
        width: 24,
        child: Text(
          '${(percentage * 100).toInt()}%',
          style: const TextStyle(
            fontSize: 10,
            color: AppColors.textSecondary,
          ),
          textAlign: TextAlign.right,
        ),
      ),
    ],
  );
}

Widget buildDriverStatCard({
  required String title,
  required String value,
  required String subtitle,
  required Color color,
  required Color background,
  required IconData icon,
  VoidCallback? onTap,
}) {
  return GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 14),
          Text(
            value,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    ),
  );
}

Widget buildDriverTipCard() {
  return Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: AppColors.driverSoft,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: AppColors.driver.withValues(alpha: 0.14)),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.lightbulb_outline_rounded, color: AppColors.driver),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Conseil du jour',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              SizedBox(height: 4),
              Text(
                'Activez le GPS dans l\'onglet "Véhicule" pour aider les quincailliers et artisans à localiser vos livraisons plus rapidement.',
                style: TextStyle(
                  fontSize: 12.5,
                  height: 1.4,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
