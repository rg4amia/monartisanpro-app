import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/controllers/search_controller.dart' as artisan_search;

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _authController = Get.find<AuthController>();
  final _searchController = Get.put(artisan_search.ArtisanSearchController());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            // App Bar
            SliverAppBar(
              floating: true,
              backgroundColor: Theme.of(context).colorScheme.primary,
              title: Row(
                children: [
                  Icon(
                    Icons.build_circle,
                    color: Colors.white,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Text(
                    'ProsArtisan',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
              actions: [
                IconButton(
                  icon: const Icon(Icons.notifications_outlined, color: Colors.white),
                  onPressed: () {
                    // TODO: Navigate to notifications
                  },
                ),
                Obx(() {
                  final user = _authController.currentUser.value;
                  return user?.avatar != null
                      ? CircleAvatar(
                          backgroundImage: NetworkImage(user!.avatar!),
                          radius: 16,
                        )
                      : CircleAvatar(
                          backgroundColor: Colors.white,
                          child: Icon(
                            Icons.person,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                        );
                }),
                const SizedBox(width: Spacing.md),
              ],
            ),

            // Welcome Section
            SliverToBoxAdapter(
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: AppColors.primaryGradient,
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                  ),
                ),
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Obx(() {
                      final user = _authController.currentUser.value;
                      return Text(
                        'Bonjour ${user?.name.split(' ').first ?? 'Invité'}!',
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                      );
                    }),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      'Quel artisan cherchez-vous aujourd\'hui?',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.white.withValues(alpha: 0.9),
                          ),
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Search Bar
                    GestureDetector(
                      onTap: () => Get.to(() => const SearchFilterScreen()),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.base,
                          vertical: Spacing.md,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(Spacing.radiusLg),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 10,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.search,
                              color: AppColors.lightTextSecondary,
                            ),
                            const SizedBox(width: Spacing.md),
                            Expanded(
                              child: Text(
                                'Rechercher un artisan...',
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      color: AppColors.lightTextSecondary,
                                    ),
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.all(Spacing.sm),
                              decoration: BoxDecoration(
                                color: AppColors.lightBackground,
                                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                              ),
                              child: Icon(
                                Icons.tune,
                                size: 20,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Map View Button
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () => Get.to(() => const MapSearchScreen()),
                        icon: const Icon(Icons.map_outlined),
                        label: const Text('Voir sur la carte'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: const BorderSide(color: Colors.white, width: 2),
                          padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Nearby Artisans Section
            Obx(() {
              if (_searchController.nearbyArtisansCount > 0) {
                return SliverToBoxAdapter(
                  child: Container(
                    margin: const EdgeInsets.all(Spacing.screenPadding),
                    padding: const EdgeInsets.all(Spacing.base),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: AppColors.warningGradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.location_on,
                          color: AppColors.goldenMarker,
                          size: 32,
                        ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${_searchController.nearbyArtisansCount} artisans à proximité',
                                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.bold,
                                    ),
                              ),
                              Text(
                                'À moins de 2km de vous',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Colors.white.withValues(alpha: 0.9),
                                    ),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          Icons.arrow_forward_ios,
                          color: Colors.white,
                          size: 20,
                        ),
                      ],
                    ),
                  ),
                );
              }
              return const SliverToBoxAdapter(child: SizedBox.shrink());
            }),

            // Categories Section Header
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  Spacing.screenPadding,
                  Spacing.lg,
                  Spacing.screenPadding,
                  Spacing.md,
                ),
                child: Text(
                  'Catégories de métiers',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),

            // Categories Grid
            Obx(() {
              if (_searchController.isLoadingSectors.value) {
                return const SliverFillRemaining(
                  child: Center(child: CircularProgressIndicator()),
                );
              }

              if (_searchController.sectors.isEmpty) {
                return const SliverFillRemaining(
                  child: Center(child: Text('Aucune catégorie disponible')),
                );
              }

              return SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: Spacing.screenPadding),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    childAspectRatio: 0.85,
                    crossAxisSpacing: Spacing.md,
                    mainAxisSpacing: Spacing.md,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (context, index) {
                      final sector = _searchController.sectors[index];
                      return _buildCategoryCard(sector);
                    },
                    childCount: _searchController.sectors.length,
                  ),
                ),
              );
            }),

            const SliverToBoxAdapter(
              child: SizedBox(height: Spacing.xxxl),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryCard(sector) {
    final iconData = _getSectorIcon(sector.code);
    final gradient = _getSectorGradient(sector.code);

    return GestureDetector(
      onTap: () {
        _searchController.setSectorFilter(sector.id);
        Get.to(() => const SearchFilterScreen());
      },
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: gradient,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          boxShadow: [
            BoxShadow(
              color: gradient[0].withValues(alpha: 0.3),
              blurRadius: 8,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              iconData,
              size: 40,
              color: Colors.white,
            ),
            const SizedBox(height: Spacing.sm),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.xs),
              child: Text(
                sector.name,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                    ),
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  IconData _getSectorIcon(String code) {
    final icons = {
      'BAT': Icons.construction,
      'ELEC': Icons.electrical_services,
      'PLOMB': Icons.plumbing,
      'MENU': Icons.carpenter,
      'PEIN': Icons.format_paint,
      'JAR': Icons.yard,
      'AUTO': Icons.directions_car,
      'TECH': Icons.devices,
      'AMEN': Icons.weekend,
      'CLEAN': Icons.cleaning_services,
      'SECU': Icons.security,
      'ART': Icons.palette,
    };
    return icons[code] ?? Icons.build;
  }

  List<Color> _getSectorGradient(String code) {
    final gradients = {
      'BAT': [const Color(0xFF4F46E5), const Color(0xFF7C3AED)],
      'ELEC': [const Color(0xFFF59E0B), const Color(0xFFD97706)],
      'PLOMB': [const Color(0xFF3B82F6), const Color(0xFF2563EB)],
      'MENU': [const Color(0xFF8B4513), const Color(0xFF654321)],
      'PEIN': [const Color(0xFF10B981), const Color(0xFF059669)],
      'JAR': [const Color(0xFF22C55E), const Color(0xFF16A34A)],
      'AUTO': [const Color(0xFF6366F1), const Color(0xFF4F46E5)],
      'TECH': [const Color(0xFF8B5CF6), const Color(0xFF7C3AED)],
      'AMEN': [const Color(0xFFEC4899), const Color(0xFFDB2777)],
      'CLEAN': [const Color(0xFF14B8A6), const Color(0xFF0D9488)],
      'SECU': [const Color(0xFFEF4444), const Color(0xFFDC2626)],
      'ART': [const Color(0xFFF97316), const Color(0xFFEA580C)],
    };
    return gradients[code] ?? AppColors.primaryGradient;
  }
}
