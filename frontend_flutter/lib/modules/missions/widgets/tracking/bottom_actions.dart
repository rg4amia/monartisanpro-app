import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/devis_model.dart';
import '../../../../data/models/jalon_model.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/missions_controller.dart';
import '../../views/jalon_submit_screen.dart';
import 'open_devis_creation.dart';
import 'otp_validation_dialog.dart';

/// Barre d'actions basse contextuelle : le CTA principal (et un CTA
/// secondaire optionnel) dépendent du rôle, du statut de la mission et du
/// prochain jalon à traiter.
class BottomActions extends StatelessWidget {
  const BottomActions({
    required this.role,
    required this.mission,
    required this.devis,
    required this.nextPendingJalon,
    required this.nextSubmittedJalon,
    required this.onStartMission,
    super.key,
  });

  final String role;
  final MissionModel mission;
  final DevisModel? devis;
  final JalonModel? nextPendingJalon;
  final JalonModel? nextSubmittedJalon;
  final Future<bool> Function() onStartMission;

  @override
  Widget build(BuildContext context) {
    final isArtisan = role == 'artisan';

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: isArtisan ? _artisanActions() : _clientActions(context),
      ),
    );
  }

  Widget _artisanActions() {
    if (mission.status == 'en_attente' && devis == null && !mission.hasDevis) {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Creer le devis',
          icon: Icons.receipt_long_outlined,
          color: AppColors.accent,
          onTap: () => openDevisCreation(mission),
        ),
        secondary: _signalConfig(),
      );
    }

    if (mission.status == 'en_attente' && devis?.statut == 'soumis') {
      return _ActionRow(
        primary: const _ActionButtonConfig(
          label: 'Devis envoye',
          icon: Icons.schedule_outlined,
          color: AppColors.primary,
          onTap: null,
        ),
        secondary: _signalConfig(),
      );
    }

    if (mission.status == 'financee') {
      final secondary = mission.montantMateriaux > 0
          ? _ActionButtonConfig(
              label: 'J-Code',
              icon: Icons.qr_code_2_outlined,
              color: AppColors.primary,
              filled: false,
              onTap: () => Get.toNamed(
                Routes.jcode,
                arguments: <String, dynamic>{'missionId': mission.id},
              ),
            )
          : _signalConfig();

      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Demarrer',
          icon: Icons.play_arrow_rounded,
          color: AppColors.success,
          onTap: () => onStartMission(),
        ),
        secondary: secondary,
      );
    }

    if (mission.status == 'en_cours' && nextPendingJalon != null) {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Soumettre un jalon',
          icon: Icons.camera_alt_outlined,
          color: AppColors.primary,
          onTap: () => Get.to(
            () => const JalonSubmitScreen(),
            arguments: nextPendingJalon,
          ),
        ),
        secondary: _ActionButtonConfig(
          label: 'J-Code',
          icon: Icons.qr_code_2_outlined,
          color: AppColors.primary,
          filled: false,
          onTap: mission.montantMateriaux > 0
              ? () => Get.toNamed(
                    Routes.jcode,
                    arguments: <String, dynamic>{'missionId': mission.id},
                  )
              : null,
        ),
      );
    }

    return _ActionRow(
      primary: _ActionButtonConfig(
        label: 'Voir les jalons',
        icon: Icons.rule_folder_outlined,
        color: AppColors.primary,
        onTap: () {},
      ),
      secondary: _signalConfig(),
    );
  }

  Widget _clientActions(BuildContext context) {
    if (devis != null &&
        devis!.statut == 'soumis' &&
        mission.status == 'en_attente') {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Voir le devis',
          icon: Icons.receipt_long_outlined,
          color: AppColors.primary,
          onTap: () => Get.toNamed(Routes.devisReview, arguments: devis!.id),
        ),
      );
    }

    if ((mission.status == 'financee' || mission.status == 'en_cours') &&
        mission.status != 'litige') {
      if (nextSubmittedJalon != null) {
        return _ActionRow(
          primary: _ActionButtonConfig(
            label: 'Valider le jalon (OTP)',
            icon: Icons.check_circle_outline,
            color: AppColors.success,
            onTap: () => showOtpValidationDialog(context, nextSubmittedJalon!),
          ),
          secondary: _signalConfig(),
        );
      }

      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Signaler un litige',
          icon: Icons.warning_amber_outlined,
          color: AppColors.danger,
          onTap: () => Get.toNamed(Routes.litige, arguments: mission),
        ),
      );
    }

    if (mission.status == 'litige') {
      return _ActionRow(
        primary: const _ActionButtonConfig(
          label: 'Litige en cours',
          icon: Icons.gavel_outlined,
          color: AppColors.accent,
          onTap: null,
        ),
      );
    }

    if (mission.status == 'terminee' || mission.status == 'completed') {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Évaluer la prestation',
          icon: Icons.star_rounded,
          color: const Color(0xFFF59E0B),
          onTap: () async {
            final res = await Get.toNamed(
              Routes.rating,
              arguments: <String, dynamic>{
                'missionId': mission.id,
                'evalueId': mission.artisanId,
                'targetName': mission.artisanName ?? 'Artisan',
                'targetRole': 'artisan',
                'targetSubtitle': 'Artisan principal de la mission',
              },
            );
            if (res == true) {
              unawaited(
                Get.find<MissionsController>()
                    .loadMission(mission.id, forceRefresh: true),
              );
            }
          },
        ),
        secondary: _signalConfig(),
      );
    }

    return const SizedBox.shrink();
  }

  _ActionButtonConfig _signalConfig() => _ActionButtonConfig(
        label: 'Signaler',
        icon: Icons.warning_amber_outlined,
        color: AppColors.danger,
        filled: false,
        onTap: () => Get.toNamed(Routes.litige, arguments: mission),
      );
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.primary, this.secondary});

  final _ActionButtonConfig primary;
  final _ActionButtonConfig? secondary;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _ActionButton(config: primary)),
        if (secondary != null) ...[
          const SizedBox(width: 12),
          Expanded(child: _ActionButton(config: secondary!)),
        ],
      ],
    );
  }
}

class _ActionButtonConfig {
  const _ActionButtonConfig({
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
    this.filled = true,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
  final bool filled;
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.config});

  final _ActionButtonConfig config;

  @override
  Widget build(BuildContext context) {
    if (config.filled) {
      return ElevatedButton.icon(
        onPressed: config.onTap,
        icon: Icon(config.icon, size: 18),
        label: Text(config.label),
        style: ElevatedButton.styleFrom(
          backgroundColor: config.color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      );
    }

    return OutlinedButton.icon(
      onPressed: config.onTap,
      icon: Icon(config.icon, size: 18),
      label: Text(config.label),
      style: OutlinedButton.styleFrom(
        foregroundColor: config.color,
        side: BorderSide(color: config.color.withValues(alpha: 0.28)),
        padding: const EdgeInsets.symmetric(vertical: 14),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
      ),
    );
  }
}
