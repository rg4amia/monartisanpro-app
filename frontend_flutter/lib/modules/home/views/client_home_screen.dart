import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../shared/widgets/artisan_card.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/home_controller.dart';

// ─── Local design tokens (screen-scoped) ──────────────────────────────────────
abstract class _C {
  // Backgrounds
  static const bg = Color(0xFFFFF8F0); // warm cream
  static const surface = Colors.white;
  static const heroTop = Color(0xFF1C1614); // warm stone-black
  static const heroBtm = Color(0xFF2C1A0E); // deep warm brown

  // Brand
  static const orange = Color(0xFFE67E22); // matches AppColors.accent
  static const orangeLight = Color(0xFFFEEDD8);
  static const gold = Color(0xFFD97706); // amber
  static const goldLight = Color(0xFFFEF3C7);

  // Text
  static const ink = Color(0xFF1C1614);
  static const inkMid = Color(0xFF44403C);
  static const muted = Color(0xFF78716C);
  static const subtle = Color(0xFFD6D3D1);
}

// ─── Categories data ──────────────────────────────────────────────────────────
const _kCategories = [
  {
    'label': 'Plomberie',
    'icon': Icons.water_drop_outlined,
    'color': Color(0xFF0EA5E9)
  },
  {
    'label': 'Électricité',
    'icon': Icons.bolt_outlined,
    'color': Color(0xFFD97706)
  },
  {
    'label': 'Maçonnerie',
    'icon': Icons.construction_outlined,
    'color': Color(0xFF78716C)
  },
  {
    'label': 'Menuiserie',
    'icon': Icons.chair_outlined,
    'color': Color(0xFF8B5CF6)
  },
  {
    'label': 'Peinture',
    'icon': Icons.format_paint_outlined,
    'color': Color(0xFF059669)
  },
  {
    'label': 'Carrelage',
    'icon': Icons.grid_on_outlined,
    'color': Color(0xFFE67E22)
  },
  {
    'label': 'Jardinage',
    'icon': Icons.yard_outlined,
    'color': Color(0xFF15803D)
  },
  {
    'label': 'Climatisation',
    'icon': Icons.ac_unit_outlined,
    'color': Color(0xFF0891B2)
  },
];

// ─── Screen ───────────────────────────────────────────────────────────────────
class ClientHomeScreen extends StatelessWidget {
  const ClientHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: _C.bg,
      body: CustomScrollView(
        physics: const BouncingScrollPhysics(),
        slivers: [
          _HeroAppBar(controller: c),
          SliverToBoxAdapter(child: _SearchCTA()),
          SliverToBoxAdapter(child: _StatsRow(controller: c)),
          _SectionTitle(
            title: 'Nos services',
            subtitle: 'Choisissez une catégorie',
          ),
          SliverToBoxAdapter(child: _CategoriesScroll()),
          _SectionTitle(
            title: 'Artisans certifiés',
            subtitle: "Triés par Score N'Zassa",
            trailing: Obx(
              () => _CountPill(count: c.artisans.length),
            ),
          ),
          _ArtisansList(controller: c),
          const SliverToBoxAdapter(child: SizedBox(height: 32)),
        ],
      ),
    );
  }
}

// ─── Hero AppBar ──────────────────────────────────────────────────────────────
class _HeroAppBar extends StatelessWidget {
  final HomeController controller;
  const _HeroAppBar({required this.controller});

  String get _greeting {
    final h = DateTime.now().hour;
    if (h < 12) return 'Bonjour';
    if (h < 18) return 'Bon après-midi';
    return 'Bonsoir';
  }

