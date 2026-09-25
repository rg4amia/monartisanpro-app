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
        'L\'argent des matériaux est sécurisé. Générez le bon de retrait pour la quincaillerie agréée.',
      'en_cours' =>
        'Chantier en cours. Utilisez votre bon de retrait pour récupérer vos matériaux chez le fournisseur.',
      _ =>
        'Le bon de retrait matériaux sera disponible dès que le client aura validé le paiement.',
    };

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(
                Icons.confirmation_number_outlined,
                color: AppColors.primary,
                size: 20,
              ),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Bon Matériel Quincaillerie (J-Code)',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ],
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
            label: const Text('Ouvrir mon Bon Matériel'),
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
