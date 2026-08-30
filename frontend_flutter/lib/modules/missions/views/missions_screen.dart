import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/missions_controller.dart';

abstract class _Palette {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.secondary;
  static const success = AppColors.success;
  static const warning = AppColors.accent;
  static const danger = AppColors.danger;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
}

class MissionsScreen extends StatelessWidget {
  const MissionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MissionsController>();
    final role = StorageService.getRole() ?? 'client';

    return Scaffold(
      backgroundColor: _Palette.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(role: role),
            _FilterTabs(controller: controller, role: role),
            Expanded(
              child: Obx(() {
                if (controller.isLoading.value && controller.missions.isEmpty) {
                  return Padding(
                    padding: const EdgeInsets.all(20),
                    child: LoadingShimmer.list(),
                  );
                }

                if (controller.missions.isEmpty) {
                  return _EmptyState(
                    role: role,
                    onRefresh: controller.refresh,
                  );
                }

                return RefreshIndicator(
                  onRefresh: controller.refresh,
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    itemCount: controller.missions.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 14),
                    itemBuilder: (_, index) => _MissionCard(
                      role: role,
                      mission: controller.missions[index],
                    ),
                  ),
                );
              }),
            ),
          ],
        ),
      ),
      floatingActionButton: role == 'client'
          ? FloatingActionButton.extended(
              onPressed: () => Get.toNamed(Routes.missionRequest),
              backgroundColor: _Palette.primary,
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text(
                'Nouvelle mission',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          : null,
    );
  }
}

class _AppBar extends StatelessWidget {
  const _AppBar({required this.role});

  final String role;

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.of(context).canPop();
    final title = switch (role) {
      'artisan' => 'Mes chantiers',
      'fournisseur' => 'Transactions',
      _ => 'Mes missions',
    };

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      child: Row(
        children: [
          if (canPop)
            GestureDetector(
              onTap: () => Get.back(),
              child: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: _Palette.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _Palette.subtle),
                ),
                child: const Icon(Icons.arrow_back, size: 20),
              ),
            )
          else
            const SizedBox(width: 40),
          Expanded(
            child: Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _Palette.ink,
              ),
            ),
          ),
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _Palette.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _Palette.subtle),
            ),
            child: const Icon(Icons.tune, size: 20, color: _Palette.muted),
          ),
        ],
      ),
    );
  }
}

class _FilterTabs extends StatelessWidget {
  const _FilterTabs({
    required this.controller,
    required this.role,
  });

  final MissionsController controller;
  final String role;

  @override
  Widget build(BuildContext context) {
    final tabs = _tabsForRole(role);

    return Container(
      height: 50,
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Obx(() {
        final selectedFilter = controller.selectedFilter.value;
        return ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: tabs.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (_, index) {
            final (value, label) = tabs[index];
            final selected = selectedFilter == value;

            return GestureDetector(
              onTap: () => controller.applyFilter(value),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: selected ? _Palette.primary : _Palette.surface,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: selected ? _Palette.primary : _Palette.subtle,
                  ),
                ),
                child: Center(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: selected ? Colors.white : _Palette.muted,
                    ),
                  ),
                ),
              ),
            );
          },
        );
      }),
    );
  }

  List<(String, String)> _tabsForRole(String role) {
    if (role == 'artisan') {
      return const [
        ('all', 'Toutes'),
        ('en_attente', 'À deviser'),
        ('financee', 'Financées'),
        ('en_cours', 'En cours'),
        ('terminee', 'Terminées'),
        ('litige', 'Litiges'),
      ];
    }

    if (role == 'fournisseur') {
      return const [
        ('all', 'Tout'),
        ('validee', 'Valide'),
        ('en_attente', 'En attente'),
        ('payee', 'Paye'),
      ];
    }

    return const [
      ('all', 'Tout'),
      ('en_cours', 'En cours'),
      ('terminee', 'Terminees'),
      ('litige', 'Litiges'),
    ];
  }
}

class _MissionCard extends StatelessWidget {
  const _MissionCard({
    required this.role,
    required this.mission,
  });

  final String role;
  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final action = _resolveAction();
    final counterpartName = role == 'artisan'
        ? (mission.clientName ?? 'Client')
        : (mission.artisanName ?? 'Artisan');
    final counterpartLabel = role == 'artisan' ? 'Client' : 'Artisan';
    final accent = _statusColor(mission.status);

