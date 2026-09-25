import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/jalon_model.dart';
import '../../../../data/models/mission_model.dart';
import 'jalon_card.dart';
import 'section_container.dart';

/// Liste « Jalons et validations » : une [JalonCard] par jalon, ou un
/// message d'attente si aucun jalon n'est encore disponible.
class JalonsSection extends StatelessWidget {
  const JalonsSection({
    required this.role,
    required this.mission,
    required this.jalons,
    super.key,
  });

  final String role;
  final MissionModel mission;
  final List<JalonModel> jalons;

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.stairs_outlined, color: AppColors.primary, size: 20),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Étapes du chantier (Jalons)',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Chaque étape validée débloque la tranche de paiement convenue.',
            style: TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 14),
          if (jalons.isEmpty)
            const Text(
              'Aucune étape de travail n\'est encore planifiée pour cette mission.',
              style: TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
              ),
            )
          else
            Column(
              children: jalons
                  .map(
                    (jalon) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: JalonCard(
                        role: role,
                        mission: mission,
                        jalon: jalon,
                      ),
                    ),
                  )
                  .toList(),
            ),
        ],
      ),
    );
  }
}
