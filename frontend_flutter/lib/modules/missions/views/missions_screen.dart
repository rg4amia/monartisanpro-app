import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/storage/storage_service.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../../../shared/widgets/mission_card.dart';
import '../controllers/missions_controller.dart';

class MissionsScreen extends StatelessWidget {
  const MissionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MissionsController>();
    final role = StorageService.getRole() ?? 'client';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title:
            Text(role == 'fournisseur' ? 'Transactions' : 'Mes missions'),
        automaticallyImplyLeading: false,
      ),
      body: Column(
        children: [
          // Filter chips
          _FilterBar(controller: c, role: role),
          // List
          Expanded(
            child: Obx(() {
              if (c.isLoading.value) {
                return Padding(
                  padding: const EdgeInsets.all(20),
                  child: LoadingShimmer.list(),
                );
              }
              if (c.missions.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.assignment_outlined,
                          size: 60,
                          color: AppColors.textMuted.withValues(alpha: 0.4)),
                      const SizedBox(height: 12),
                      const Text('Aucune mission trouvée.',
                          style: TextStyle(color: AppColors.textMuted)),
                    ],
                  ),
                );
              }
              return RefreshIndicator(
                onRefresh: () =>
                    c.loadMissions(status: c.selectedFilter.value),
                child: ListView.builder(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 20, vertical: 8),
                  itemCount: c.missions.length,
                  itemBuilder: (_, i) => MissionCard(
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
      floatingActionButton: role == 'client'
          ? FloatingActionButton.extended(
              onPressed: () => Get.toNamed(Routes.missionRequest),
              backgroundColor: AppColors.primary,
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text('Nouvelle mission',
                  style: TextStyle(color: Colors.white)),
            )
          : null,
    );
  }
}

class _FilterBar extends StatelessWidget {
  final MissionsController controller;
  final String role;

  const _FilterBar({required this.controller, required this.role});

  @override
  Widget build(BuildContext context) {
    final filters = role == 'fournisseur'
        ? const [
            ('all', 'Toutes'),
            ('validee', 'Validées'),
            ('en_attente', 'En attente'),
            ('payee', 'Payées'),
          ]
        : const [
            ('all', 'Toutes'),
            ('en_cours', 'En cours'),
            ('en_attente', 'Devis'),
            ('terminee', 'Terminées'),
          ];

    return Container(
      height: 48,
      color: AppColors.card,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: filters.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final (value, label) = filters[i];
          return Obx(() {
            final selected = controller.selectedFilter.value == value;
            return GestureDetector(
              onTap: () => controller.applyFilter(value),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.symmetric(
                    horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  color: selected ? AppColors.primary : AppColors.background,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color:
                        selected ? AppColors.primary : AppColors.border,
                  ),
                ),
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: selected
                        ? Colors.white
                        : AppColors.textSecondary,
                  ),
                ),
              ),
            );
          });
        },
      ),
    );
  }
}
