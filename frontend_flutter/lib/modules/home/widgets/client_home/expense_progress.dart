import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Ligne « dépense par catégorie » : libellé, montant formaté en K FCFA et barre
/// de progression proportionnelle au total.
class ExpenseProgress extends StatelessWidget {
  const ExpenseProgress({
    super.key,
    required this.category,
    required this.amount,
    required this.percentage,
    required this.color,
  });

  final String category;
  final int amount;
  final double percentage;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final formattedAmount = '${(amount / 1000).toStringAsFixed(0)}K FCFA';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              category,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            Text(
              formattedAmount,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: LinearProgressIndicator(
            value: percentage,
            backgroundColor: AppColors.border,
            color: color,
            minHeight: 8,
          ),
        ),
      ],
    );
  }
}
