import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/missions_controller.dart';
import '../widgets/tracking/bottom_actions.dart';
import '../widgets/tracking/budget_section.dart';
import '../widgets/tracking/counterparty_card.dart';
import '../widgets/tracking/devis_section.dart';
import '../widgets/tracking/escrow_section.dart';
import '../widgets/tracking/jalons_section.dart';
import '../widgets/tracking/jcode_section.dart';
import '../widgets/tracking/mission_evaluations_section.dart';
import '../widgets/tracking/mission_header_card.dart';
import '../widgets/tracking/pending_acceptance_card.dart';
import '../widgets/tracking/workflow_card.dart';

/// Écran de suivi d'une mission, partagé client / artisan. Orchestration
/// uniquement : le `MissionsController` porte l'état, chaque section vit dans
/// `widgets/tracking/`.
class MissionTrackingScreen extends StatefulWidget {
  const MissionTrackingScreen({super.key});

  @override
  State<MissionTrackingScreen> createState() => _MissionTrackingScreenState();
}

class _MissionTrackingScreenState extends State<MissionTrackingScreen> {
  int? _missionId;

  @override
  void initState() {
    super.initState();
    final arg = Get.arguments;
    if (arg is MissionModel) {
      _missionId = arg.id;
    } else if (arg is int) {
      _missionId = arg;
    }

    if (_missionId != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        Get.find<MissionsController>().loadMission(_missionId!);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MissionsController>();
    final role = StorageService.getRole() ?? 'client';
    final isArtisan = role == 'artisan';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(isArtisan ? 'Suivi du chantier' : 'Details de la mission'),
        elevation: 0,
        backgroundColor: AppColors.surface,
        surfaceTintColor: AppColors.surface,
      ),
      body: Obx(() {
        final mission = controller.currentMission.value;
        if (controller.isLoading.value || mission == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final devis = controller.latestDevis;

        return RefreshIndicator(
          onRefresh: () =>
              controller.loadMission(mission.id, forceRefresh: true),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 120),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                MissionHeaderCard(mission: mission),
                const SizedBox(height: 16),
                CounterpartyCard(role: role, mission: mission),
                const SizedBox(height: 16),
                WorkflowCard(
                  mission: mission,
                  devis: devis,
                  role: role,
                  hasReferentPendingValidation:
                      controller.hasReferentPendingValidation,
                ),
                if (isArtisan &&
                    mission.status == 'pending_artisan_acceptance') ...[
                  const SizedBox(height: 16),
                  PendingAcceptanceCard(
                    mission: mission,
                    controller: controller,
                  ),
                ],
                const SizedBox(height: 16),
                if (!isArtisan &&
                    (mission.status == 'financee' ||
                        mission.status == 'en_cours'))
                  EscrowSection(mission: mission, jalons: controller.jalons)
                else
                  BudgetSection(mission: mission),
                const SizedBox(height: 16),
                DevisSection(role: role, mission: mission, devis: devis),
                const SizedBox(height: 16),
                if (isArtisan && mission.montantMateriaux > 0) ...[
                  JCodeSection(mission: mission),
                  const SizedBox(height: 16),
                ],
                JalonsSection(
                  role: role,
                  mission: mission,
                  jalons: controller.jalons,
                ),
                if (!isArtisan &&
                    (mission.status == 'terminee' ||
                        mission.status == 'completed')) ...[
                  const SizedBox(height: 16),
                  MissionEvaluationsSection(
                    mission: mission,
                    onEvaluated: () => controller.loadMission(
                      mission.id,
                      forceRefresh: true,
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      }),
      bottomNavigationBar: Obx(() {
        final mission = controller.currentMission.value;
        if (mission == null) {
          return const SizedBox.shrink();
        }

        return BottomActions(
          role: role,
          mission: mission,
          devis: controller.latestDevis,
          nextPendingJalon: controller.nextPendingJalon,
          nextSubmittedJalon: controller.nextSubmittedJalon,
          onStartMission: () =>
              controller.updateMissionStatus(mission.id, 'en_cours'),
        );
      }),
    );
  }
}
