import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/jalon_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/evaluation_repository.dart';
import '../controllers/devis_controller.dart';
import '../controllers/missions_controller.dart';
import 'jalon_submit_screen.dart';

abstract class _Palette {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.secondary;
  static const success = AppColors.success;
  static const successLight = AppColors.supplierSoft;
  static const warning = AppColors.accent;
  static const warningLight = AppColors.artisanSoft;
  static const danger = AppColors.danger;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
}

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
      backgroundColor: _Palette.bg,
      appBar: AppBar(
        title: Text(isArtisan ? 'Suivi du chantier' : 'Details de la mission'),
        elevation: 0,
        backgroundColor: _Palette.surface,
        surfaceTintColor: _Palette.surface,
      ),
      body: Obx(() {
        final mission = controller.currentMission.value;
        if (controller.isLoading.value || mission == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final devis = controller.latestDevis;

        return RefreshIndicator(
          onRefresh: () => controller.loadMission(
            mission.id,
            forceRefresh: true,
          ),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 120),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _MissionHeaderCard(mission: mission),
                const SizedBox(height: 16),
                _CounterpartyCard(
                  role: role,
                  mission: mission,
                ),
                const SizedBox(height: 16),
                _WorkflowCard(
                  mission: mission,
                  devis: devis,
                  role: role,
                  hasReferentPendingValidation:
                      controller.hasReferentPendingValidation,
                ),
                if (isArtisan && mission.status == 'pending_artisan_acceptance') ...[
                  const SizedBox(height: 16),
                  _PendingAcceptanceCard(
                    mission: mission,
                    controller: controller,
                  ),
                ],
                const SizedBox(height: 16),
                if (isArtisan)
                  _BudgetSection(mission: mission)
                else if (mission.status == 'financee' || mission.status == 'en_cours')
                  _EscrowSection(mission: mission, jalons: controller.jalons)
                else
                  _BudgetSection(mission: mission),
                const SizedBox(height: 16),
                _DevisSection(
                  role: role,
                  mission: mission,
                  devis: devis,
                ),
                const SizedBox(height: 16),
                if (isArtisan && mission.montantMateriaux > 0)
                  Column(
                    children: [
                      _MaterialsSection(mission: mission),
                      const SizedBox(height: 16),
                    ],
                  ),
                _JalonsSection(
                  role: role,
                  mission: mission,
                  jalons: controller.jalons,
                ),
                if (!isArtisan && (mission.status == 'terminee' || mission.status == 'completed')) ...[
                  const SizedBox(height: 16),
                  _MissionEvaluationsSection(
                    mission: mission,
                    onEvaluated: () => controller.loadMission(mission.id, forceRefresh: true),
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

        return _BottomActions(
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

Future<void> _openDevisCreation(MissionModel mission) async {
  final result = await Get.toNamed(Routes.devisCreation, arguments: mission);
  if (result != true || !Get.isRegistered<MissionsController>()) {
    return;
  }

  WidgetsBinding.instance.addPostFrameCallback((_) {
    final controller = Get.find<MissionsController>();
    controller.loadMission(
      mission.id,
      forceRefresh: true,
    );

    Get.snackbar(
      'Devis créé',
      'Votre devis a été envoyé au client pour validation.',
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 3),
    );
  });
}

class _MissionHeaderCard extends StatelessWidget {
  const _MissionHeaderCard({required this.mission});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(mission.status);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.04),
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
              _Pill(
                label: Formatters.missionStatus(mission.status),
                color: statusColor,
              ),
              const SizedBox(width: 8),
              if (mission.needsReferent)
                const _Pill(
                  label: 'Referent obligatoire',
                  color: _Palette.warning,
                ),
              const Spacer(),
              Text(
                '#MS-${mission.id.toString().padLeft(5, '0')}',
                style: const TextStyle(
                  fontSize: 12,
                  color: _Palette.muted,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            mission.description ?? 'Mission',
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: _Palette.ink,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 10,
            children: [
              _HeaderMeta(
                icon: Icons.place_outlined,
                text: mission.location ?? 'Adresse non renseignee',
              ),
              _HeaderMeta(
                icon: Icons.timer_outlined,
                text: 'Urgence ${mission.urgencyLabel.toLowerCase()}',
              ),
              _HeaderMeta(
                icon: Icons.calendar_today_outlined,
                text: Formatters.date(mission.createdAt),
              ),
            ],
          ),
          if (mission.photos.isNotEmpty) ...[
            const SizedBox(height: 18),
            const Divider(color: _Palette.subtle),
            const SizedBox(height: 12),
            const Text(
              'Visuels transmis par le client :',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: _Palette.ink,
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 90,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: mission.photos.length,
                separatorBuilder: (context, index) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final mediaUrl = mission.photos[index];
                  final isVideo = _isVideoUrl(mediaUrl);

                  return GestureDetector(
                    onTap: () => _openMedia(context, mediaUrl, isVideo),
                    child: Container(
                      width: 90,
                      height: 90,
                      decoration: BoxDecoration(
                        color: Colors.black12,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _Palette.subtle),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            if (isVideo)
                              Container(
                                color: Colors.grey[800],
                                child: const Center(
                                  child: CircleAvatar(
                                    backgroundColor: Colors.white24,
                                    radius: 20,
                                    child: Icon(
                                      Icons.play_arrow_rounded,
                                      color: Colors.white,
                                      size: 24,
                                    ),
                                  ),
                                ),
                              )
                            else
                              CachedNetworkImage(
                                imageUrl: mediaUrl,
                                fit: BoxFit.cover,
                                placeholder: (context, url) => const Center(
                                  child: SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  ),
                                ),
                                errorWidget: (context, url, error) => const Center(
                                  child: Icon(Icons.broken_image_outlined, size: 24),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }

  bool _isVideoUrl(String url) {
    final path = url.toLowerCase();
    return path.endsWith('.mp4') ||
        path.endsWith('.mov') ||
        path.endsWith('.avi') ||
        path.endsWith('.mkv') ||
        path.endsWith('.3gp') ||
        path.endsWith('.m4v');
  }

  void _openMedia(BuildContext context, String url, bool isVideo) {
    if (isVideo) {
      launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } else {
      Get.dialog(
        Dialog.fullscreen(
          backgroundColor: Colors.black,
          child: Stack(
            children: [
              InteractiveViewer(
                minScale: 0.5,
                maxScale: 4.0,
                child: Center(
                  child: CachedNetworkImage(
                    imageUrl: url,
                    fit: BoxFit.contain,
                    placeholder: (context, url) => const CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                    errorWidget: (context, url, error) => const Icon(
                      Icons.broken_image_outlined,
                      color: Colors.white70,
                      size: 48,
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 20,
                right: 20,
                child: SafeArea(
                  child: IconButton(
                    icon: const CircleAvatar(
                      backgroundColor: Colors.black45,
                      child: Icon(Icons.close, color: Colors.white),
                    ),
                    onPressed: () => Get.back(),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'en_attente':
        return _Palette.warning;
      case 'financee':
        return _Palette.primary;
      case 'en_cours':
        return _Palette.success;
      case 'litige':
        return _Palette.danger;
      default:
        return _Palette.muted;
    }
  }
}

class _HeaderMeta extends StatelessWidget {
  const _HeaderMeta({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: _Palette.muted),
        const SizedBox(width: 6),
        Text(
          text,
          style: const TextStyle(
            fontSize: 12,
            color: _Palette.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

class _CounterpartyCard extends StatelessWidget {
  const _CounterpartyCard({
    required this.role,
    required this.mission,
  });

  final String role;
  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final isArtisan = role == 'artisan';
    final title = isArtisan ? 'Client de la mission' : 'Artisan en charge';
    final name = isArtisan
        ? (mission.clientName ?? 'Client')
        : (mission.artisanName ?? 'Artisan');
    final subtitle = isArtisan
        ? 'Ce client validera vos jalons via OTP.'
        : 'Le suivi terrain et les jalons sont portes par cet artisan.';

    final showActions = isArtisan && mission.isPaid;
    final displaySubtitle = showActions && mission.location != null
        ? 'Adresse : ${mission.location}'
        : subtitle;

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: _Palette.primaryLight,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  isArtisan ? Icons.person_outline : Icons.handyman_outlined,
                  color: _Palette.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: _Palette.muted,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: _Palette.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      displaySubtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        color: _Palette.muted,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (showActions && (mission.clientPhone != null || (mission.clientLatitude != null && mission.clientLongitude != null))) ...[
            const SizedBox(height: 12),
            const Divider(color: _Palette.subtle, height: 1),
            const SizedBox(height: 12),
            Row(
              children: [
                if (mission.clientPhone != null)
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        launchUrl(
                          Uri.parse('tel:${mission.clientPhone}'),
                          mode: LaunchMode.externalApplication,
                        );
                      },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _Palette.primary,
                        side: const BorderSide(color: _Palette.primary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                      icon: const Icon(Icons.phone, size: 16),
                      label: Text(
                        mission.clientPhone!,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                if (mission.clientPhone != null && mission.clientLatitude != null && mission.clientLongitude != null)
                  const SizedBox(width: 8),
                if (mission.clientLatitude != null && mission.clientLongitude != null)
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        final lat = mission.clientLatitude;
                        final lng = mission.clientLongitude;
                        launchUrl(
                          Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng'),
                          mode: LaunchMode.externalApplication,
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _Palette.primary,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                      icon: const Icon(Icons.map_outlined, size: 16),
                      label: const Text(
                        'Itinéraire Maps',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _WorkflowCard extends StatelessWidget {
  const _WorkflowCard({
    required this.mission,
    required this.devis,
    required this.role,
    required this.hasReferentPendingValidation,
  });

  final MissionModel mission;
  final DevisModel? devis;
  final String role;
  final bool hasReferentPendingValidation;

  @override
  Widget build(BuildContext context) {
    final progress = _calculateProgress(mission, devis);
    final phase = _describePhase();

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Phase en cours',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
              ),
              Text(
                '$progress%',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: _Palette.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress / 100,
              minHeight: 8,
              backgroundColor: _Palette.primary.withValues(alpha: 0.12),
              valueColor: const AlwaysStoppedAnimation(_Palette.primary),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            phase.title,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: phase.color,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            phase.body,
            style: const TextStyle(
              fontSize: 13,
              color: _Palette.muted,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  int _calculateProgress(MissionModel mission, DevisModel? devis) {
    if (mission.status == 'terminee') return 100;
    if (mission.status == 'litige') return 78;
    if (mission.status == 'en_cours') return 72;
    if (mission.status == 'financee') return 48;
    if (devis != null && devis.statut == 'soumis') return 24;
    if (devis != null && devis.statut == 'accepte') return 48;
    return 12;
  }

  _PhaseDescription _describePhase() {
    final isArtisan = role == 'artisan';

    if (mission.status == 'litige') {
      return const _PhaseDescription(
        title: 'Mission en arbitrage',
        body:
            'Un litige a ete ouvert. Les fonds et les validations sont temporairement geles jusqu\'a decision.',
        color: _Palette.danger,
      );
    }

    if (mission.status == 'pending_artisan_acceptance') {
      return _PhaseDescription(
        title: isArtisan
            ? 'Nouvelle demande de devis reçue'
            : 'Demande transmise à l\'artisan',
        body: isArtisan
            ? 'Un client vous a sélectionné pour cette mission. Veuillez accepter ou refuser la demande.'
            : 'L\'artisan a été notifié. En attente de son acceptation pour créer le devis.',
        color: _Palette.warning,
      );
    }

    if (mission.status == 'en_attente') {
      if (devis == null) {
        return _PhaseDescription(
          title: isArtisan ? 'Devis a preparer' : 'Attente du devis artisan',
          body: isArtisan
              ? 'La demande client a ete recue. Vous devez envoyer un devis detaille pour lancer le sequestre.'
              : 'L\'artisan doit chiffrer les materiaux, la main d\'oeuvre et les jalons.',
          color: _Palette.warning,
        );
      }

      if (devis!.statut == 'soumis') {
        return _PhaseDescription(
          title: isArtisan
              ? 'Devis envoye, paiement en attente'
              : 'Devis recu, validation client requise',
          body: isArtisan
              ? 'Le client doit maintenant accepter le devis et financer la mission.'
              : 'Acceptez ou refusez le devis pour passer a la phase de sequestre.',
          color: _Palette.primary,
        );
      }
    }

    if (mission.status == 'financee') {
      return _PhaseDescription(
        title: 'Mission financee',
        body: mission.montantMateriaux > 0
            ? 'Le sequestre est en place. Generez le J-Code materiaux puis demarrez les travaux.'
            : 'Le sequestre est en place. Vous pouvez maintenant demarrer les travaux.',
        color: _Palette.primary,
      );
    }

    if (mission.status == 'en_cours' && hasReferentPendingValidation) {
      return const _PhaseDescription(
        title: 'Validation referent attendue',
        body:
            'Cette mission depasse le seuil de controle. Les jalons valides attendent la visite physique du referent.',
        color: _Palette.warning,
      );
    }

    if (mission.status == 'en_cours') {
      return _PhaseDescription(
        title: isArtisan
            ? 'Travaux en execution'
            : 'Mission en cours de realisation',
        body: isArtisan
            ? 'Soumettez les jalons avec photos geolocalisees. Le client recevra un OTP a chaque validation.'
            : 'Suivez l\'avancement des jalons et validez les OTP recus par SMS lorsque necessaire.',
        color: _Palette.success,
      );
    }

    return const _PhaseDescription(
      title: 'Mission terminee',
      body:
          'La mission est cloturee. Les validations et decaissements principaux ont ete traites.',
      color: _Palette.success,
    );
  }
}

class _PhaseDescription {
  const _PhaseDescription({
    required this.title,
    required this.body,
    required this.color,
  });

  final String title;
  final String body;
  final Color color;
}

class _BudgetSection extends StatelessWidget {
  const _BudgetSection({required this.mission});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final materiauxPct = (mission.ratioMateriaux * 100).round();
    final moPct = 100 - materiauxPct;

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Sequestre et budget',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
              ),
              Text(
                Formatters.fcfa(mission.montantTotal),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: _Palette.ink,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _BudgetBar(
            label: 'Wallet materiaux',
            amount: mission.montantMateriaux,
            percentage: materiauxPct,
            color: _Palette.success,
          ),
          const SizedBox(height: 14),
          _BudgetBar(
            label: 'Wallet main d\'oeuvre',
            amount: mission.montantMo,
            percentage: moPct,
            color: _Palette.primary,
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _Palette.successLight.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Le ratio materiaux / main d\'oeuvre est fige apres acceptation du devis.',
              style: TextStyle(
                fontSize: 12,
                color: _Palette.success,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BudgetBar extends StatelessWidget {
  const _BudgetBar({
    required this.label,
    required this.amount,
    required this.percentage,
    required this.color,
  });

  final String label;
  final int amount;
  final int percentage;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: _Palette.ink,
                ),
              ),
            ),
            Text(
              Formatters.fcfa(amount),
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: percentage / 100,
            minHeight: 7,
            backgroundColor: color.withValues(alpha: 0.12),
            valueColor: AlwaysStoppedAnimation(color),
          ),
        ),
      ],
    );
  }
}

class _EscrowSection extends StatelessWidget {
  const _EscrowSection({
    required this.mission,
    required this.jalons,
  });

  final MissionModel mission;
  final List<JalonModel> jalons;

  @override
  Widget build(BuildContext context) {
    int totalMo = mission.montantMo;
    int libereMo = jalons
        .where((j) => j.statut == 'paye')
        .fold(0, (sum, j) => sum + j.montant);
    int restMo = totalMo - libereMo;

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Expanded(
                child: Text(
                  'Fonds Séquestrés',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
              ),
              Icon(Icons.lock_outline, color: _Palette.primary, size: 20),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _Palette.primaryLight.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Vos fonds sont sécurisés et ne sont libérés à l\'artisan qu\'après votre validation par OTP.',
              style: TextStyle(
                fontSize: 12,
                color: _Palette.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 16),
          _BudgetBar(
            label: 'Matériaux (Bloqué/Fournisseur)',
            amount: mission.montantMateriaux,
            percentage: mission.montantTotal > 0
                ? (mission.montantMateriaux * 100 ~/ mission.montantTotal)
                : 0,
            color: _Palette.warning,
          ),
          const SizedBox(height: 14),
          _BudgetBar(
            label: 'Main d\'œuvre (Libéré)',
            amount: libereMo,
            percentage: mission.montantTotal > 0
                ? (libereMo * 100 ~/ mission.montantTotal)
                : 0,
            color: _Palette.success,
          ),
          const SizedBox(height: 14),
          _BudgetBar(
            label: 'Main d\'œuvre (Restant bloqué)',
            amount: restMo,
            percentage: mission.montantTotal > 0
                ? (restMo * 100 ~/ mission.montantTotal)
                : 0,
            color: _Palette.primary,
          ),
        ],
      ),
    );
  }
}

class _DevisSection extends StatelessWidget {
  const _DevisSection({
    required this.role,
    required this.mission,
    required this.devis,
  });

  final String role;
  final MissionModel mission;
  final DevisModel? devis;

  @override
  Widget build(BuildContext context) {
    final isArtisan = role == 'artisan';

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Devis',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _Palette.ink,
            ),
          ),
          const SizedBox(height: 14),
          if (devis == null)
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isArtisan
                      ? 'Aucun devis n\'a encore ete envoye pour cette mission.'
                      : 'L\'artisan n\'a pas encore soumis de devis.',
                  style: const TextStyle(
                    fontSize: 13,
                    color: _Palette.muted,
                    height: 1.4,
                  ),
                ),
                if (isArtisan) ...[
                  const SizedBox(height: 14),
                  ElevatedButton.icon(
                    onPressed: () => _openDevisCreation(mission),
                    icon: const Icon(Icons.receipt_long_outlined, size: 18),
                    label: const Text('Creer le devis'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _Palette.warning,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ],
              ],
            )
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _Pill(
                      label: _devisLabel(devis!.statut),
                      color: _devisColor(devis!.statut),
                    ),
                    _Pill(
                      label:
                          '${devis!.lignes.length} ligne${devis!.lignes.length > 1 ? 's' : ''}',
                      color: _Palette.muted,
                    ),
                    _Pill(
                      label:
                          '${devis!.jalons.length} jalon${devis!.jalons.length > 1 ? 's' : ''}',
                      color: _Palette.primary,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _InfoRow(
                  label: 'Total general',
                  value: Formatters.fcfa(devis!.totalGeneral),
                ),
                _InfoRow(
                  label: 'Main d\'oeuvre',
                  value: Formatters.fcfa(devis!.totalMo),
                ),
                _InfoRow(
                  label: 'Materiaux',
                  value: Formatters.fcfa(devis!.totalMat),
                ),
                if (devis!.ratioMateriaux != null)
                  _InfoRow(
                    label: 'Ratio materiaux',
                    value: '${(devis!.ratioMateriaux! * 100).round()}%',
                  ),
                const SizedBox(height: 10),
                Text(
                  isArtisan
                      ? devis!.statut == 'soumis'
                          ? 'Votre devis a ete transmis. Il sera finance apres acceptation client.'
                          : 'Le devis de cette mission a deja ete enregistre.'
                      : devis!.statut == 'soumis'
                          ? 'Ouvrez le detail pour accepter ou refuser ce devis.'
                          : 'Le devis a deja ete traite pour cette mission.',
                  style: const TextStyle(
                    fontSize: 12,
                    color: _Palette.muted,
                    height: 1.35,
                  ),
                ),
                if (!isArtisan && devis!.statut == 'soumis') ...[
                  const SizedBox(height: 14),
                  ElevatedButton.icon(
                    onPressed: () =>
                        Get.toNamed(Routes.devisReview, arguments: devis!.id),
                    icon: const Icon(Icons.visibility_outlined, size: 18),
                    label: const Text('Voir le devis'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _Palette.primary,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ],
              ],
            ),
        ],
      ),
    );
  }

  String _devisLabel(String statut) {
    switch (statut) {
      case 'soumis':
        return 'Soumis';
      case 'accepte':
        return 'Accepte';
      case 'refuse':
        return 'Refuse';
      default:
        return 'Brouillon';
    }
  }

  Color _devisColor(String statut) {
    switch (statut) {
      case 'soumis':
        return _Palette.warning;
      case 'accepte':
        return _Palette.success;
      case 'refuse':
        return _Palette.danger;
      default:
        return _Palette.muted;
    }
  }
}

class _MaterialsSection extends StatelessWidget {
  const _MaterialsSection({required this.mission});

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

    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'J-Code materiaux',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _Palette.ink,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            body,
            style: const TextStyle(
              fontSize: 13,
              color: _Palette.muted,
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
              backgroundColor: _Palette.primary,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}

class _JalonsSection extends StatelessWidget {
  const _JalonsSection({
    required this.role,
    required this.mission,
    required this.jalons,
  });

  final String role;
  final MissionModel mission;
  final List<JalonModel> jalons;

  @override
  Widget build(BuildContext context) {
    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Jalons et validations',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _Palette.ink,
            ),
          ),
          const SizedBox(height: 14),
          if (jalons.isEmpty)
            const Text(
              'Aucun jalon n\'est encore disponible pour cette mission.',
              style: TextStyle(
                fontSize: 13,
                color: _Palette.muted,
              ),
            )
          else
            Column(
              children: jalons
                  .map(
                    (jalon) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _JalonCard(
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

class _JalonCard extends StatelessWidget {
  const _JalonCard({
    required this.role,
    required this.mission,
    required this.jalon,
  });

  final String role;
  final MissionModel mission;
  final JalonModel jalon;

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MissionsController>();
    final config = _statusConfig();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: config.bg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: config.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: config.color.withValues(alpha: 0.12),
                child: Text(
                  '${jalon.ordre}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: config.color,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      jalon.description,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: _Palette.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      Formatters.fcfa(jalon.montant),
                      style: const TextStyle(
                        fontSize: 12,
                        color: _Palette.muted,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _Pill(
                label: Formatters.jalonStatus(jalon.statut),
                color: config.color,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            config.message,
            style: const TextStyle(
              fontSize: 12,
              color: _Palette.muted,
              height: 1.35,
            ),
          ),
          if (jalon.photosJson != null && jalon.photosJson!.isNotEmpty) ...[
            const SizedBox(height: 14),
            const Text(
              'Preuves de réalisation :',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: _Palette.ink,
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 70,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: jalon.photosJson!.length,
                separatorBuilder: (context, index) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final photo = jalon.photosJson![index];
                  final url = photo['url'] as String? ?? '';
                  final isVideo = _isPathVideo(url);
                  return GestureDetector(
                    onTap: () => _openMedia(context, url, isVideo),
                    child: Container(
                      width: 70,
                      height: 70,
                      decoration: BoxDecoration(
                        color: Colors.black12,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: _Palette.subtle),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            if (isVideo)
                              Container(
                                color: Colors.grey[800],
                                child: const Center(
                                  child: Icon(
                                    Icons.play_arrow_rounded,
                                    color: Colors.white,
                                    size: 24,
                                  ),
                                ),
                              )
                            else
                              CachedNetworkImage(
                                imageUrl: url,
                                fit: BoxFit.cover,
                                placeholder: (context, url) => const Center(
                                  child: SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(strokeWidth: 2),
                                  ),
                                ),
                                errorWidget: (context, url, error) => const Center(
                                  child: Icon(Icons.broken_image_outlined, size: 20),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
          if (role == 'artisan') ...[
            if (jalon.isPending) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => Get.to(
                    () => const JalonSubmitScreen(),
                    arguments: jalon,
                  ),
                  icon: const Icon(Icons.camera_alt_outlined, size: 18),
                  label: const Text('Soumettre le jalon'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _Palette.primary,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
            ] else if (jalon.isSubmitted) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => Get.to(
                        () => const JalonSubmitScreen(),
                        arguments: jalon,
                      ),
                      icon: const Icon(Icons.add_a_photo_outlined, size: 16),
                      label: const Text(
                        'Compléter preuves',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () async => controller.requestOtp(jalon.id),
                      icon: const Icon(Icons.sms_outlined, size: 16),
                      label: const Text(
                        'Renvoyer OTP',
                        style: TextStyle(fontSize: 12),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _Palette.primary,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
          if (role == 'client' && (jalon.isSubmitted || jalon.isPending)) ...[
            const SizedBox(height: 12),
            Column(
              children: [
                if (jalon.isSubmitted) ...[
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () async {
                        final confirm = await Get.dialog<bool>(
                          AlertDialog(
                            title: const Text('Accepter les preuves ?'),
                            content: const Text(
                              'En acceptant ces preuves, vous validez la réalisation de ce jalon et débloquez le paiement pour l\'artisan.',
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Get.back(result: false),
                                child: const Text('Annuler'),
                              ),
                              ElevatedButton(
                                onPressed: () => Get.back(result: true),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: _Palette.success,
                                ),
                                child: const Text('Accepter'),
                              ),
                            ],
                          ),
                        );
                        if (confirm == true) {
                          await controller.acceptJalonProofs(jalon.id);
                        }
                      },
                      icon: const Icon(Icons.verified_outlined, size: 18),
                      label: const Text('Accepter les preuves'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _Palette.success,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                ],
                Row(
                  children: [
                    if (mission.paymentType == 'hybrid') ...[
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: () => _startJalonPaymentAndValidation(context, jalon),
                          icon: const Icon(Icons.payment_rounded, size: 18),
                          label: const Text('Financer'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: _Palette.primary,
                            foregroundColor: Colors.white,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => showOtpValidationDialog(context, jalon),
                        icon: const Icon(Icons.sms_outlined, size: 18),
                        label: const Text('Valider via OTP'),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: _Palette.success),
                          foregroundColor: _Palette.success,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            if (jalon.isSubmitted) ...[
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: TextButton.icon(
                  onPressed: () async => controller.requestOtp(jalon.id),
                  icon: const Icon(Icons.sms_outlined, size: 16, color: _Palette.primary),
                  label: const Text(
                    'Renvoyer le code de validation (SMS)',
                    style: TextStyle(color: _Palette.primary, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  void _startJalonPaymentAndValidation(BuildContext context, JalonModel jalon) {
    final DevisController devisController = Get.isRegistered<DevisController>()
        ? Get.find<DevisController>()
        : Get.put(DevisController());

    String selectedProvider = 'wave';
    Get.dialog(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: StatefulBuilder(
            builder: (dialogContext, setState) => Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.payment, color: _Palette.primary, size: 36),
                const SizedBox(height: 12),
                Text(
                  'Financer le Jalon ${jalon.ordre}',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  'Montant : ${Formatters.fcfa(jalon.montant)}\nLes fonds seront sécurisés sur le wallet de la mission.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 12, color: _Palette.muted),
                ),
                const SizedBox(height: 16),
                ListTile(
                  leading: const Icon(Icons.waves, color: _Palette.primary),
                  title: const Text('Wave CI', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  trailing: Icon(
                    selectedProvider == 'wave' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                    color: selectedProvider == 'wave' ? _Palette.primary : _Palette.muted,
                  ),
                  onTap: () => setState(() => selectedProvider = 'wave'),
                ),
                ListTile(
                  leading: const Icon(Icons.phone_android, color: _Palette.primary),
                  title: const Text('Orange Money CI', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  trailing: Icon(
                    selectedProvider == 'orange_money' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                    color: selectedProvider == 'orange_money' ? _Palette.primary : _Palette.muted,
                  ),
                  onTap: () => setState(() => selectedProvider = 'orange_money'),
                ),
                ListTile(
                  leading: const Icon(Icons.account_balance, color: _Palette.primary),
                  title: const Text('Virement Bancaire', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  trailing: Icon(
                    selectedProvider == 'virement_bancaire' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                    color: selectedProvider == 'virement_bancaire' ? _Palette.primary : _Palette.muted,
                  ),
                  onTap: () => setState(() => selectedProvider = 'virement_bancaire'),
                ),
                const SizedBox(height: 20),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Get.back(),
                        child: const Text('Annuler'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          Get.back();
                          final success = await devisController.payJalon(
                            jalon.id,
                            provider: selectedProvider,
                          );
                          if (success) {
                            unawaited(Get.find<MissionsController>().loadMission(jalon.missionId, forceRefresh: true));
                            if (context.mounted) {
                              showOtpValidationDialog(context, jalon);
                            }
                          }
                        },
                        child: const Text('Payer'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  _JalonStatusConfig _statusConfig() {
    if (jalon.isPaid) {
      return const _JalonStatusConfig(
        color: _Palette.success,
        bg: _Palette.successLight,
        border: Color(0xFFB8E7CC),
        message: 'Paiement libere sur le wallet main d\'oeuvre.',
      );
    }

    if (jalon.isValidated) {
      if (mission.needsReferent) {
        return const _JalonStatusConfig(
          color: _Palette.warning,
          bg: _Palette.warningLight,
          border: Color(0xFFF6D68A),
          message:
              'OTP valide. La liberation attend la validation physique du referent.',
        );
      }

      return const _JalonStatusConfig(
        color: _Palette.success,
        bg: _Palette.successLight,
        border: Color(0xFFB8E7CC),
        message: 'OTP valide. Le paiement est en cours de liberation.',
      );
    }

    if (jalon.isSubmitted) {
      return const _JalonStatusConfig(
        color: _Palette.primary,
        bg: _Palette.primaryLight,
        border: Color(0xFFC7D2FE),
        message:
            'Preuves envoyees. Le client doit maintenant confirmer l\'OTP recu par SMS.',
      );
    }

    return const _JalonStatusConfig(
      color: _Palette.warning,
      bg: Color(0xFFFFFBEB),
      border: Color(0xFFFDE68A),
      message:
          'Ce jalon doit etre documente avec des photos geolocalisees avant soumission.',
    );
  }

  bool _isPathVideo(String path) {
    return path.endsWith('.mp4') ||
        path.endsWith('.mov') ||
        path.endsWith('.avi') ||
        path.endsWith('.3gp') ||
        path.endsWith('.m4v');
  }

  void _openMedia(BuildContext context, String url, bool isVideo) {
    if (isVideo) {
      launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } else {
      Get.dialog(
        Dialog.fullscreen(
          backgroundColor: Colors.black,
          child: Stack(
            children: [
              InteractiveViewer(
                minScale: 0.5,
                maxScale: 4.0,
                child: Center(
                  child: CachedNetworkImage(
                    imageUrl: url,
                    fit: BoxFit.contain,
                    placeholder: (context, url) => const CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                    errorWidget: (context, url, error) => const Icon(
                      Icons.broken_image_outlined,
                      color: Colors.white70,
                      size: 48,
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 20,
                right: 20,
                child: SafeArea(
                  child: IconButton(
                    icon: const CircleAvatar(
                      backgroundColor: Colors.black45,
                      child: Icon(Icons.close, color: Colors.white),
                    ),
                    onPressed: () => Get.back(),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }
  }
}

class _JalonStatusConfig {
  const _JalonStatusConfig({
    required this.color,
    required this.bg,
    required this.border,
    required this.message,
  });

  final Color color;
  final Color bg;
  final Color border;
  final String message;
}

void showOtpValidationDialog(BuildContext context, JalonModel jalon) {
  final MissionsController controller = Get.find<MissionsController>();
  final TextEditingController otpController = TextEditingController();

  Get.dialog(
    Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.sms_outlined, color: _Palette.success, size: 36),
            const SizedBox(height: 12),
            Text(
              'Valider le Jalon ${jalon.ordre}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Saisissez le code de validation OTP à 4 chiffres envoyé par SMS.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: _Palette.muted),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: otpController,
              keyboardType: TextInputType.number,
              maxLength: 4,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 20, letterSpacing: 8, fontWeight: FontWeight.bold),
              decoration: const InputDecoration(
                hintText: '0000',
                counterText: '',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextButton.icon(
              onPressed: () async {
                await controller.requestOtp(jalon.id);
              },
              icon: const Icon(Icons.refresh, size: 16, color: _Palette.primary),
              label: const Text(
                'Renvoyer le code par SMS',
                style: TextStyle(
                  color: _Palette.primary,
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Get.back(),
                    child: const Text('Annuler'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () async {
                      final otp = otpController.text.trim();
                      if (otp.length != 4) {
                        Get.snackbar('Code invalide', 'Le code doit contenir 4 chiffres');
                        return;
                      }
                      Get.back();
                      final success = await controller.validateOtp(jalon.id, otp);
                      if (success) {
                        unawaited(controller.loadMission(jalon.missionId, forceRefresh: true));
                      }
                    },
                    child: const Text('Valider'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _BottomActions extends StatelessWidget {
  const _BottomActions({
    required this.role,
    required this.mission,
    required this.devis,
    required this.nextPendingJalon,
    required this.nextSubmittedJalon,
    required this.onStartMission,
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
        color: _Palette.surface,
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
        child: isArtisan ? _artisanActions() : _clientActions(),
      ),
    );
  }

  Widget _artisanActions() {
    if (mission.status == 'en_attente' && devis == null && !mission.hasDevis) {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Creer le devis',
          icon: Icons.receipt_long_outlined,
          color: _Palette.warning,
          onTap: () => _openDevisCreation(mission),
        ),
        secondary: _ActionButtonConfig(
          label: 'Signaler',
          icon: Icons.warning_amber_outlined,
          color: _Palette.danger,
          filled: false,
          onTap: () => Get.toNamed(Routes.litige, arguments: mission),
        ),
      );
    }

    if (mission.status == 'en_attente' && devis?.statut == 'soumis') {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Devis envoye',
          icon: Icons.schedule_outlined,
          color: _Palette.primary,
          onTap: null,
        ),
        secondary: _ActionButtonConfig(
          label: 'Signaler',
          icon: Icons.warning_amber_outlined,
          color: _Palette.danger,
          filled: false,
          onTap: () => Get.toNamed(Routes.litige, arguments: mission),
        ),
      );
    }

    if (mission.status == 'financee') {
      final secondary = mission.montantMateriaux > 0
          ? _ActionButtonConfig(
              label: 'J-Code',
              icon: Icons.qr_code_2_outlined,
              color: _Palette.primary,
              filled: false,
              onTap: () => Get.toNamed(
                Routes.jcode,
                arguments: <String, dynamic>{'missionId': mission.id},
              ),
            )
          : _ActionButtonConfig(
              label: 'Signaler',
              icon: Icons.warning_amber_outlined,
              color: _Palette.danger,
              filled: false,
              onTap: () => Get.toNamed(Routes.litige, arguments: mission),
            );

      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Demarrer',
          icon: Icons.play_arrow_rounded,
          color: _Palette.success,
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
          color: _Palette.primary,
          onTap: () => Get.to(
            () => const JalonSubmitScreen(),
            arguments: nextPendingJalon,
          ),
        ),
        secondary: _ActionButtonConfig(
          label: 'J-Code',
          icon: Icons.qr_code_2_outlined,
          color: _Palette.primary,
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
        color: _Palette.primary,
        onTap: () {},
      ),
      secondary: _ActionButtonConfig(
        label: 'Signaler',
        icon: Icons.warning_amber_outlined,
        color: _Palette.danger,
        filled: false,
        onTap: () => Get.toNamed(Routes.litige, arguments: mission),
      ),
    );
  }

  Widget _clientActions() {
    if (devis != null &&
        devis!.statut == 'soumis' &&
        mission.status == 'en_attente') {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Voir le devis',
          icon: Icons.receipt_long_outlined,
          color: _Palette.primary,
          onTap: () => Get.toNamed(Routes.devisReview, arguments: devis!.id),
        ),
      );
    }

    if ((mission.status == 'financee' || mission.status == 'en_cours') &&
        mission.status != 'litige') {
      if (nextSubmittedJalon != null) {
        return Builder(
          builder: (context) => _ActionRow(
            primary: _ActionButtonConfig(
              label: 'Valider le jalon (OTP)',
              icon: Icons.check_circle_outline,
              color: _Palette.success,
              onTap: () => showOtpValidationDialog(context, nextSubmittedJalon!),
            ),
            secondary: _ActionButtonConfig(
              label: 'Signaler',
              icon: Icons.warning_amber_outlined,
              color: _Palette.danger,
              filled: false,
              onTap: () => Get.toNamed(Routes.litige, arguments: mission),
            ),
          ),
        );
      }

      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Signaler un litige',
          icon: Icons.warning_amber_outlined,
          color: _Palette.danger,
          onTap: () => Get.toNamed(Routes.litige, arguments: mission),
        ),
      );
    }

    if (mission.status == 'litige') {
      return _ActionRow(
        primary: _ActionButtonConfig(
          label: 'Litige en cours',
          icon: Icons.gavel_outlined,
          color: _Palette.warning,
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
              unawaited(Get.find<MissionsController>().loadMission(mission.id, forceRefresh: true));
            }
          },
        ),
        secondary: _ActionButtonConfig(
          label: 'Signaler',
          icon: Icons.warning_amber_outlined,
          color: _Palette.danger,
          filled: false,
          onTap: () => Get.toNamed(Routes.litige, arguments: mission),
        ),
      );
    }

    return const SizedBox.shrink();
  }
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.primary,
    this.secondary,
  });

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

class _SectionContainer extends StatelessWidget {
  const _SectionContainer({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.label,
    required this.value,
  });

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                color: _Palette.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 13,
              color: _Palette.ink,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.color,
  });

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }
}

class _PendingAcceptanceCard extends StatelessWidget {
  final MissionModel mission;
  final MissionsController controller;

  const _PendingAcceptanceCard({
    required this.mission,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.pending_actions_rounded, color: _Palette.warning, size: 24),
              SizedBox(width: 10),
              Text(
                'Réponse requise',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: _Palette.ink,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Ce client vous a choisi pour réaliser ses travaux. Acceptez-vous la demande pour débloquer la création du devis ?',
            style: TextStyle(fontSize: 13, color: _Palette.muted, height: 1.4),
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
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    elevation: 0,
                  ),
                  icon: const Icon(Icons.check_circle_outline, size: 18),
                  label: const Text('ACCEPTER', style: TextStyle(fontWeight: FontWeight.bold)),
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
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.cancel_outlined, size: 18),
                  label: const Text('REFUSER', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MissionEvaluationsSection extends StatefulWidget {
  const _MissionEvaluationsSection({
    required this.mission,
    required this.onEvaluated,
  });

  final MissionModel mission;
  final VoidCallback onEvaluated;

  @override
  State<_MissionEvaluationsSection> createState() => _MissionEvaluationsSectionState();
}

class _MissionEvaluationsSectionState extends State<_MissionEvaluationsSection> {
  final EvaluationRepository _evaluationRepo = EvaluationRepository();
  bool _isLoading = true;
  List<Map<String, dynamic>> _actors = [];

  @override
  void initState() {
    super.initState();
    _loadActors();
  }

  Future<void> _loadActors() async {
    setState(() => _isLoading = true);
    final data = await _evaluationRepo.getMissionActors(widget.mission.id);
    if (mounted) {
      if (data != null && data['actors'] is List && (data['actors'] as List).isNotEmpty) {
        setState(() {
          _actors = List<Map<String, dynamic>>.from(data['actors']);
          _isLoading = false;
        });
      } else {
        // Fallback default artisan actor
        setState(() {
          _actors = [
            {
              'id': widget.mission.artisanId,
              'name': widget.mission.artisanName ?? 'Artisan',
              'role': 'artisan',
              'role_label': 'Artisan',
              'subtitle': 'Artisan principal de la mission',
              'is_evaluated': false,
              'evaluation': null,
            }
          ];
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return _SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.star_rounded, size: 20, color: Color(0xFFD97706)),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Évaluations des intervenants',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: _Palette.ink,
                      ),
                    ),
                    Text(
                      'Partagez votre avis pour valoriser le travail bien fait',
                      style: TextStyle(
                        fontSize: 12,
                        color: _Palette.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_isLoading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(20),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_actors.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: _Palette.subtle),
              ),
              child: const Text(
                'Aucun intervenant à évaluer.',
                style: TextStyle(color: _Palette.muted, fontSize: 13),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _actors.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final actor = _actors[index];
                return _ActorEvaluationCard(
                  actor: actor,
                  missionId: widget.mission.id,
                  onRate: () async {
                    final res = await Get.toNamed(
                      Routes.rating,
                      arguments: <String, dynamic>{
                        'missionId': widget.mission.id,
                        'evalueId': actor['id'],
                        'targetName': actor['name'] ?? 'Intervenant',
                        'targetRole': actor['role'] ?? 'artisan',
                        'targetSubtitle': actor['subtitle'] ?? '',
                      },
                    );
                    if (res == true) {
                      unawaited(_loadActors());
                      widget.onEvaluated();
                    }
                  },
                );
              },
            ),
        ],
      ),
    );
  }
}

class _ActorEvaluationCard extends StatelessWidget {
  const _ActorEvaluationCard({
    required this.actor,
    required this.missionId,
    required this.onRate,
  });

  final Map<String, dynamic> actor;
  final int missionId;
  final VoidCallback onRate;

  @override
  Widget build(BuildContext context) {
    final bool isEvaluated = actor['is_evaluated'] == true;
    final String role = actor['role'] ?? 'artisan';
    final String name = actor['name'] ?? 'Intervenant';
    final String roleLabel = actor['role_label'] ??
        (role == 'artisan'
            ? 'Artisan'
            : role == 'livreur'
                ? 'Livreur'
                : 'Fournisseur');
    final String subtitle = actor['subtitle'] ?? '';
    final Map<String, dynamic>? eval = actor['evaluation'] != null
        ? Map<String, dynamic>.from(actor['evaluation'])
        : null;
    final int note = eval?['note'] as int? ?? 0;
    final String? comment = eval?['commentaire'] as String?;

    final Color roleColor = role == 'artisan'
        ? const Color(0xFFE67E22)
        : role == 'livreur'
            ? const Color(0xFFF1C40F)
            : const Color(0xFF10B981);

    final IconData roleIcon = role == 'artisan'
        ? Icons.handyman_rounded
        : role == 'livreur'
            ? Icons.local_shipping_rounded
            : Icons.storefront_rounded;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isEvaluated ? const Color(0xFFBBF7D0) : _Palette.subtle,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: roleColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(roleIcon, size: 22, color: roleColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            name,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: _Palette.ink,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: roleColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            roleLabel,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: roleColor,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: _Palette.muted,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (isEvaluated) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFDCFCE7)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.check_circle, size: 16, color: Color(0xFF16A34A)),
                  const SizedBox(width: 6),
                  const Text(
                    'Avis enregistré : ',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF15803D),
                    ),
                  ),
                  Row(
                    children: List.generate(5, (i) {
                      return Icon(
                        i < note ? Icons.star_rounded : Icons.star_outline_rounded,
                        size: 16,
                        color: const Color(0xFFF59E0B),
                      );
                    }),
                  ),
                  const Spacer(),
                  Text(
                    '$note/5',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFD97706),
                    ),
                  ),
                ],
              ),
            ),
            if (comment != null && comment.isNotEmpty) ...[
              const SizedBox(height: 6),
              Padding(
                padding: const EdgeInsets.only(left: 4),
                child: Text(
                  '« $comment »',
                  style: const TextStyle(
                    fontSize: 12,
                    fontStyle: FontStyle.italic,
                    color: _Palette.muted,
                  ),
                ),
              ),
            ],
          ] else ...[
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: onRate,
                icon: const Icon(Icons.star_outline_rounded, size: 16),
                label: Text(
                  role == 'artisan'
                      ? 'Noter l\'artisan'
                      : role == 'livreur'
                          ? 'Noter le livreur'
                          : 'Noter la quincaillerie',
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: roleColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  elevation: 0,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

