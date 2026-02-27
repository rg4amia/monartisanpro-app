import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../../../shared/widgets/mission_card.dart';
import '../controllers/home_controller.dart';

class ArtisanHomeScreen extends StatelessWidget {
  const ArtisanHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: c.refresh,
        child: CustomScrollView(
          slivers: [
            _buildAppBar(c),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Stats row
                    Obx(() => Row(
                          children: [
                            _StatCard(
                              label: 'Missions actives',
                              value: '${c.activeMissions.length}',
                              icon: Icons.assignment_outlined,
                              color: AppColors.primary,
                            ),
                            const SizedBox(width: 12),
                            _StatCard(
                              label: 'Wallet MO',
                              value: Formatters.fcfa(c.walletMo.value),
                              icon: Icons.account_balance_wallet_outlined,
                              color: AppColors.success,
                            ),
                          ],
                        )),
                    const SizedBox(height: 20),
                    // Quick actions
                    const Text('Actions rapides',
                        style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary)),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        _QuickAction(
                          label: 'J-Code',
                          icon: Icons.qr_code,
                          color: AppColors.accent,
                          onTap: () => Get.toNamed(Routes.jcode),
                        ),
                        const SizedBox(width: 12),
                        _QuickAction(
                          label: 'Score',
                          icon: Icons.star_outline,
                          color: AppColors.warning,
                          onTap: () => Get.toNamed(Routes.score),
                        ),
                        const SizedBox(width: 12),
                        _QuickAction(
                          label: 'Devis',
                          icon: Icons.description_outlined,
                          color: AppColors.primary,
                          onTap: () => Get.toNamed(Routes.quoteBuilder),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    const Text('Missions en cours',
                        style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary)),
                    const SizedBox(height: 10),
                  ],
                ),
              ),
            ),
            Obx(() {
              if (c.isLoading.value) {
                return SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: LoadingShimmer.list(),
                  ),
                );
              }
              if (c.activeMissions.isEmpty) {
                return const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(40),
                    child: Center(
                      child: Text('Aucune mission en cours.',
                          style: TextStyle(color: AppColors.textMuted)),
                    ),
                  ),
                );
              }
              return SliverList(
                delegate: SliverChildBuilderDelegate(
                  (_, i) => Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: MissionCard(
                      mission: c.activeMissions[i],
                      onTap: () => Get.toNamed(
                        Routes.missionTracking,
                        arguments: c.activeMissions[i],
                      ),
                    ),
                  ),
                  childCount: c.activeMissions.length,
                ),
              );
            }),
            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }

  SliverAppBar _buildAppBar(HomeController c) {
    return SliverAppBar(
      pinned: true,
      backgroundColor: AppColors.accent,
      expandedHeight: 110,
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          color: AppColors.accent,
          padding: const EdgeInsets.fromLTRB(20, 50, 20, 14),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Column(
                mainAxisAlignment: MainAxisAlignment.end,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Mon tableau de bord',
                      style: TextStyle(color: Colors.white70, fontSize: 13)),
                  Obx(() => Text(
                        c.userName.value.isNotEmpty
                            ? c.userName.value
                            : 'Artisan',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w700),
                      )),
                ],
              ),
              GestureDetector(
                onTap: () => Get.toNamed(Routes.notifications),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.notifications_outlined,
                      color: Colors.white),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 8),
            Text(value,
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: color)),
            Text(label,
                style: const TextStyle(
                    fontSize: 12, color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  const _QuickAction({
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: color.withValues(alpha: 0.2)),
          ),
          child: Column(
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(height: 6),
              Text(label,
                  style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: color)),
            ],
          ),
        ),
      ),
    );
  }
}
