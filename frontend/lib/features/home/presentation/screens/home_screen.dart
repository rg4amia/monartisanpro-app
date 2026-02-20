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
      debugPrint('Error initializing search controller: $e');
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
      backgroundColor: AppColors.darkBackground,
      body: RefreshIndicator(
        color: AppColors.darkAccentPrimary,
        backgroundColor: AppColors.darkCard,
        onRefresh: _refreshData,
        child: SafeArea(
          child: CustomScrollView(
            slivers: [
              // Top Bar - Design System: 56-64px height
              _buildTopBar(),

              // Welcome Section with Search
              _buildWelcomeSection(),

              // Promotional Card (Nearby Artisans)
              _buildPromotionalCard(),

              // Categories Section Header
              _buildSectionHeader(),

              // Categories Grid - Design System: 3 columns, 12px gap
              _buildCategoriesGrid(),

              const SliverToBoxAdapter(child: SizedBox(height: Spacing.xxxxl)),
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
          borderRadius: BorderRadius.circular(Spacing.radiusLg),
          boxShadow: const [
            BoxShadow(
              color: Color(0x4D000000), // rgba(0, 0, 0, 0.3)
              blurRadius: 8,
              offset: Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(iconData, size: 48, color: AppColors.darkTextPrimary),
            const SizedBox(height: Spacing.sm),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: Spacing.sm),
              child: Text(
                sector.name,
                style: const TextStyle(
                  color: AppColors.darkTextPrimary,
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

  // Top Bar Component - Design System Pattern
  Widget _buildTopBar() {
    return SliverAppBar(
      floating: true,
      backgroundColor: AppColors.darkBackground,
      elevation: 0,
      toolbarHeight: 64,
      title: Row(
        children: [
          Icon(
            Icons.build_circle,
            color: AppColors.darkAccentHighlight,
            size: 28,
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
        // Notification Icon Button - Design System: 44x44 touch target
        Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          child: IconButton(
            icon: const Icon(
              Icons.notifications_outlined,
              color: AppColors.darkTextPrimary,
              size: 24,
            ),
            onPressed: () {
              // Navigate to notifications
              Get.snackbar(
                'Notifications',
                'Fonctionnalité à venir',
                backgroundColor: AppColors.darkCard,
                colorText: AppColors.darkTextPrimary,
              );
            },
          ),
        ),
        const SizedBox(width: Spacing.sm),
        // User Avatar - Design System: 40-48px
        Obx(() {
          final user = _authController.currentUser.value;
          return Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.overlayMedium, width: 2),
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
          );
        }),
        const SizedBox(width: Spacing.lg),
      ],
    );
  }

  // Welcome Section - Design System Pattern
  Widget _buildWelcomeSection() {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Welcome Header - Design System: h2 (24px) for greeting
            Obx(() {
              final user = _authController.currentUser.value;
              final firstName = user?.name.split(' ').first ?? 'Invité';
              return Text(
                'Bonjour $firstName! 👋',
                style: const TextStyle(
                  color: AppColors.darkTextPrimary,
                  fontWeight: FontWeight.w700,
                  fontSize: 24,
                  height: 1.2,
                ),
              );
            }),
            const SizedBox(height: Spacing.sm),
            const Text(
              'Quel artisan cherchez-vous aujourd\'hui?',
              style: TextStyle(
                color: AppColors.darkTextSecondary,
                fontSize: 14,
                height: 1.5,
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Search Bar - Design System: 12px border radius, card background
            GestureDetector(
              onTap: () => Get.to(() => const SearchFilterScreen()),
              child: Container(
                height: 48,
                padding: const EdgeInsets.symmetric(horizontal: Spacing.base),
                decoration: BoxDecoration(
                  color: AppColors.darkCard,
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  border: Border.all(color: AppColors.overlayLight, width: 1),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x4D000000), // rgba(0, 0, 0, 0.3)
                      blurRadius: 6,
                      offset: Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.search,
                      color: AppColors.darkTextTertiary,
                      size: 24,
                    ),
                    const SizedBox(width: Spacing.md),
                    const Expanded(
                      child: Text(
                        'Rechercher un artisan...',
                        style: TextStyle(
                          color: AppColors.darkTextTertiary,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    // Filter Button - Design System: Icon button with accent background
                    Container(
                      padding: const EdgeInsets.all(Spacing.sm),
                      decoration: BoxDecoration(
                        color: AppColors.darkAccentPrimary,
                        borderRadius: BorderRadius.circular(Spacing.radiusSm),
                      ),
                      child: const Icon(
                        Icons.tune,
                        size: 20,
                        color: AppColors.darkTextPrimary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: Spacing.base),

            // Map View Button - Design System: Secondary button
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => Get.to(() => const MapSearchScreen()),
                icon: const Icon(Icons.map_outlined, size: 20),
                label: const Text(
                  'Voir sur la carte',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.darkTextPrimary,
                  side: const BorderSide(
                    color: AppColors.darkAccentPrimary,
                    width: 1,
                  ),
                  backgroundColor: AppColors.overlayLight,
                  padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Promotional Card - Design System Pattern
  Widget _buildPromotionalCard() {
    return Obx(() {
      if (_searchController.nearbyArtisansCount > 0) {
        return SliverToBoxAdapter(
          child: Container(
            margin: const EdgeInsets.symmetric(
              horizontal: Spacing.base,
              vertical: Spacing.base,
            ),
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.darkAccentPrimary, Color(0xFF4A5FD9)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(Spacing.radiusLg),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x4D5B7FFF), // Glow effect
                  blurRadius: 20,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(Spacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.overlayMedium,
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  ),
                  child: const Icon(
                    Icons.location_on,
                    color: AppColors.darkAccentHighlight,
                    size: 32,
                  ),
                ),
                const SizedBox(width: Spacing.base),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${_searchController.nearbyArtisansCount} artisans à proximité',
                        style: const TextStyle(
                          color: AppColors.darkTextPrimary,
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'À moins de 2km de vous',
                        style: TextStyle(
                          color: AppColors.darkTextPrimary.withValues(
                            alpha: 0.9,
                          ),
                          fontSize: 12,
                          height: 1.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.arrow_forward_ios,
                  color: AppColors.darkTextPrimary,
                  size: 16,
                ),
              ],
            ),
          ),
        );
      }
      return const SliverToBoxAdapter(child: SizedBox.shrink());
    });
  }

  // Section Header - Design System Pattern
  Widget _buildSectionHeader() {
    return const SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          Spacing.lg,
          Spacing.xl,
          Spacing.lg,
          Spacing.base,
        ),
        child: Text(
          'Catégories de métiers',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: AppColors.darkTextPrimary,
            height: 1.2,
          ),
        ),
      ),
    );
  }

  // Categories Grid - Design System: 3 columns, 12px gap
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
            childAspectRatio: 1.0,
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

  IconData _getSectorIcon(String code) {
    // Map sector codes from database to icons
    final icons = {
      '1': Icons.directions_car, // MÉCANIQUE & AUTOMOBILE
      '2': Icons.electrical_services, // ÉLECTRICITÉ & ÉNERGIE
      '3': Icons.plumbing, // PLOMBERIE & FLUIDES
      '4': Icons.construction, // BÂTIMENT & TRAVAUX PUBLICS (BTP)
      '5': Icons.carpenter, // MENUISERIE & BOIS
      '6': Icons.build, // MÉTALLURGIE & SOUDURE
      '7': Icons.palette, // ARTISANAT & MÉTIERS CRÉATIFS
      '8': Icons.devices, // NUMÉRIQUE & TECHNIQUE
      '9': Icons.ac_unit, // FROID, CLIMATISATION & ÉQUIPEMENTS
      '10': Icons.cleaning_services, // SERVICES & MÉTIERS DE PROXIMITÉ
      '11': Icons.security, // SÉCURITÉ & INSTALLATION
      '12': Icons.water_drop, // ASSAINISSEMENT & EAU
    };
    return icons[code] ?? Icons.build;
  }

  // Design System Colors for Categories
  Color _getSectorColor(String code) {
    // Map sector codes from database to colors
    final colors = {
      '1': const Color(0xFF6366F1), // MÉCANIQUE & AUTOMOBILE - Indigo
      '2': const Color(0xFFF59E0B), // ÉLECTRICITÉ & ÉNERGIE - Orange/Yellow
      '3': const Color(0xFF3B82F6), // PLOMBERIE & FLUIDES - Light Blue
      '4': AppColors.darkAccentPrimary, // BÂTIMENT & TRAVAUX PUBLICS - Blue
      '5': const Color(0xFF8B4513), // MENUISERIE & BOIS - Brown
      '6': const Color(0xFF78716C), // MÉTALLURGIE & SOUDURE - Gray
      '7': const Color(0xFFEC4899), // ARTISANAT & MÉTIERS CRÉATIFS - Pink
      '8': const Color(0xFF8B5CF6), // NUMÉRIQUE & TECHNIQUE - Purple
      '9': const Color(0xFF06B6D4), // FROID, CLIMATISATION - Cyan
      '10': AppColors.darkAccentSecondary, // SERVICES & MÉTIERS - Green
      '11': AppColors.darkAccentDanger, // SÉCURITÉ & INSTALLATION - Red
      '12': const Color(0xFF14B8A6), // ASSAINISSEMENT & EAU - Teal
    };
    return colors[code] ?? AppColors.darkCard;
  }
}
