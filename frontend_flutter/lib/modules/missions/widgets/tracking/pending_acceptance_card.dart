import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/missions_controller.dart';
import 'section_container.dart';

/// Carte « Réponse requise » : l'artisan accepte ou refuse la demande du
/// client (statut `pending_artisan_acceptance`), ce qui débloque la
/// création du devis.
class PendingAcceptanceCard extends StatelessWidget {
  const PendingAcceptanceCard({
    required this.mission,
    required this.controller,
    super.key,
  });

  final MissionModel mission;
  final MissionsController controller;

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(
                Icons.pending_actions_rounded,
                color: AppColors.accent,
                size: 24,
              ),
              SizedBox(width: 10),
              Text(
                'Réponse requise',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Ce client vous a choisi pour réaliser ses travaux. Acceptez-vous la demande pour débloquer la création du devis ?',
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => controller.acceptMissionRequest(mission.id),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF10B981),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 0,
                  ),
                  icon: const Icon(Icons.check_circle_outline, size: 18),
                  label: const Text(
                    'ACCEPTER',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => controller.rejectMissionRequest(mission.id),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFFEF4444),
                    side: const BorderSide(color: Color(0xFFEF4444)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  icon: const Icon(Icons.cancel_outlined, size: 18),
                  label: const Text(
                    'REFUSER',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
