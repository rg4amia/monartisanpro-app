import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/jalon_model.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/missions_controller.dart';
import '../controllers/devis_controller.dart';
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
                const SizedBox(height: 16),
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
        ],
      ),
    );
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

    return _SectionContainer(
      child: Row(
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
                  subtitle,
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
          if (role == 'artisan' && jalon.isPending) ...[
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
          ],
          if (role == 'client' && (jalon.isSubmitted || jalon.isPending)) ...[
            const SizedBox(height: 12),
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
                  child: ElevatedButton.icon(
                    onPressed: () => _showOtpValidationDialog(context, jalon),
                    icon: const Icon(Icons.check_circle_outline, size: 18),
                    label: const Text('Valider OTP'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _Palette.success,
                      foregroundColor: Colors.white,
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
            builder: (context, setState) => Column(
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
                RadioListTile<String>(
                  title: const Text('Wave CI', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  secondary: const Icon(Icons.waves, color: _Palette.primary),
                  value: 'wave',
                  groupValue: selectedProvider,
                  onChanged: (v) => setState(() => selectedProvider = v!),
                  activeColor: _Palette.primary,
                ),
                RadioListTile<String>(
                  title: const Text('Orange Money CI', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  secondary: const Icon(Icons.phone_android, color: _Palette.primary),
                  value: 'orange_money',
                  groupValue: selectedProvider,
                  onChanged: (v) => setState(() => selectedProvider = v!),
                  activeColor: _Palette.primary,
                ),
                RadioListTile<String>(
                  title: const Text('Virement Bancaire', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  secondary: const Icon(Icons.account_balance, color: _Palette.primary),
                  value: 'virement_bancaire',
                  groupValue: selectedProvider,
                  onChanged: (v) => setState(() => selectedProvider = v!),
                  activeColor: _Palette.primary,
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
                            Get.find<MissionsController>().loadMission(jalon.missionId, forceRefresh: true);
                            _showOtpValidationDialog(context, jalon);
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

  void _showOtpValidationDialog(BuildContext context, JalonModel jalon) {
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
                        final otp = otpController.text.trim();
                        if (otp.length != 4) {
                          Get.snackbar('Code invalide', 'Le code doit contenir 4 chiffres');
                          return;
                        }
                        Get.back();
                        final success = await controller.validateOtp(jalon.id, otp);
                        if (success) {
                          controller.loadMission(jalon.missionId, forceRefresh: true);
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

class _BottomActions extends StatelessWidget {
  const _BottomActions({
    required this.role,
    required this.mission,
    required this.devis,
    required this.nextPendingJalon,
    required this.onStartMission,
  });

  final String role;
  final MissionModel mission;
  final DevisModel? devis;
  final JalonModel? nextPendingJalon;
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
    if (mission.status == 'en_attente' && devis == null) {
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
