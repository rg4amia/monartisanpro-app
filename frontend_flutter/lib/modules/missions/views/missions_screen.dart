import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/missions_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const success = Color(0xFF10B981);
  static const successLight = Color(0xFFD1FAE5);
  static const warning = Color(0xFFF59E0B);
  static const warningLight = Color(0xFFFEF3C7);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

class MissionsScreen extends StatelessWidget {
  const MissionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MissionsController>();
    final role = StorageService.getRole() ?? 'client';

    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(role: role),
            _TabBar(controller: c, role: role),
            Expanded(
              child: Obx(() {
                if (c.isLoading.value && c.missions.isEmpty) {
                  return Padding(
                    padding: const EdgeInsets.all(20),
                    child: LoadingShimmer.list(),
                  );
                }

                if (c.missions.isEmpty) {
                  return _EmptyState(onRefresh: c.refresh);
                }

                return RefreshIndicator(
                  onRefresh: c.refresh,
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    itemCount: c.missions.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 16),
                    itemBuilder: (_, i) => _MissionCard(
                      mission: c.missions[i],
                      onTap: () => Get.toNamed(
                        Routes.missionTracking,
                        arguments: c.missions[i],
                      ),
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
              backgroundColor: _C.primary,
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text(
                'Nouvelle Mission',
                style:
                    TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
              ),
            )
          : null,
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  final String role;
  const _AppBar({required this.role});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          Text(
            role == 'fournisseur' ? 'Transactions' : 'Historique des missions',
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          GestureDetector(
            onTap: () {},
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.filter_list, size: 20),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Tab Bar ──────────────────────────────────────────────────────────────────
class _TabBar extends StatelessWidget {
  final MissionsController controller;
  final String role;

  const _TabBar({required this.controller, required this.role});

  @override
  Widget build(BuildContext context) {
    final tabs = role == 'fournisseur'
        ? const [
            ('all', 'Tout'),
            ('validee', 'Validé'),
            ('en_attente', 'En attente'),
            ('payee', 'Payé'),
          ]
        : const [
            ('all', 'Tout'),
            ('en_cours', 'En cours'),
            ('terminee', 'Terminé'),
          ];

    return Container(
      height: 48,
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: tabs.map((tab) {
          final (value, label) = tab;
          return Expanded(
            child: Obx(() {
              final selected = controller.selectedFilter.value == value;
              return GestureDetector(
                onTap: () => controller.applyFilter(value),
                child: Container(
                  decoration: BoxDecoration(
                    color: selected ? _C.primary : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                      color: selected ? Colors.white : _C.muted,
                    ),
                  ),
                ),
              );
            }),
          );
        }).toList(),
      ),
    );
  }
}

// ─── Mission Card ─────────────────────────────────────────────────────────────
class _MissionCard extends StatelessWidget {
  final dynamic mission;
  final VoidCallback onTap;

  const _MissionCard({required this.mission, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: _C.subtle),
          boxShadow: [
            BoxShadow(
              color: _C.ink.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image
            ClipRRect(
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(16)),
              child: Container(
                height: 160,
                width: double.infinity,
                color: _C.subtle,
                child: mission.category != null
                    ? _getCategoryImage(mission.category)
                    : const Icon(Icons.construction, size: 48, color: _C.muted),
              ),
            ),
            // Content
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _StatusBadge(status: mission.status),
                      _FinanceBadge(status: mission.status),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    mission.description ?? 'Mission #${mission.id}',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: _C.ink,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.person_outline,
                          size: 14, color: _C.muted),
                      const SizedBox(width: 4),
                      Text(
                        'Artisan: ${mission.artisanName ?? 'Inconnu'}',
                        style: const TextStyle(fontSize: 13, color: _C.muted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.calendar_today_outlined,
                          size: 14, color: _C.muted),
                      const SizedBox(width: 4),
                      Text(
                        'Lancée le ${_formatDate(mission.createdAt)}',
                        style: const TextStyle(fontSize: 13, color: _C.muted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Progression du projet',
                            style: TextStyle(fontSize: 12, color: _C.muted),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${_getProgress(mission.status)}%',
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: _C.primary,
                            ),
                          ),
                        ],
                      ),
                      SizedBox(
                        width: 120,
                        child: _ProgressBar(
                            progress: _getProgress(mission.status)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: onTap,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _C.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text(
                        'Voir détails',
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _getCategoryImage(String category) {
    final icons = {
      'Plomberie': Icons.plumbing,
      'Électricité': Icons.electric_bolt,
      'Maçonnerie': Icons.foundation,
      'Peinture': Icons.format_paint,
    };
    return Center(
      child: Icon(
        icons[category] ?? Icons.construction,
        size: 64,
        color: _C.muted,
      ),
    );
  }

  String _formatDate(String date) {
    try {
      final dt = DateTime.parse(date);
      return '${dt.month.toString().padLeft(2, '0')}/${dt.day.toString().padLeft(2, '0')}, ${dt.year}';
    } catch (_) {
      return date;
    }
  }

  int _getProgress(String status) {
    switch (status) {
      case 'en_attente':
        return 10;
      case 'financee':
        return 20;
      case 'en_cours':
        return 65;
      case 'terminee':
        return 100;
      default:
        return 0;
    }
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final config = _getStatusConfig(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: config['bg'] as Color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        config['label'] as String,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: config['color'] as Color,
          letterSpacing: 0.5,
        ),
      ),
    );
  }

  Map<String, dynamic> _getStatusConfig(String status) {
    switch (status) {
      case 'en_cours':
        return {
          'label': 'EN COURS',
          'color': _C.primary,
          'bg': _C.primaryLight,
        };
      case 'terminee':
        return {
          'label': 'TERMINÉ',
          'color': _C.success,
          'bg': _C.successLight,
        };
      case 'en_attente':
        return {
          'label': 'EN ATTENTE',
          'color': _C.warning,
          'bg': _C.warningLight,
        };
      default:
        return {
          'label': status.toUpperCase(),
          'color': _C.muted,
          'bg': _C.subtle,
        };
    }
  }
}

class _FinanceBadge extends StatelessWidget {
  final String status;
  const _FinanceBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    if (status != 'financee' && status != 'en_cours' && status != 'terminee') {
      return const SizedBox.shrink();
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: _C.successLight,
        borderRadius: BorderRadius.circular(8),
      ),
      child: const Text(
        'Financé',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: _C.success,
        ),
      ),
    );
  }
}

class _ProgressBar extends StatelessWidget {
  final int progress;
  const _ProgressBar({required this.progress});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: SizedBox(
            height: 8,
            child: LinearProgressIndicator(
              value: progress / 100,
              backgroundColor: _C.subtle,
              valueColor: const AlwaysStoppedAnimation(_C.primary),
            ),
          ),
        ),
      ],
    );
  }
}

// ─── Empty State ──────────────────────────────────────────────────────────────
class _EmptyState extends StatelessWidget {
  final Future<void> Function() onRefresh;
  const _EmptyState({required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        child: Container(
          height: MediaQuery.of(context).size.height * 0.6,
          alignment: Alignment.center,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: _C.primaryLight,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Icon(
                  Icons.assignment_outlined,
                  size: 40,
                  color: _C.primary,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Aucune mission trouvée',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Tirer pour rafraîchir',
                style: TextStyle(
                  fontSize: 13,
                  color: _C.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
