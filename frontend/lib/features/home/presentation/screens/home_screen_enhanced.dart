import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../search/presentation/screens/map_search_screen.dart';
import '../../../search/presentation/screens/search_filter_screen.dart';

/// Enhanced Home Screen with improved visual design
/// Figma reference: node-id=3118:11495
class HomeScreenEnhanced extends StatefulWidget {
  const HomeScreenEnhanced({super.key});

  @override
  State<HomeScreenEnhanced> createState() => _HomeScreenEnhancedState();
}

class _HomeScreenEnhancedState extends State<HomeScreenEnhanced> {
  final _authController = Get.find<AuthController>();
  late final artisan_search.ArtisanSearchController _searchController;

  @override
  void initState() {
    super.initState();
    try {
      _searchController = Get.put(artisan_search.ArtisanSearchController());
      _loadInitialData();
    } catch (e) {
      if (Get.isRegistered<artisan_search.ArtisanSearchController>()) {
        _searchController = Get.find<artisan_search.ArtisanSearchController>();
      } else {
        _searchController = Get.put(artisan_search.ArtisanSearchController());
      }
    }
  }

  Future<void> _loadInitialData() async {
    await Future.wait([
      _searchController.fetchSectors(),
      _searchController.getCurrentLocation(),
    ]);

    if (_searchController.currentPosition.value != null) {
      await _searchController.getNearbyArtisans();
    }
  }

