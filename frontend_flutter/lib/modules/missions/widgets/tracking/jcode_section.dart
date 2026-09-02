import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import 'section_container.dart';

/// Accès au module J-Code matériaux (artisan, missions avec un montant
/// matériaux > 0). Le message s'adapte au statut de la mission.
class JCodeSection extends StatelessWidget {
  const JCodeSection({required this.mission, super.key});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final body = switch (mission.status) {
      'financee' =>
        'Les fonds materiaux sont bloques. Generez le J-Code pour le fournisseur agree.',
      'en_cours' =>
        'Le chantier est lance. Utilisez le module J-Code pour suivre ou regenerer le jeton materiaux.',
      _ =>
        'Le J-Code materiaux sera accessible des que la mission sera financee.',
    };

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'J-Code materiaux',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            body,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 14),
          ElevatedButton.icon(
            onPressed: () => Get.toNamed(
              Routes.jcode,
              arguments: <String, dynamic>{'missionId': mission.id},
            ),
            icon: const Icon(Icons.qr_code_2_outlined, size: 18),
            label: const Text('Ouvrir le module J-Code'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}