    return GestureDetector(
      onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
      child: Container(
        decoration: BoxDecoration(
          color: _Palette.surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: _Palette.subtle),
          boxShadow: [
            BoxShadow(
              color: _Palette.ink.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _Pill(
                          label: Formatters.missionStatus(mission.status),
                          color: accent,
                        ),
                        if (mission.needsReferent)
                          const _Pill(
                            label: 'Referent requis',
                            color: _Palette.warning,
                          ),
                      ],
                    ),
                  ),
                  Text(
                    '#${mission.id}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: _Palette.muted,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                mission.description ?? 'Mission',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: _Palette.ink,
                ),
              ),
              const SizedBox(height: 12),
              _MetaRow(
                icon: Icons.person_outline,
                label: counterpartLabel,
                value: counterpartName,
              ),
              const SizedBox(height: 8),
              _MetaRow(
                icon: Icons.place_outlined,
                label: 'Lieu',
                value: mission.location ?? 'Adresse non renseignee',
              ),
              const SizedBox(height: 8),
              _MetaRow(
                icon: Icons.account_balance_wallet_outlined,
                label: 'Montant',
                value: Formatters.fcfa(mission.montantTotal),
              ),
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    Icon(action.icon, size: 18, color: action.color),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        action.subtitle,
                        style: TextStyle(
                          fontSize: 12,
                          color: action.color,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.toNamed(Routes.missionTracking,
                          arguments: mission),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _Palette.primary,
                        side: BorderSide(
                          color: _Palette.primary.withValues(alpha: 0.24),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Details'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: action.onTap,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: action.color,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: Text(action.label),
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

  _MissionAction _resolveAction() {
    if (role == 'artisan') {
      switch (mission.status) {
        case 'en_attente':
          if (mission.hasDevis) {
            return _MissionAction(
              label: 'Devis envoyé',
              subtitle: 'Votre devis a été soumis. En attente de la décision du client.',
              color: _Palette.muted,
              icon: Icons.hourglass_empty_outlined,
              onTap: null,
            );
          }
          return _MissionAction(
            label: 'Faire devis',
            subtitle: 'Chiffrage attendu pour debloquer la suite du workflow.',
            color: _Palette.warning,
            icon: Icons.receipt_long_outlined,
            onTap: () => Get.toNamed(Routes.devisCreation, arguments: mission),
          );
        case 'financee':
          return _MissionAction(
            label: 'J-Code',
            subtitle: 'Mission financee. Lancez la commande materiaux.',
            color: _Palette.primary,
            icon: Icons.qr_code_2_outlined,
            onTap: () => Get.toNamed(
              Routes.jcode,
              arguments: <String, dynamic>{'missionId': mission.id},
            ),
          );
        case 'en_cours':
          return _MissionAction(
            label: 'Suivre',
            subtitle: 'Soumettez vos jalons et vos preuves photo.',
            color: _Palette.success,
            icon: Icons.construction_outlined,
            onTap: () =>
                Get.toNamed(Routes.missionTracking, arguments: mission),
          );
        case 'litige':
          return _MissionAction(
            label: 'Litige',
            subtitle: 'Des preuves et un suivi sont requis sur ce dossier.',
            color: _Palette.danger,
            icon: Icons.gpp_bad_outlined,
            onTap: () =>
                Get.toNamed(Routes.missionTracking, arguments: mission),
          );
      }
    }

    if (mission.status == 'terminee' ||
        mission.status == 'completed' ||
        mission.rawStatus == 'terminee' ||
        mission.rawStatus == 'completed' ||
        mission.status.toLowerCase().contains('termin')) {
      return _MissionAction(
        label: '⭐ Noter l\'artisan',
        subtitle: 'Travaux terminés. Évaluez la prestation de votre artisan.',
        color: const Color(0xFFF59E0B),
        icon: Icons.star_rounded,
        onTap: () async {
          final result = await Get.toNamed(
            Routes.rating,
            arguments: <String, dynamic>{
              'missionId': mission.id,
              'evalueId': mission.artisanId,
              'targetName': mission.artisanName ?? 'Artisan',
              'targetRole': 'artisan',
              'targetSubtitle': mission.description ?? 'Mission #${mission.id}',
            },
          );
          if (result == true) {
            Get.find<MissionsController>().loadMissions();
          }
        },
      );
    }

    return _MissionAction(
      label: 'Ouvrir',
      subtitle: 'Consultez le detail complet et l\'avancement de la mission.',
      color: _Palette.primary,
      icon: Icons.open_in_new,
      onTap: () => Get.toNamed(Routes.missionTracking, arguments: mission),
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

class _MetaRow extends StatelessWidget {
  const _MetaRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 15, color: _Palette.muted),
        const SizedBox(width: 8),
        Text(
          '$label : ',
          style: const TextStyle(
            fontSize: 12,
            color: _Palette.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        Expanded(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              color: _Palette.ink,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
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
        color: color.withValues(alpha: 0.1),
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

class _MissionAction {
  const _MissionAction({
    required this.label,
    required this.subtitle,
    required this.color,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final String subtitle;
  final Color color;
  final IconData icon;
  final VoidCallback? onTap;
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.role,
    required this.onRefresh,
  });

  final String role;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final title = switch (role) {
      'artisan' => 'Aucun chantier a afficher',
      'fournisseur' => 'Aucune transaction disponible',
      _ => 'Aucune mission trouvee',
    };
    final subtitle = switch (role) {
      'artisan' =>
        'Les demandes, missions financees et chantiers en cours apparaitront ici.',
      'fournisseur' =>
        'Les commandes et paiements fournisseurs apparaitront ici.',
      _ => 'Creez une mission ou actualisez la liste pour reessayer.',
    };

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(
                color: _Palette.primaryLight,
                borderRadius: BorderRadius.circular(24),
              ),
              child: const Icon(
                Icons.work_outline,
                size: 40,
                color: _Palette.primary,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _Palette.ink,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                height: 1.4,
                color: _Palette.muted,
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onRefresh,
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Actualiser'),
              style: ElevatedButton.styleFrom(
                backgroundColor: _Palette.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
