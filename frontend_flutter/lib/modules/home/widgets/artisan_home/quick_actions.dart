import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import '../../../main_tab/controllers/main_tab_controller.dart';
import '../../controllers/home_controller.dart';
import 'action_tile.dart';

/// Bloc « Actions Prioritaires Terrain » : raccourcis one-tap vers la prochaine
/// étape (devis, J-Code, suivi de chantier, quincailleries, portefeuille).
class QuickActions extends StatelessWidget {
  const QuickActions({
    super.key,
    required this.controller,
    required this.missions,
  });

  final HomeController controller;
  final List<MissionModel> missions;

  @override
  Widget build(BuildContext context) {
    final pendingMission = _findFirst(missions, 'en_attente');
    final fundedMission = _findFirst(missions, 'financee');
    final ongoingMission = _findFirst(missions, 'en_cours');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Actions Prioritaires Terrain',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
                letterSpacing: -0.3,
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: AppColors.accent.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                'One-Tap',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  color: AppColors.accent,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ActionTile(
          icon: Icons.receipt_long_rounded,
          gradient: AppColors.gradientElectricity,
          badge: pendingMission != null ? 'À Chiffrer' : null,
          title: pendingMission == null
              ? 'Consulter les demandes'
              : 'Créer le devis de la mission #${pendingMission.id}',
          subtitle: pendingMission == null
              ? 'Ouvrir vos missions en attente de chiffrage'
              : pendingMission.description ?? 'Nouvelle demande client',
          onTap: () {
            if (pendingMission != null) {
              Get.toNamed(Routes.devisCreation, arguments: pendingMission);
            } else {
              _openArtisanTab(1);
            }
          },
        ),
        const SizedBox(height: 12),
        ActionTile(
          icon: Icons.qr_code_2_rounded,
          gradient: AppColors.gradientMasonry,
          badge: fundedMission != null ? 'Retrait Prêt' : null,
          title: fundedMission == null
              ? 'Module J-Code Matériaux'
              : 'Générer le J-Code de la mission #${fundedMission.id}',
          subtitle: fundedMission == null
              ? 'Préparer une commande matériaux ou consulter un code actif'
              : 'Mission financée, prête pour le retrait quincaillerie',
          onTap: () => Get.toNamed(
            Routes.jcode,
            arguments: fundedMission == null
                ? null
                : <String, dynamic>{'missionId': fundedMission.id},
          ),
        ),
        const SizedBox(height: 12),
        ActionTile(
          icon: Icons.checklist_rtl_rounded,
          gradient: AppColors.gradientPainting,
          badge: ongoingMission != null ? 'En Cours' : null,
          title: ongoingMission == null
              ? 'Suivre mes chantiers'
              : 'Suivre le chantier #${ongoingMission.id}',
          subtitle: ongoingMission == null
              ? 'Voir les jalons, preuves photo et validations OTP'
              : 'Avancement des jalons et déboursement main d\'œuvre',
          onTap: () {
            if (ongoingMission != null) {
              Get.toNamed(Routes.missionTracking, arguments: ongoingMission);
            } else {
              _openArtisanTab(1);
            }
          },
        ),
        const SizedBox(height: 12),
        ActionTile(
          icon: Icons.store_rounded,
          gradient: AppColors.gradientWelding,
          title: 'Quincailleries Agréées',
          subtitle:
              'Consulter le réseau de quincailleries partenaires et leurs stocks',
          onTap: () => Get.toNamed(Routes.clientSuppliers),
        ),
        const SizedBox(height: 12),
        ActionTile(
          icon: Icons.account_balance_wallet_rounded,
          gradient: const LinearGradient(
            colors: [Color(0xFF0D9488), Color(0xFF14B8A6)],
          ),
          title: 'Paiements Reçus & Portefeuille',
          subtitle:
              'Consulter tous les reversements Wave/Orange Money et jalons libérés',
          onTap: () => Get.toNamed(Routes.wallet),
        ),
      ],
    );
  }

  MissionModel? _findFirst(List<MissionModel> missions, String status) {
    for (final mission in missions) {
      if (mission.status == status) {
        return mission;
      }
    }
    return null;
  }

  void _openArtisanTab(int index) {
    if (Get.isRegistered<MainTabController>()) {
      Get.find<MainTabController>().changeTab(index);
      return;
    }
    Get.toNamed(Routes.missions);
  }
}
