import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../shared/widgets/artisan_card.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/home_controller.dart';

// ─── Local design tokens (screen-scoped) ──────────────────────────────────────
abstract class _C {
  // Backgrounds
  static const bg = Color(0xFFF8F9FA); // light neutral
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5); // indigo
  static const primaryLight = Color(0xFFEEF2FF);

  // Text
  static const ink = Color(0xFF111827);
  static const inkMid = Color(0xFF374151);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

// ─── Categories data ──────────────────────────────────────────────────────────
const _kCategories = [
  {
    'label': 'Plomberie',
    'icon': Icons.plumbing_outlined,
    'color': Color(0xFF3B82F6)
  },
  {
    'label': 'Électricité',
    'icon': Icons.electric_bolt_outlined,
    'color': Color(0xFFF59E0B)
  },
  {
    'label': 'Maçonnerie',
    'icon': Icons.foundation_outlined,
    'color': Color(0xFF78716C)
  },
  {
    'label': 'Peinture',
    'icon': Icons.format_paint_outlined,
    'color': Color(0xFF10B981)
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
      body: SafeArea(
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            _AppBar(controller: c),
            SliverToBoxAdapter(child: _SearchBar()),
            SliverToBoxAdapter(child: _QuickRequestCard()),
            _SectionTitle(
                title: 'Service Categories',
                trailing: TextButton(
                  onPressed: () => Get.toNamed(Routes.sectors),
                  child: const Text('See all',
                      style: TextStyle(color: _C.primary)),
                )),
            SliverToBoxAdapter(child: _CategoriesGrid()),
            _SectionTitle(
              title: 'Nearby Artisans',
              trailing: Obx(
                  () => _LocationChip(location: c.nearbyArtisansCount.value)),
            ),
            _ArtisansList(controller: c),
            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  final HomeController controller;
  const _AppBar({required this.controller});

  @override
  Widget build(BuildContext context) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
        child: Row(
          children: [
            // Profile avatar
            GestureDetector(
              onTap: () => Get.toNamed(Routes.settings),
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: _C.primary,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: _C.primary.withValues(alpha: 0.3),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: const Icon(Icons.person, color: Colors.white, size: 22),
              ),
            ),
            const SizedBox(width: 12),
            // Title
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "N'Zassa Home",
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: _C.ink,
                      letterSpacing: -0.5,
                    ),
                  ),
                  Obx(() => Text(
                        controller.userName.value.isNotEmpty
                            ? 'Welcome, ${controller.userName.value}'
                            : 'Welcome back',
                        style: const TextStyle(
                          fontSize: 13,
                          color: _C.muted,
                        ),
                      )),
                ],
              ),
            ),
            // Notification button
            GestureDetector(
              onTap: () => Get.toNamed(Routes.notifications),
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: _C.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _C.subtle),
                ),
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    const Icon(Icons.notifications_outlined,
                        color: _C.ink, size: 22),
                    Positioned(
                      top: 10,
                      right: 10,
                      child: Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFFEF4444),
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
      ),
    );
  }
}

// ─── Search Bar ───────────────────────────────────────────────────────────────
class _SearchBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: GestureDetector(
        onTap: () => Get.toNamed(Routes.missionRequest),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: _C.surface,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: _C.subtle),
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
              Icon(Icons.search, color: _C.muted, size: 22),
              const SizedBox(width: 12),
              const Text(
                'What do you need help with?',
                style: TextStyle(
                  color: _C.muted,
                  fontSize: 15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Quick Request Card ───────────────────────────────────────────────────────
class _QuickRequestCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      child: GestureDetector(
        onTap: () => Get.toNamed(Routes.missionRequest),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [_C.primary, Color(0xFF6366F1)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: _C.primary.withValues(alpha: 0.3),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.auto_awesome,
                    color: Colors.white, size: 24),
              ),
              const SizedBox(width: 16),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.stars, color: Colors.white, size: 16),
                        SizedBox(width: 6),
                        Text(
                          'AI ASSISTANT',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Quick Request',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Describe your need in plain text for instant matching with the best artisans.',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.arrow_forward_ios,
                  color: Colors.white, size: 18),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Section Title ────────────────────────────────────────────────────────────
class _SectionTitle extends StatelessWidget {
  final String title;
  final Widget? trailing;

  const _SectionTitle({
    required this.title,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 24, 20, 12),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
                letterSpacing: -0.3,
              ),
            ),
            if (trailing != null) trailing!,
          ],
        ),
      ),
    );
  }
}

class _LocationChip extends StatelessWidget {
  final int location;
  const _LocationChip({required this.location});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: _C.primaryLight,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.location_on, size: 14, color: _C.primary),
          const SizedBox(width: 4),
          Text(
            'Abidjan',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: _C.primary,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Categories Grid ──────────────────────────────────────────────────────────
class _CategoriesGrid extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 0.85,
        ),
        itemCount: _kCategories.length,
        itemBuilder: (_, i) {
          final cat = _kCategories[i];
          final color = cat['color'] as Color;
          return GestureDetector(
            onTap: () => Get.toNamed(
              Routes.missionRequest,
              arguments: {'category': cat['label']},
            ),
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
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
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
                      fontSize: 11,
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
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: LoadingShimmer.list(count: 3),
          ),
        );
      }
      if (controller.artisans.isEmpty) {
        return SliverToBoxAdapter(child: _EmptyState());
      }
      return SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        sliver: SliverList(
          delegate: SliverChildBuilderDelegate(
            (_, i) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
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
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 24),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.search_off_rounded,
              size: 32,
              color: _C.primary,
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'No artisans found',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Try adjusting your search criteria\nor expand your search area',
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