  @override
  Widget build(BuildContext context) {
    return SliverAppBar(
      expandedHeight: 210,
      floating: false,
      pinned: true,
      backgroundColor: _C.heroTop,
      elevation: 0,
      flexibleSpace: FlexibleSpaceBar(
        background: Stack(
          fit: StackFit.expand,
          children: [
            // Gradient background
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [_C.heroTop, Color(0xFF3A2010), _C.heroBtm],
                  stops: [0.0, 0.55, 1.0],
                ),
              ),
            ),

            // Decorative circles
            Positioned(
              top: -50,
              right: -50,
              child:
                  _Circle(size: 200, color: _C.orange.withValues(alpha: 0.10)),
            ),
            Positioned(
              top: 30,
              right: 70,
              child: _Circle(size: 90, color: _C.gold.withValues(alpha: 0.08)),
            ),
            Positioned(
              bottom: -30,
              left: -20,
              child:
                  _Circle(size: 140, color: _C.orange.withValues(alpha: 0.06)),
            ),

            // Content
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 28),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Top bar: logo + notifications
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        // Brand
                        Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [_C.orange, Color(0xFFF97316)],
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(
                                Icons.handyman_rounded,
                                color: Colors.white,
                                size: 18,
                              ),
                            ),
                            const SizedBox(width: 10),
                            const Text(
                              'ProsArtisan',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ],
                        ),

                        // Notification button
                        GestureDetector(
                          onTap: () => Get.toNamed(Routes.notifications),
                          child: Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.10),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                color: Colors.white.withValues(alpha: 0.12),
                              ),
                            ),
                            child: Stack(
                              alignment: Alignment.center,
                              children: [
                                const Icon(
                                  Icons.notifications_outlined,
                                  color: Colors.white,
                                  size: 20,
                                ),
                                Positioned(
                                  top: 9,
                                  right: 9,
                                  child: Container(
                                    width: 7,
                                    height: 7,
                                    decoration: const BoxDecoration(
                                      color: _C.orange,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),

                    // Greeting block
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Status chip
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: _C.orange.withValues(alpha: 0.18),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: _C.orange.withValues(alpha: 0.30),
                              ),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  width: 6,
                                  height: 6,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFF4ADE80),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Text(
                                  _greeting,
                                  style: TextStyle(
                                    color: Colors.white.withValues(alpha: 0.90),
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    letterSpacing: 0.3,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 8),

                          // User name
                          Obx(
                            () => Text(
                              controller.userName.value.isNotEmpty
                                  ? controller.userName.value
                                  : 'Client',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 30,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -1.0,
                                height: 1.1,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Que cherchez-vous aujourd\'hui ?',
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.55),
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Circle extends StatelessWidget {
  final double size;
  final Color color;
  const _Circle({required this.size, required this.color});

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(shape: BoxShape.circle, color: color),
      );
}

// ─── Search CTA ───────────────────────────────────────────────────────────────
class _SearchCTA extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: GestureDetector(
        onTap: () => Get.toNamed(Routes.missionRequest),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: _C.surface,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: _C.ink.withValues(alpha: 0.08),
                blurRadius: 20,
                offset: const Offset(0, 4),
              ),
              BoxShadow(
                color: _C.orange.withValues(alpha: 0.06),
                blurRadius: 40,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Row(
            children: [
              // Icon
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [_C.orange, Color(0xFFF97316)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.search_rounded,
                  color: Colors.white,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),

              // Labels
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Décrire vos travaux',
                      style: TextStyle(
                        color: _C.ink,
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Plomberie, électricité, maçonnerie…',
                      style: TextStyle(
                        color: _C.muted,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),

              // Filter button
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: _C.goldLight,
                  borderRadius: BorderRadius.circular(11),
                ),
                child: const Icon(Icons.tune_rounded, color: _C.gold, size: 18),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Stats Row ────────────────────────────────────────────────────────────────
class _StatsRow extends StatelessWidget {
  final HomeController controller;
  const _StatsRow({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      child: Obx(
        () => Row(
          children: [
            Expanded(
              child: _StatCard(
                label: 'Missions actives',
                value: '${controller.activeMissionsCount.value}',
                icon: Icons.assignment_outlined,
                accent: _C.orange,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _StatCard(
                label: 'Artisans proches',
                value: '${controller.nearbyArtisansCount.value}',
                icon: Icons.location_on_outlined,
                accent: _C.gold,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color accent;

  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.accent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle.withValues(alpha: 0.6)),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: accent, size: 18),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: accent,
                  letterSpacing: -0.5,
                ),
              ),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w500,
                  color: _C.muted,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ─── Section Title ────────────────────────────────────────────────────────────
class _SectionTitle extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget? trailing;

  const _SectionTitle({
    required this.title,
    required this.subtitle,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 28, 20, 14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: _C.ink,
                      letterSpacing: -0.4,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: _C.muted,
                    ),
                  ),
                ],
              ),
            ),
            if (trailing != null) trailing!,
          ],
        ),
      ),
    );
  }
}

class _CountPill extends StatelessWidget {
  final int count;
  const _CountPill({required this.count});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: _C.orangeLight,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _C.orange.withValues(alpha: 0.20)),
      ),
      child: Text(
        '$count dispo.',
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: _C.orange,
        ),
      ),
    );
  }
}

// ─── Categories Horizontal Scroll ─────────────────────────────────────────────
class _CategoriesScroll extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 112,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        itemCount: _kCategories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (_, i) {
          final cat = _kCategories[i];
          final color = cat['color'] as Color;
          return GestureDetector(
            onTap: () => Get.toNamed(
              Routes.missionRequest,
              arguments: {'category': cat['label']},
            ),
            child: Container(
              width: 84,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: _C.subtle.withValues(alpha: 0.6)),
                boxShadow: [
                  BoxShadow(
                    color: _C.ink.withValues(alpha: 0.04),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child:
                        Icon(cat['icon'] as IconData, color: color, size: 22),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    cat['label'] as String,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: _C.inkMid,
                      height: 1.2,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

// ─── Artisans List (sliver) ───────────────────────────────────────────────────
class _ArtisansList extends StatelessWidget {
  final HomeController controller;
  const _ArtisansList({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.isLoading.value) {
        return SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: LoadingShimmer.list(count: 4),
          ),
        );
      }
      if (controller.artisans.isEmpty) {
        return SliverToBoxAdapter(child: _EmptyState());
      }
      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (_, i) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: ArtisanCard(
              artisan: controller.artisans[i],
              onTap: () => Get.toNamed(
                Routes.artisanProfile,
                arguments: controller.artisans[i],
              ),
            ),
          ),
          childCount: controller.artisans.length,
        ),
      );
    });
  }
}

// ─── Empty State ──────────────────────────────────────────────────────────────
class _EmptyState extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _C.subtle.withValues(alpha: 0.6)),
      ),
      child: Column(
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: _C.orangeLight,
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Icon(
              Icons.search_off_rounded,
              size: 36,
              color: _C.orange,
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Aucun artisan trouvé',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Modifiez vos critères\nou élargissez votre zone de recherche',
            style: TextStyle(
              fontSize: 13,
              color: _C.muted,
              height: 1.5,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
