import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Conteneur de section standard de l'écran de suivi de mission :
/// surface blanche arrondie avec ombre légère.
class SectionContainer extends StatelessWidget {
  const SectionContainer({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.textPrimary.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }
}
