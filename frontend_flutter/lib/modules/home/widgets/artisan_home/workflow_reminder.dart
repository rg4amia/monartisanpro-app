import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Encart « Rappel du workflow » : rappelle l'enchaînement des étapes, avec une
/// variante si une mission urgente (travail de nuit) est en file.
class WorkflowReminder extends StatelessWidget {
  const WorkflowReminder({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    final hasNightWork = controller.prioritizedArtisanMissions.any(
      (mission) => mission.urgency == 'urgent',
    );

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.artisanSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.14)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.route_outlined,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Rappel du workflow',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  hasNightWork
                      ? 'Demande recue -> devis -> mission financee -> J-Code ou stock d\'urgence -> jalons -> paiement OTP.'
                      : 'Demande recue -> devis -> mission financee -> J-Code materiaux -> jalons -> cloture client.',
                  style: const TextStyle(
                    fontSize: 12,
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
}
