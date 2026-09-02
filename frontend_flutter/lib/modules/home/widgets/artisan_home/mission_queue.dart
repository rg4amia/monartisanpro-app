import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/mission_model.dart';
import '../../../main_tab/controllers/main_tab_controller.dart';
import '../../controllers/home_controller.dart';
import 'meta_text.dart';
import 'mission_action.dart';
import 'status_pill.dart';

/// « File de traitement » : les 4 prochaines missions de l'artisan avec, pour
/// chacune, l'action contextuelle attendue.
class MissionQueue extends StatelessWidget {
  const MissionQueue({
    super.key,
    required this.controller,
    required this.missions,
  });

  final HomeController controller;
  final List<MissionModel> missions;

  @override
  Widget build(BuildContext context) {
    final queue = missions.take(4).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'File de traitement',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
            ),
            TextButton(
              onPressed: () {
                if (Get.isRegistered<MainTabController>()) {
                  Get.find<MainTabController>().changeTab(1);
                } else {
                  Get.toNamed(Routes.missions);
                }
              },
              child: const Text('Voir tout'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (queue.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: const Column(
              children: [
                Icon(
                  Icons.inbox_outlined,
                  size: 36,
                  color: AppColors.textSecondary,
                ),
                SizedBox(height: 10),
                Text(
                  'Aucune mission pour le moment',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Les nouvelles demandes client apparaitront ici pour traitement.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          )
        else
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: queue
                .map(
                  (mission) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _MissionQueueCard(mission: mission),
                  ),
                )
                .toList(),
          ),
      ],
    );
  }
}

class _MissionQueueCard extends StatelessWidget {
  const _MissionQueueCard({required this.mission});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final action = _resolveAction(mission);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.textPrimary.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Mission #${mission.id}',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              StatusPill(
                label: Formatters.missionStatus(mission.status),
                color: action.color,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            mission.description ?? 'Travaux a realiser',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 13,
              height: 1.35,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 10,
            runSpacing: 8,
            children: [
              MetaText(
                icon: Icons.person_outline,
                text: mission.clientName ?? 'Client',
              ),
              MetaText(
                icon: Icons.place_outlined,
                text: mission.location ?? 'Adresse non renseignee',
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            action.subtitle,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: ElevatedButton(
              onPressed: action.onTap,
              style: ElevatedButton.styleFrom(
                backgroundColor: action.color,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: Text(action.label),
            ),
          ),
        ],
      ),
    );
  }

  MissionAction _resolveAction(MissionModel mission) {
    switch (mission.status) {
      case 'en_attente':
        if (mission.hasDevis) {
          return const MissionAction(
            label: 'Devis envoyé',
            subtitle:
                'Votre devis a été soumis. En attente de la décision du client.',
            color: AppColors.textSecondary,
            onTap: null,
          );
        }
        return MissionAction(
          label: 'Devis',
          subtitle:
              'Votre chiffrage est attendu pour lancer le parcours client.',
          color: AppColors.accent,
          onTap: () => Get.toNamed(Routes.devisCreation, arguments: mission),
        );
      case 'financee':
        return MissionAction(
          label: 'J-Code',
          subtitle: 'Mission financee. Preparez la commande materiaux.',
          color: AppColors.primary,
          onTap: () => Get.toNamed(
            Routes.jcode,
            arguments: <String, dynamic>{'missionId': mission.id},
          ),
        );
      case 'en_cours':
        return MissionAction(
          label: 'Suivre',
          subtitle: 'Soumettez les jalons et les preuves photo geolocalisees.',
          color: AppColors.success,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
      case 'litige':
        return MissionAction(
          label: 'Detail',
          subtitle: 'Un arbitrage est en cours sur ce chantier.',
          color: AppColors.danger,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
      default:
        return MissionAction(
          label: 'Voir',
          subtitle: 'Consulter le recapitulatif complet de cette mission.',
          color: AppColors.textSecondary,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
    }
  }
}
