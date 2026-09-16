import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Note compacte affichée à la place d'une section vide du tableau de bord.
///
/// Ces sections étaient auparavant préremplies de données de démonstration.
/// Les retirer sans rien mettre à la place laisserait des cadres muets, que
/// l'utilisateur lirait comme une panne. Dire explicitement qu'il n'y a rien
/// à montrer — et pourquoi — vaut mieux qu'un vide ou qu'un chiffre inventé.
class SectionEmptyNote extends StatelessWidget {
  const SectionEmptyNote(
      {super.key, required this.icon, required this.message,});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Icon(
            icon,
            size: 20,
            color: AppColors.textSecondary.withValues(alpha: 0.6),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12.5,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
