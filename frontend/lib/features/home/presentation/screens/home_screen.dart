import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../search/presentation/screens/map_search_screen.dart';
import '../../../search/presentation/screens/search_filter_screen.dart';

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
    // Initialize search controller with error handling
    try {
      _searchController = Get.put(artisan_search.ArtisanSearchController());
      // Load sectors when screen initializes
      _loadInitialData();
    } catch (e) {
      print('Error initializing search controller: $e');
      // Try to find existing instance or create new one
      if (Get.isRegistered<artisan_search.ArtisanSearchController>()) {
        _searchController = Get.find<artisan_search.ArtisanSearchController>();
      } else {
        _searchController = Get.put(artisan_search.ArtisanSearchController());
      }
    }
  }

  Future<void> _loadInitialData() async {
    // Load sectors and get current location
    await Future.wait([
      _searchController.fetchSectors(),
      _searchController.getCurrentLocation(),
    ]);

    // Search for nearby artisans
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
      backgroundColor: const Color(0xFF0A0A2A),
      body: RefreshIndicator(
        onRefresh: _refreshData,
        child: SafeArea(
          child: CustomScrollView(
            slivers: [
              // App Bar
              SliverAppBar(
                floating: true,
                backgroundColor: const Color(0xFF0A0A2A),
                elevation: 0,
                title: Row(
                  children: [
                    Icon(
                      Icons.build_circle,
                      color: const Color(0xFFFFD700),
                      size: 28,
                    ),
                    const SizedBox(width: Spacing.sm),
                    Text(
                      'ProsArtisan',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 20,
                      ),
                    ),
                  ],
                ),
                actions: [
                  IconButton(
                    icon: const Icon(
                      Icons.notifications_outlined,
                      color: Colors.white,
                      size: 24,
                    ),
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
                            backgroundColor: const Color(0xFF1A1A3E),
                            child: Icon(
                              Icons.person,
                              color: Colors.white,
                              size: 20,
                            ),
                          );
                  }),
                  const SizedBox(width: Spacing.md),
                ],
              ),

              // Welcome Section
              SliverToBoxAdapter(
                child: Container(
                  decoration: const BoxDecoration(color: Color(0xFF0A0A2A)),
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Obx(() {
                        final user = _authController.currentUser.value;
                        return Text(
                          'Bonjour ${user?.name.split(' ').first ?? 'Invité'}!',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 24,
                            height: 1.2,
                          ),
                        );
                      }),
                      const SizedBox(height: 8),
                      Text(
                        'Quel artisan cherchez-vous aujourd\'hui?',
                        style: TextStyle(
                          color: const Color(0xFFB0B0B0),
                          fontSize: 14,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Search Bar
                      GestureDetector(
                        onTap: () => Get.to(() => const SearchFilterScreen()),
                        child: Container(
                          height: 48,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1A1A3E),
                            borderRadius: BorderRadius.circular(24),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.1),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Icon(
                                Icons.search,
                                color: const Color(0xFFB0B0B0),
                                size: 24,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  'Rechercher un artisan...',
                                  style: TextStyle(
                                    color: const Color(0xFFB0B0B0),
                                    fontSize: 14,
                                  ),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: const Color(0xFF007BFF),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Icon(
                                  Icons.tune,
                                  size: 20,
                                  color: Colors.white,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Map View Button
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () =>
                              Get.to(() => const MapSearchScreen()),
                          icon: const Icon(Icons.map_outlined, size: 20),
                          label: const Text(
                            'Voir sur la carte',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(
                              color: Color(0xFF007BFF),
                              width: 1,
                            ),
                            backgroundColor: const Color(
                              0xFF007BFF,
                            ).withValues(alpha: 0.1),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
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
                      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFF007BFF),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.1),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.location_on,
                            color: const Color(0xFFFFD700),
                            size: 32,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${_searchController.nearbyArtisansCount} artisans à proximité',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 16,
                                    height: 1.5,
                                  ),
                                ),
                                Text(
                                  'À moins de 2km de vous',
                                  style: TextStyle(
                                    color: Colors.white.withValues(alpha: 0.9),
                                    fontSize: 12,
                                    height: 1.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Icon(
                            Icons.arrow_forward_ios,
                            color: Colors.white,
                            size: 16,
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
                  padding: EdgeInsets.fromLTRB(16, 24, 16, 16),
                  child: Text(
                    'Catégories de métiers',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                      height: 1.2,
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
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  sliver: SliverGrid(
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 3,
                          childAspectRatio: 0.9,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                        ),
                    delegate: SliverChildBuilderDelegate((context, index) {
                      final sector = _searchController.sectors[index];
                      return _buildCategoryCard(sector);
                    }, childCount: _searchController.sectors.length),
                  ),
                );
              }),

              const SliverToBoxAdapter(child: SizedBox(height: 48)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCategoryCard(dynamic sector) {
    final iconData = _getSectorIcon(sector.code);
    final cardColor = _getSectorColor(sector.code);

    return GestureDetector(
      onTap: () {
        _searchController.setSectorFilter(sector.id);
        Get.to(() => const SearchFilterScreen());
      },
      child: Container(
        decoration: BoxDecoration(
          color: cardColor,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 8,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(iconData, size: 48, color: Colors.white),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: Text(
                sector.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w500,
                  fontSize: 14,
                  height: 1.5,
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

  Color _getSectorColor(String code) {
    final colors = {
      'BAT': const Color(0xFF007BFF),
      'ELEC': const Color(0xFFF59E0B),
      'PLOMB': const Color(0xFF3B82F6),
      'MENU': const Color(0xFF8B4513),
      'PEIN': const Color(0xFF28A745),
      'JAR': const Color(0xFF22C55E),
      'AUTO': const Color(0xFF6366F1),
      'TECH': const Color(0xFF8B5CF6),
      'AMEN': const Color(0xFFEC4899),
      'CLEAN': const Color(0xFF14B8A6),
      'SECU': const Color(0xFFEF4444),
      'ART': const Color(0xFFFF4500),
    };
    return colors[code] ?? const Color(0xFF1A1A3E);
  }
}
