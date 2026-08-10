import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../../main_tab/controllers/main_tab_controller.dart';
import '../../notifications/controllers/notifications_controller.dart';
import '../../../shared/widgets/communication_banner.dart';
import '../controllers/home_controller.dart';

abstract class _Palette {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
  static const primary = AppColors.primary;
  static const success = AppColors.success;
  static const warning = AppColors.accent;
  static const danger = AppColors.danger;
}

class ArtisanHomeScreen extends StatelessWidget {
  const ArtisanHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: _Palette.bg,
      body: RefreshIndicator(
        onRefresh: controller.refresh,
        color: _Palette.primary,
        child: Obx(() {
          // ── État d'erreur : afficher un widget de retry ──
          if (controller.hasError.value &&
              controller.artisanMissions.isEmpty &&
              !controller.isLoading.value) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [
                SizedBox(height: MediaQuery.of(context).size.height * 0.2),
                Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: _Palette.danger.withOpacity(0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.wifi_off_rounded,
                          size: 48,
                          color: _Palette.danger,
                        ),
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Impossible de charger les données',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: _Palette.ink,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Vérifiez votre connexion internet\net tirez vers le bas pour réessayer.',
                        style: TextStyle(
                          fontSize: 13,
                          color: _Palette.muted,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 24),
                      ElevatedButton.icon(
                        onPressed: () => controller.refresh(),
                        icon: const Icon(Icons.refresh_rounded, size: 18),
                        label: const Text('Réessayer'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _Palette.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 28,
                            vertical: 14,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 2,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }

          if (controller.isLoading.value &&
              controller.artisanMissions.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [LoadingShimmer.list(count: 4)],
            );
          }

          return CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _HeroHeader(controller: controller)),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      CommunicationBanner(announcements: controller.announcements),
                      LeSaviezVousCarousel(tips: controller.tips),
                      _StatGrid(controller: controller),
                      const SizedBox(height: 24),
                      _QuickActions(controller: controller, missions: controller.prioritizedArtisanMissions),
                      const SizedBox(height: 24),
                      _WorkflowReminder(controller: controller),
                      const SizedBox(height: 24),
                      _MissionQueue(controller: controller, missions: controller.prioritizedArtisanMissions),
                    ],
                  ),
                ),
              ),
            ],
          );
        }),
      ),
    );
  }
}

