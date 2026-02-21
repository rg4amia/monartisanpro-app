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
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
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
      snap: true,
      backgroundColor: AppColors.darkBackground,
      elevation: 0,
      toolbarHeight: 72,
      title: Row(
        children: [
          Hero(
            tag: 'app_logo',
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.darkAccentPrimary,
                    AppColors.darkAccentPrimary.withOpacity(0.7),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.darkAccentPrimary.withOpacity(0.4),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: const Icon(
                Icons.build_circle,
                color: Colors.white,
                size: 28,
              ),
            ),
          ),
          const SizedBox(width: Spacing.md),
          const Text(
            'ProsArtisan',
            style: TextStyle(
              color: AppColors.darkTextPrimary,
              fontWeight: FontWeight.w800,
              fontSize: 22,
              height: 1.2,
              letterSpacing: -0.5,
            ),
          ),
        ],
      ),
      actions: [
        Container(
          margin: const EdgeInsets.only(right: Spacing.xs),
          decoration: BoxDecoration(
            color: AppColors.darkCard,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.overlayLight, width: 1),
          ),
          child: Stack(
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
                    borderRadius: 12,
                    margin: const EdgeInsets.all(Spacing.base),
                  );
                },
              ),
              Positioned(
                right: 10,
                top: 10,
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: AppColors.darkAccentDanger,
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.darkCard, width: 1.5),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: Spacing.sm),
        Obx(() {
          final user = _authController.currentUser.value;
          return GestureDetector(
            onTap: () {
              // Navigate to profile
            },
            child: Container(
              width: 44,
              height: 44,
              margin: const EdgeInsets.only(right: Spacing.base),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [
                    AppColors.darkAccentPrimary,
                    AppColors.darkAccentPrimary.withOpacity(0.7),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.darkAccentPrimary.withOpacity(0.4),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              padding: const EdgeInsets.all(2),
              child: user?.avatar != null
                  ? CircleAvatar(
                      backgroundImage: NetworkImage(user!.avatar!),
                      radius: 20,
                    )
                  : CircleAvatar(
                      backgroundColor: AppColors.darkCard,
                      child: Icon(
                        Icons.person,
                        color: AppColors.darkTextPrimary,
                        size: 22,
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
          Spacing.xl,
          Spacing.lg,
          Spacing.lg,
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
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0.0, end: 1.0),
                    duration: const Duration(milliseconds: 600),
                    curve: Curves.easeOut,
                    builder: (context, value, child) {
                      return Opacity(
                        opacity: value,
                        child: Transform.translate(
                          offset: Offset(0, 20 * (1 - value)),
                          child: child,
                        ),
                      );
                    },
                    child: Text(
                      'Bonjour $firstName! 👋',
                      style: const TextStyle(
                        color: AppColors.darkTextPrimary,
                        fontWeight: FontWeight.w800,
                        fontSize: 32,
                        height: 1.2,
                        letterSpacing: -0.5,
                      ),
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0.0, end: 1.0),
                    duration: const Duration(milliseconds: 600),
                    curve: Curves.easeOut,
                    builder: (context, value, child) {
                      return Opacity(opacity: value, child: child);
                    },
                    child: Text(
                      'Trouvez le meilleur artisan pour vos projets',
                      style: TextStyle(
                        color: AppColors.darkTextSecondary,
                        fontSize: 16,
                        height: 1.5,
                        fontWeight: FontWeight.w400,
                      ),
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
          child: Hero(
            tag: 'search_bar',
            child: Material(
              color: Colors.transparent,
              child: Container(
                height: 60,
                padding: const EdgeInsets.symmetric(horizontal: Spacing.base),
                decoration: BoxDecoration(
                  color: AppColors.darkCard,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.overlayLight, width: 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.15),
                      blurRadius: 20,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppColors.darkAccentPrimary.withOpacity(0.15),
                            AppColors.darkAccentPrimary.withOpacity(0.05),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(
                        Icons.search_rounded,
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
                          fontWeight: FontWeight.w400,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            AppColors.darkAccentPrimary,
                            AppColors.darkAccentPrimary.withOpacity(0.8),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(10),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.darkAccentPrimary.withOpacity(0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.tune_rounded,
                        size: 20,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
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
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: Spacing.base),
          decoration: BoxDecoration(
            color: AppColors.darkCard,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.overlayLight, width: 1.5),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.1),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      AppColors.darkAccentPrimary.withOpacity(0.15),
                      AppColors.darkAccentPrimary.withOpacity(0.05),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: AppColors.darkAccentPrimary, size: 26),
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                label,
                style: const TextStyle(
                  color: AppColors.darkTextSecondary,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPromotionalCard() {
    return Obx(() {
      if (_searchController.nearbyArtisansCount > 0) {
        return SliverToBoxAdapter(
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0.0, end: 1.0),
            duration: const Duration(milliseconds: 800),
            curve: Curves.easeOut,
            builder: (context, value, child) {
              return Opacity(
                opacity: value,
                child: Transform.scale(
                  scale: 0.9 + (0.1 * value),
                  child: child,
                ),
              );
            },
            child: Container(
              margin: const EdgeInsets.symmetric(
                horizontal: Spacing.lg,
                vertical: Spacing.base,
              ),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    AppColors.darkAccentPrimary,
                    AppColors.darkAccentPrimary.withOpacity(0.75),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.darkAccentPrimary.withOpacity(0.4),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () => Get.to(() => const SearchFilterScreen()),
                  borderRadius: BorderRadius.circular(20),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.25),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Icon(
                            Icons.location_on_rounded,
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
                                  fontWeight: FontWeight.w800,
                                  fontSize: 18,
                                  height: 1.3,
                                  letterSpacing: -0.3,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  Container(
                                    width: 6,
                                    height: 6,
                                    decoration: const BoxDecoration(
                                      color: Colors.white,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  const Text(
                                    'À moins de 2km',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    width: 6,
                                    height: 6,
                                    decoration: const BoxDecoration(
                                      color: Colors.white,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  const Text(
                                    'Disponibles maintenant',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.2),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(
                            Icons.arrow_forward_rounded,
                            color: Colors.white,
                            size: 20,
                          ),
                        ),
                      ],
                    ),
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
          Spacing.xxl,
          Spacing.lg,
          Spacing.lg,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                color: AppColors.darkTextPrimary,
                height: 1.2,
                letterSpacing: -0.5,
              ),
            ),
            if (actionText != null)
              TextButton.icon(
                onPressed: () {
                  Get.to(() => const SearchFilterScreen());
                },
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.md,
                    vertical: Spacing.sm,
                  ),
                  backgroundColor: AppColors.darkCard,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: AppColors.overlayLight, width: 1),
                  ),
                ),
                icon: Icon(
                  Icons.arrow_forward_rounded,
                  color: AppColors.darkAccentPrimary,
                  size: 18,
                ),
                label: Text(
                  actionText,
                  style: TextStyle(
                    color: AppColors.darkAccentPrimary,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
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
            childAspectRatio: 0.92,
            crossAxisSpacing: Spacing.base,
            mainAxisSpacing: Spacing.base,
          ),
          delegate: SliverChildBuilderDelegate((context, index) {
            final sector = _searchController.sectors[index];
            return TweenAnimationBuilder<double>(
              tween: Tween(begin: 0.0, end: 1.0),
              duration: Duration(milliseconds: 400 + (index * 50)),
              curve: Curves.easeOut,
              builder: (context, value, child) {
                return Opacity(
                  opacity: value,
                  child: Transform.translate(
                    offset: Offset(0, 20 * (1 - value)),
                    child: child,
                  ),
                );
              },
              child: _buildCategoryCard(sector),
            );
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
        borderRadius: BorderRadius.circular(18),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [cardColor, cardColor.withOpacity(0.85)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: cardColor.withOpacity(0.4),
                blurRadius: 12,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.25),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(iconData, size: 34, color: Colors.white),
              ),
              const SizedBox(height: Spacing.sm),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
                child: Text(
                  sector.name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    height: 1.3,
                    letterSpacing: -0.2,
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
