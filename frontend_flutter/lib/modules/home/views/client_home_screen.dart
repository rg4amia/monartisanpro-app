import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/communication_banner.dart';
import '../controllers/home_controller.dart';
import '../widgets/client_home/artisans_list.dart';
import '../widgets/client_home/categories_grid.dart';
import '../widgets/client_home/client_dashboard_view.dart';
import '../widgets/client_home/client_hero.dart';
import '../widgets/client_home/client_stats.dart';
import '../widgets/client_home/info_pill.dart';
import '../widgets/client_home/mission_search_card.dart';
import '../widgets/client_home/night_mode_banner.dart';
import '../widgets/client_home/quick_request_card.dart';
import '../widgets/client_home/search_mode_toggle.dart';
import '../widgets/client_home/section_header.dart';
import '../widgets/client_home/supplier_banner.dart';
import '../widgets/client_home/tab_button.dart';

class ClientHomeScreen extends StatelessWidget {
  const ClientHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: controller.refresh,
          color: AppColors.client,
          child: Obx(() {
            return CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(child: ClientHero(controller: controller)),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
                    child: _DashboardTabBar(controller: controller),
                  ),
                ),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
                    child: AnimatedCrossFade(
                      duration: const Duration(milliseconds: 300),
                      firstCurve: Curves.easeIn,
                      secondCurve: Curves.easeIn,
                      crossFadeState: controller.dashboardTab.value == 0
                          ? CrossFadeState.showFirst
                          : CrossFadeState.showSecond,
                      firstChild: _ExplorationView(controller: controller),
                      secondChild: ClientDashboardView(controller: controller),
                    ),
                  ),
                ),
              ],
            );
          }),
        ),
      ),
    );
  }
}

class _DashboardTabBar extends StatelessWidget {
  const _DashboardTabBar({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: TabButton(
              label: 'Exploration',
              icon: Icons.explore_outlined,
              isActive: controller.dashboardTab.value == 0,
              onTap: () => controller.dashboardTab.value = 0,
            ),
          ),
          Expanded(
            child: TabButton(
              label: 'Tableau de Bord',
              icon: Icons.dashboard_customize_outlined,
              isActive: controller.dashboardTab.value == 1,
              onTap: () => controller.dashboardTab.value = 1,
            ),
          ),
        ],
      ),
    );
  }
}

class _ExplorationView extends StatelessWidget {
  const _ExplorationView({required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const MissionSearchCard(),
        CommunicationBanner(announcements: controller.announcements),
        LeSaviezVousCarousel(tips: controller.tips),
        if (controller.isNightModeActive) ...[
          const SizedBox(height: 16),
          const NightModeBanner(),
        ],
        const SizedBox(height: 20),
        ClientStats(controller: controller),
        const SizedBox(height: 24),
        SectionHeader(
          title: 'Demande rapide',
          trailing: TextButton(
            onPressed: () => Get.toNamed(Routes.services),
            child: const Text('Voir tout'),
          ),
        ),
        const SizedBox(height: 12),
        const QuickRequestCard(),
        const SizedBox(height: 24),
        const SectionHeader(title: 'Catégories populaires'),
        const SizedBox(height: 12),
        const CategoriesGrid(),
        const SizedBox(height: 24),
        const SectionHeader(title: 'Matériaux & Quincailleries'),
        const SizedBox(height: 12),
        const SupplierBanner(),
        const SizedBox(height: 24),
        SectionHeader(
          title: 'Artisans à proximité',
          trailing: InfoPill(
            icon: Icons.location_on_outlined,
            label: '${controller.displayedNearbyArtisansCount} autour de vous',
            color: AppColors.client,
            background: AppColors.clientSoft,
          ),
        ),
        const SizedBox(height: 14),
        SearchModeToggle(controller: controller),
        const SizedBox(height: 16),
        ArtisansList(controller: controller),
      ],
    );
  }
}