class _HeroHeader extends StatelessWidget {
  const _HeroHeader({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    final firstName = controller.userName.value.isNotEmpty
        ? controller.userName.value.split(' ').first
        : 'Artisan';

    return Container(
      padding: EdgeInsets.fromLTRB(
        20,
        MediaQuery.of(context).padding.top + 12,
        20,
        24,
      ),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primary,
            AppColors.accent,
          ],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(16),
                ),
                alignment: Alignment.center,
                child: const Icon(
                  Icons.handyman_rounded,
                  color: Colors.white,
                  size: 28,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Espace artisan',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.6,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Bon retour, $firstName',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              Stack(
                clipBehavior: Clip.none,
                children: [
                  GestureDetector(
                    onTap: () => Get.toNamed(Routes.notifications),
                    child: Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(
                        Icons.notifications_outlined,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  Obx(() {
                    final unread = Get.isRegistered<NotificationsController>()
                        ? Get.find<NotificationsController>().unreadCount
                        : 0;
                    if (unread == 0) return const SizedBox.shrink();
                    return Positioned(
                      right: -2,
                      top: -2,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.red,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(
                          minWidth: 16,
                          minHeight: 16,
                        ),
                        child: Center(
                          child: Text(
                            '$unread',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ],
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withValues(alpha: 0.14)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: GestureDetector(
                    onTap: () => Get.toNamed(Routes.wallet),
                    child: _HeroMetric(
                      label: 'Gains disponibles',
                      value: Formatters.fcfa(controller.walletMo.value),
                    ),
                  ),
                ),
                Container(
                  width: 1,
                  height: 42,
                  color: Colors.white.withValues(alpha: 0.14),
                ),
                Expanded(
                  child: _HeroMetric(
                    label: 'Demandes a traiter',
                    value: '${controller.pendingMissionCount}',
                    alignEnd: true,
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

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.label,
    required this.value,
    this.alignEnd = false,
  });

  final String label;
  final String value;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment:
          alignEnd ? CrossAxisAlignment.end : CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _StatGrid extends StatelessWidget {
  const _StatGrid({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Pipeline de mission',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: _Palette.ink,
          ),
        ),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          physics: const NeverScrollableScrollPhysics(),
          shrinkWrap: true,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.45,
          children: [
            _StatCard(
              label: 'A deviser',
              value: '${controller.pendingMissionCount}',
              subtitle: 'Demandes en attente',
              color: _Palette.warning,
              icon: Icons.receipt_long_outlined,
            ),
            _StatCard(
              label: 'Financees',
              value: '${controller.fundedMissionCount}',
              subtitle: 'Pretes pour J-Code',
              color: _Palette.primary,
              icon: Icons.account_balance_wallet_outlined,
            ),
            _StatCard(
              label: 'Travaux',
              value: '${controller.ongoingMissionCount}',
              subtitle: 'Chantiers actifs',
              color: _Palette.success,
              icon: Icons.construction_outlined,
            ),
            _StatCard(
              label: 'Litiges',
              value: '${controller.disputedMissionCount}',
              subtitle: 'A suivre',
              color: _Palette.danger,
              icon: Icons.gpp_bad_outlined,
            ),
          ],
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.subtitle,
    required this.color,
    required this.icon,
  });

  final String label;
  final String value;
  final String subtitle;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.subtle),
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
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color, size: 18),
              ),
              const Spacer(),
              Text(
                value,
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: color,
                ),
              ),
            ],
          ),
          const Spacer(),
          Text(
            label,
            style: const TextStyle(
              fontSize: 15,
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
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({
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
        const Text(
          'Actions rapides',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: _Palette.ink,
          ),
        ),
        const SizedBox(height: 12),
        _ActionTile(
          icon: Icons.receipt_long_outlined,
          color: _Palette.warning,
          title: pendingMission == null
              ? 'Consulter les demandes'
              : 'Creer le devis de la mission #${pendingMission.id}',
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
        const SizedBox(height: 10),
        _ActionTile(
          icon: Icons.qr_code_2_outlined,
          color: _Palette.primary,
          title: fundedMission == null
              ? 'Ouvrir le module J-Code'
              : 'Generer le J-Code de la mission #${fundedMission.id}',
          subtitle: fundedMission == null
              ? 'Preparer une commande materiaux ou consulter un code actif'
              : 'Mission financee, prete pour le retrait fournisseur',
          onTap: () => Get.toNamed(
            Routes.jcode,
            arguments: fundedMission == null
                ? null
                : <String, dynamic>{'missionId': fundedMission.id},
          ),
        ),
        const SizedBox(height: 10),
        _ActionTile(
          icon: Icons.construction_outlined,
          color: _Palette.success,
          title: ongoingMission == null
              ? 'Suivre mes chantiers'
              : 'Suivre le chantier #${ongoingMission.id}',
          subtitle: ongoingMission == null
              ? 'Voir les jalons, preuves photo et validations OTP'
              : 'Avancement des jalons et deboursement main d\'oeuvre',
          onTap: () {
            if (ongoingMission != null) {
              Get.toNamed(Routes.missionTracking, arguments: ongoingMission);
            } else {
              _openArtisanTab(1);
            }
          },
        ),
        const SizedBox(height: 10),
        _ActionTile(
          icon: Icons.storefront_outlined,
          color: _Palette.primary,
          title: 'Partenaires Fournisseurs',
          subtitle: 'Consulter la liste des quincailleries agréées et leurs catalogues',
          onTap: () => Get.toNamed(Routes.clientSuppliers),
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

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: _Palette.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color.withValues(alpha: 0.12)),
          boxShadow: [
            BoxShadow(
              color: _Palette.ink.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: _Palette.ink,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      height: 1.35,
                      color: _Palette.muted,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: _Palette.muted),
          ],
        ),
      ),
    );
  }
}

class _WorkflowReminder extends StatelessWidget {
  const _WorkflowReminder({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    final hasNightWork = controller.prioritizedArtisanMissions.any(
      (mission) => mission.urgency == 'urgent',
    );

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.artisanSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.primary.withValues(alpha: 0.14)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.route_outlined,
              color: _Palette.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Rappel du workflow',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  hasNightWork
                      ? 'Demande recue -> devis -> mission financee -> J-Code ou stock d\'urgence -> jalons -> paiement OTP.'
                      : 'Demande recue -> devis -> mission financee -> J-Code materiaux -> jalons -> cloture client.',
                  style: const TextStyle(
                    fontSize: 12,
                    height: 1.4,
                    color: _Palette.muted,
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

class _MissionQueue extends StatelessWidget {
  const _MissionQueue({
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
                  color: _Palette.ink,
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
              color: _Palette.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _Palette.subtle),
            ),
            child: const Column(
              children: [
                Icon(Icons.inbox_outlined, size: 36, color: _Palette.muted),
                SizedBox(height: 10),
                Text(
                  'Aucune mission pour le moment',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Les nouvelles demandes client apparaitront ici pour traitement.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    color: _Palette.muted,
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
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.subtle),
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
              Expanded(
                child: Text(
                  'Mission #${mission.id}',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
              ),
              _StatusPill(
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
              color: _Palette.ink,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 10,
            runSpacing: 8,
            children: [
              _MetaText(
                icon: Icons.person_outline,
                text: mission.clientName ?? 'Client',
              ),
              _MetaText(
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
              color: _Palette.muted,
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

  _MissionAction _resolveAction(MissionModel mission) {
    switch (mission.status) {
      case 'en_attente':
        if (mission.hasDevis) {
          return _MissionAction(
            label: 'Devis envoyé',
            subtitle: 'Votre devis a été soumis. En attente de la décision du client.',
            color: _Palette.muted,
            onTap: null,
          );
        }
        return _MissionAction(
          label: 'Devis',
          subtitle:
              'Votre chiffrage est attendu pour lancer le parcours client.',
          color: _Palette.warning,
          onTap: () => Get.toNamed(Routes.devisCreation, arguments: mission),
        );
      case 'financee':
        return _MissionAction(
          label: 'J-Code',
          subtitle: 'Mission financee. Preparez la commande materiaux.',
          color: _Palette.primary,
          onTap: () => Get.toNamed(
            Routes.jcode,
            arguments: <String, dynamic>{'missionId': mission.id},
          ),
        );
      case 'en_cours':
        return _MissionAction(
          label: 'Suivre',
          subtitle: 'Soumettez les jalons et les preuves photo geolocalisees.',
          color: _Palette.success,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
      case 'litige':
        return _MissionAction(
          label: 'Detail',
          subtitle: 'Un arbitrage est en cours sur ce chantier.',
          color: _Palette.danger,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
      default:
        return _MissionAction(
          label: 'Voir',
          subtitle: 'Consulter le recapitulatif complet de cette mission.',
          color: _Palette.muted,
          onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
        );
    }
  }
}

class _MissionAction {
  const _MissionAction({
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback? onTap;
}

class _MetaText extends StatelessWidget {
  const _MetaText({
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
        Icon(icon, size: 14, color: _Palette.muted),
        const SizedBox(width: 4),
        SizedBox(
          width: 130,
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              color: _Palette.muted,
            ),
          ),
        ),
      ],
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
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