  Future<void> _refreshData() async {
    await _loadInitialData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.darkBackground,
      body: RefreshIndicator(
        color: AppColors.darkAccentPrimary,
        backgroundColor: AppColors.darkCard,
        onRefresh: _refreshData,
        child: SafeArea(
          child: CustomScrollView(
            slivers: [
              _buildTopBar(),
              _buildWelcomeSection(),
              _buildSearchBar(),
              _buildQuickActions(),
              _buildPromotionalCard(),
              _buildSectionHeader('Catégories populaires', 'Voir tout'),
              _buildCategoriesGrid(),
              const SliverToBoxAdapter(child: SizedBox(height: Spacing.xxxxl)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTopBar() {
    return SliverAppBar(
      floating: true,
      backgroundColor: AppColors.darkBackground,
      elevation: 0,
      toolbarHeight: 64,
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.xs),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  AppColors.darkAccentPrimary,
                  AppColors.darkAccentPrimary.withOpacity(0.8),
                ],
              ),
              borderRadius: BorderRadius.circular(Spacing.radiusSm),
            ),
            child: const Icon(
              Icons.build_circle,
              color: Colors.white,
              size: 24,
            ),
          ),
          const SizedBox(width: Spacing.sm),
          const Text(
            'ProsArtisan',
            style: TextStyle(
              color: AppColors.darkTextPrimary,
              fontWeight: FontWeight.w700,
              fontSize: 20,
              height: 1.2,
            ),
          ),
        ],
      ),
      actions: [
        Stack(
          children: [
            IconButton(
              icon: const Icon(
                Icons.notifications_outlined,
                color: AppColors.darkTextPrimary,
                size: 24,
              ),
              onPressed: () {
                Get.snackbar(
                  'Notifications',
                  'Fonctionnalité à venir',
                  backgroundColor: AppColors.darkCard,
                  colorText: AppColors.darkTextPrimary,
                );
              },
            ),
            Positioned(
              right: 8,
              top: 8,
              child: Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: AppColors.darkAccentDanger,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.darkBackground,
                    width: 1.5,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(width: Spacing.xs),
        Obx(() {
          final user = _authController.currentUser.value;
          return GestureDetector(
            onTap: () {
              // Navigate to profile
            },
            child: Container(
              width: 40,
              height: 40,
              margin: const EdgeInsets.only(right: Spacing.base),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: AppColors.darkAccentPrimary,
                  width: 2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.darkAccentPrimary.withOpacity(0.3),
                    blurRadius: 8,
                    spreadRadius: 0,
                  ),
                ],
              ),
              child: user?.avatar != null
                  ? CircleAvatar(
                      backgroundImage: NetworkImage(user!.avatar!),
                      radius: 18,
                    )
                  : CircleAvatar(
                      backgroundColor: AppColors.darkCard,
                      child: Icon(
                        Icons.person,
                        color: AppColors.darkTextSecondary,
                        size: 20,
                      ),
                    ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildWelcomeSection() {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.lg,
          Spacing.lg,
          Spacing.md,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Obx(() {
              final user = _authController.currentUser.value;
              final firstName = user?.name.split(' ').first ?? 'Invité';
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Bonjour $firstName! 👋',
                    style: const TextStyle(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w700,
                      fontSize: 28,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: Spacing.xs),
                  const Text(
                    'Trouvez le meilleur artisan pour vos projets',
                    style: TextStyle(
                      color: AppColors.darkTextSecondary,
                      fontSize: 15,
                      height: 1.5,
                    ),
                  ),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        child: GestureDetector(
          onTap: () => Get.to(() => const SearchFilterScreen()),
          child: Container(
            height: 56,
            padding: const EdgeInsets.symmetric(horizontal: Spacing.base),
            decoration: BoxDecoration(
              color: AppColors.darkCard,
              borderRadius: BorderRadius.circular(Spacing.radiusLg),
              border: Border.all(color: AppColors.overlayLight, width: 1),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.2),
                  blurRadius: 8,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(Spacing.sm),
                  decoration: BoxDecoration(
                    color: AppColors.darkAccentPrimary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                  ),
                  child: Icon(
                    Icons.search,
                    color: AppColors.darkAccentPrimary,
                    size: 24,
                  ),
                ),
                const SizedBox(width: Spacing.md),
                const Expanded(
                  child: Text(
                    'Rechercher un artisan ou un service...',
                    style: TextStyle(
                      color: AppColors.darkTextTertiary,
                      fontSize: 15,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(Spacing.sm),
                  decoration: BoxDecoration(
                    color: AppColors.darkAccentPrimary,
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                  ),
                  child: const Icon(Icons.tune, size: 20, color: Colors.white),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildQuickActions() {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Row(
          children: [
            Expanded(
              child: _buildQuickActionButton(
                icon: Icons.map_outlined,
                label: 'Carte',
                onTap: () => Get.to(() => const MapSearchScreen()),
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _buildQuickActionButton(
                icon: Icons.history,
                label: 'Historique',
                onTap: () {
                  Get.snackbar(
                    'Historique',
                    'Fonctionnalité à venir',
                    backgroundColor: AppColors.darkCard,
                    colorText: AppColors.darkTextPrimary,
                  );
                },
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _buildQuickActionButton(
                icon: Icons.favorite_outline,
                label: 'Favoris',
                onTap: () {
                  Get.snackbar(
                    'Favoris',
                    'Fonctionnalité à venir',
                    backgroundColor: AppColors.darkCard,
                    colorText: AppColors.darkTextPrimary,
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: Spacing.md),
        decoration: BoxDecoration(
          color: AppColors.darkCard,
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          border: Border.all(color: AppColors.overlayLight, width: 1),
        ),
        child: Column(
          children: [
            Icon(icon, color: AppColors.darkAccentPrimary, size: 24),
            const SizedBox(height: Spacing.xs),
            Text(
              label,
              style: const TextStyle(
                color: AppColors.darkTextSecondary,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPromotionalCard() {
    return Obx(() {
      if (_searchController.nearbyArtisansCount > 0) {
        return SliverToBoxAdapter(
          child: Container(
            margin: const EdgeInsets.symmetric(
              horizontal: Spacing.lg,
              vertical: Spacing.base,
            ),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  AppColors.darkAccentPrimary,
                  AppColors.darkAccentPrimary.withOpacity(0.8),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(Spacing.radiusXl),
              boxShadow: [
                BoxShadow(
                  color: AppColors.darkAccentPrimary.withOpacity(0.3),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => Get.to(() => const SearchFilterScreen()),
                borderRadius: BorderRadius.circular(Spacing.radiusXl),
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(Spacing.base),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        ),
                        child: const Icon(
                          Icons.location_on,
                          color: Colors.white,
                          size: 32,
                        ),
                      ),
                      const SizedBox(width: Spacing.base),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${_searchController.nearbyArtisansCount} artisans près de vous',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                                fontSize: 17,
                                height: 1.3,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'À moins de 2km • Disponibles maintenant',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(
                        Icons.arrow_forward_ios,
                        color: Colors.white,
                        size: 18,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      }
      return const SliverToBoxAdapter(child: SizedBox.shrink());
    });
  }

  Widget _buildSectionHeader(String title, String? actionText) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.xl,
          Spacing.lg,
          Spacing.base,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
                color: AppColors.darkTextPrimary,
                height: 1.2,
              ),
            ),
            if (actionText != null)
              TextButton(
                onPressed: () {
                  Get.to(() => const SearchFilterScreen());
                },
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.sm,
                    vertical: Spacing.xs,
                  ),
                ),
                child: Text(
                  actionText,
                  style: TextStyle(
                    color: AppColors.darkAccentPrimary,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoriesGrid() {
    return Obx(() {
      if (_searchController.isLoadingSectors.value) {
        return SliverFillRemaining(
          child: Center(
            child: CircularProgressIndicator(
              color: AppColors.darkAccentPrimary,
            ),
          ),
        );
      }

      if (_searchController.sectors.isEmpty) {
        return const SliverFillRemaining(
          child: Center(
            child: Text(
              'Aucune catégorie disponible',
              style: TextStyle(
                color: AppColors.darkTextSecondary,
                fontSize: 14,
              ),
            ),
          ),
        );
      }

      return SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: Spacing.lg),
        sliver: SliverGrid(
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            childAspectRatio: 0.95,
            crossAxisSpacing: Spacing.md,
            mainAxisSpacing: Spacing.md,
          ),
          delegate: SliverChildBuilderDelegate((context, index) {
            final sector = _searchController.sectors[index];
            return _buildCategoryCard(sector);
          }, childCount: _searchController.sectors.length),
        ),
      );
    });
  }

  Widget _buildCategoryCard(dynamic sector) {
    final iconData = _getSectorIcon(sector.code);
    final cardColor = _getSectorColor(sector.code);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () {
          _searchController.setSectorFilter(sector.id);
          Get.to(() => const SearchFilterScreen());
        },
        borderRadius: BorderRadius.circular(Spacing.radiusLg),
        child: Container(
          decoration: BoxDecoration(
            color: cardColor,
            borderRadius: BorderRadius.circular(Spacing.radiusLg),
            boxShadow: [
              BoxShadow(
                color: cardColor.withOpacity(0.3),
                blurRadius: 8,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(Spacing.sm),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                ),
                child: Icon(iconData, size: 32, color: Colors.white),
              ),
              const SizedBox(height: Spacing.sm),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
                child: Text(
                  sector.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    height: 1.3,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  IconData _getSectorIcon(String code) {
    final icons = {
      '1': Icons.directions_car,
      '2': Icons.electrical_services,
      '3': Icons.plumbing,
      '4': Icons.construction,
      '5': Icons.carpenter,
      '6': Icons.build,
      '7': Icons.palette,
      '8': Icons.devices,
      '9': Icons.ac_unit,
      '10': Icons.cleaning_services,
      '11': Icons.security,
      '12': Icons.water_drop,
    };
    return icons[code] ?? Icons.build;
  }

  Color _getSectorColor(String code) {
    final colors = {
      '1': const Color(0xFF6366F1),
      '2': const Color(0xFFF59E0B),
      '3': const Color(0xFF3B82F6),
      '4': AppColors.darkAccentPrimary,
      '5': const Color(0xFF8B4513),
      '6': const Color(0xFF78716C),
      '7': const Color(0xFFEC4899),
      '8': const Color(0xFF8B5CF6),
      '9': const Color(0xFF06B6D4),
      '10': AppColors.darkAccentSecondary,
      '11': AppColors.darkAccentDanger,
      '12': const Color(0xFF14B8A6),
    };
    return colors[code] ?? AppColors.darkCard;
  }
}
