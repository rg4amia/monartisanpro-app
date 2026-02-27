import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/artisan_card.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/home_controller.dart';

class ClientHomeScreen extends StatelessWidget {
  const ClientHomeScreen({super.key});

  static const _categories = [
    {'label': 'Plomberie', 'icon': Icons.water_drop_outlined, 'color': AppColors.info},
    {'label': 'Électricité', 'icon': Icons.bolt_outlined, 'color': AppColors.warning},
    {'label': 'Maçonnerie', 'icon': Icons.construction_outlined, 'color': AppColors.textSecondary},
    {'label': 'Menuiserie', 'icon': Icons.chair_outlined, 'color': AppColors.accent},
    {'label': 'Peinture', 'icon': Icons.format_paint_outlined, 'color': AppColors.success},
    {'label': 'Carrelage', 'icon': Icons.grid_on_outlined, 'color': AppColors.primary},
  ];

  @override
  Widget build(BuildContext context) {
    final c = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(c),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 20),
                  _SearchBar(),
                  const SizedBox(height: 24),
                  const Text('Catégories',
                      style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary)),
                  const SizedBox(height: 14),
                  _CategoriesGrid(),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Artisans près de vous',
                          style: TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary)),
                      Obx(() => Text('${c.artisans.length} trouvés',
                          style: const TextStyle(
                              fontSize: 13, color: AppColors.textSecondary))),
                    ],
                  ),
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
                  child: LoadingShimmer.list(count: 4),
                ),
              );
            }
            if (c.artisans.isEmpty) {
              return const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: Center(
                    child: Text('Aucun artisan trouvé à proximité.',
                        style: TextStyle(color: AppColors.textMuted)),
                  ),
                ),
              );
            }
            return SliverList(
              delegate: SliverChildBuilderDelegate(
                (_, i) => Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: ArtisanCard(
                    artisan: c.artisans[i],
                    onTap: () => Get.toNamed(
                      Routes.artisanProfile,
                      arguments: c.artisans[i],
                    ),
                  ),
                ),
                childCount: c.artisans.length,
              ),
            );
          }),
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    );
  }

  SliverAppBar _buildAppBar(HomeController c) {
    return SliverAppBar(
      expandedHeight: 120,
      floating: true,
      snap: true,
      backgroundColor: AppColors.primary,
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          color: AppColors.primary,
          padding: const EdgeInsets.fromLTRB(20, 50, 20, 16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Column(
                mainAxisAlignment: MainAxisAlignment.end,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Bonjour 👋',
                      style: TextStyle(
                          color: Colors.white70, fontSize: 14)),
                  Obx(() => Text(
                        c.userName.value.isNotEmpty
                            ? c.userName.value
                            : 'Client',
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
                    color: Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.notifications_outlined,
                      color: Colors.white, size: 22),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SearchBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Get.toNamed(Routes.missionRequest),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
          boxShadow: const [BoxShadow(color: AppColors.shadowColor, blurRadius: 8)],
        ),
        child: const Row(
          children: [
            Icon(Icons.search, color: AppColors.textMuted, size: 20),
            SizedBox(width: 10),
            Text('Décrire vos travaux...',
                style: TextStyle(color: AppColors.textMuted, fontSize: 15)),
          ],
        ),
      ),
    );
  }
}

class _CategoriesGrid extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    const categories = ClientHomeScreen._categories;
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        childAspectRatio: 1.1,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
      ),
      itemCount: categories.length,
      itemBuilder: (_, i) {
        final cat = categories[i];
        final color = cat['color'] as Color;
        return GestureDetector(
          onTap: () => Get.toNamed(Routes.missionRequest,
              arguments: {'category': cat['label']}),
          child: Container(
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: color.withValues(alpha: 0.2)),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(cat['icon'] as IconData, color: color, size: 26),
                const SizedBox(height: 6),
                Text(cat['label'] as String,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: color)),
              ],
            ),
          ),
        );
      },
    );
  }
}
