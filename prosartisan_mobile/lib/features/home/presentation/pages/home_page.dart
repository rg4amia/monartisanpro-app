import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/cards/promotional_card.dart';
import '../../../../shared/widgets/cards/category_card.dart';
import '../../../../shared/widgets/cards/service_card.dart';
import '../../../../shared/widgets/common/search_bar.dart';
import '../../../../shared/widgets/navigation/bottom_nav_bar.dart';
import '../../../../shared/widgets/buttons/icon_button.dart';
import '../controllers/home_controller.dart';

/// Page d'accueil principale de l'application ProSartisan
/// Implémente le design system avec:
/// 1. Header (Welcome + nom + notification icon)
/// 2. Promotional banner (gradient card)
/// 3. Search bar + filter button
/// 4. "Categories" section → Grid 3 colonnes de category cards
/// 5. "Popular Services" section → Liste verticale ou carousel de service cards
/// 6. Bottom Navigation (fixed)
class HomePage extends GetView<HomeController> {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      body: SafeArea(
        child: Column(
          children: [
            // Contenu principal avec scroll
            Expanded(
              child: SingleChildScrollView(
                padding: AppSpacing.screenPaddingAll,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Header avec salutation et notifications
                    _buildHeader(),
                    
                    const SizedBox(height: AppSpacing.xl),
                    
                    // Carte promotionnelle
                    _buildPromotionalSection(),
                    
                    const SizedBox(height: AppSpacing.xl),
                    
                    // Barre de recherche
                    _buildSearchSection(),
                    
                    const SizedBox(height: AppSpacing.xl),
                    
                    // Section Catégories
                    _buildCategoriesSection(),
                    
                    const SizedBox(height: AppSpacing.xl),
                    
                    // Section Services Populaires
                    _buildPopularServicesSection(),
                    
                    // Espacement pour la navigation inférieure
                    const SizedBox(height: AppSpacing.bottomNavHeight),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      
      // Navigation inférieure
      bottomNavigationBar: Obx(() => CustomBottomNavBar(
        items: DefaultBottomNavItems.items,
        currentRoute: controller.currentRoute.value,
        onItemTapped: controller.onNavItemTapped,
      )),
    );
  }

  /// Header avec salutation et bouton notifications
  Widget _buildHeader() {
    return Obx(() => Row(
      children: [
        // Salutation
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Bonjour 👋',
                style: AppTypography.body.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                controller.userName.value,
                style: AppTypography.h3.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
        
        // Bouton notifications
        CustomIconButton(
          icon: Icons.notifications_outlined,
          onPressed: controller.onNotificationPressed,
          backgroundColor: AppColors.overlayLight,
          iconColor: AppColors.textPrimary,
          badgeCount: controller.notificationCount.value,
          hasShadow: true,
        ),
      ],
    ));
  }

  /// Section carte promotionnelle
  Widget _buildPromotionalSection() {
    return Obx(() => PromotionalCard(
      discount: controller.currentPromotion.value.discount,
      title: controller.currentPromotion.value.title,
      subtitle: controller.currentPromotion.value.subtitle,
      imagePath: controller.currentPromotion.value.imagePath,
      onTap: controller.onPromotionTapped,
      gradientColors: AppColors.promotionalGradient,
    ));
  }

  /// Section barre de recherche
  Widget _buildSearchSection() {
    return SearchBarWidget(
      hintText: 'Rechercher tous les services...',
      onChanged: controller.onSearchChanged,
      onSubmitted: controller.onSearchSubmitted,
      onFilterPressed: controller.onFilterPressed,
      showFilter: true,
    );
  }

  /// Section des catégories
  Widget _buildCategoriesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // En-tête de section
        _buildSectionHeader(
          title: 'Catégories',
          onSeeAllPressed: controller.onSeeAllCategoriesPressed,
        ),
        
        const SizedBox(height: AppSpacing.base),
        
        // Grille de catégories
        Obx(() => CategoryGrid(
          categories: controller.categories,
          activeCategory: controller.selectedCategoryId.value,
          onCategorySelected: controller.onCategorySelected,
          crossAxisCount: 3,
          childAspectRatio: 1.0,
        )),
      ],
    );
  }

  /// Section des services populaires
  Widget _buildPopularServicesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // En-tête de section
        _buildSectionHeader(
          title: 'Services Populaires',
          onSeeAllPressed: controller.onSeeAllServicesPressed,
        ),
        
        const SizedBox(height: AppSpacing.base),
        
        // Liste des services
        Obx(() {
          if (controller.isLoadingServices.value) {
            return _buildServicesLoading();
          }
          
          if (controller.popularServices.isEmpty) {
            return _buildEmptyServices();
          }
          
          return ServiceCardList(
            services: controller.popularServices,
            onServiceTap: controller.onServiceTapped,
            onFavoriteTap: controller.onServiceFavoriteToggled,
            horizontal: false,
          );
        }),
      ],
    );
  }

  /// En-tête de section avec titre et bouton "Voir tout"
  Widget _buildSectionHeader({
    required String title,
    VoidCallback? onSeeAllPressed,
  }) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        if (onSeeAllPressed != null)
          GestureDetector(
            onTap: onSeeAllPressed,
            child: Text(
              'Voir tout',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.accentPrimary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }

  /// Widget de chargement pour les services
  Widget _buildServicesLoading() {
    return Column(
      children: List.generate(3, (index) => Container(
        height: AppSpacing.serviceCardHeight,
        margin: const EdgeInsets.only(bottom: AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.overlayLight,
          borderRadius: AppRadius.cardRadius,
        ),
        child: const Center(
          child: CircularProgressIndicator(
            color: AppColors.accentPrimary,
          ),
        ),
      )),
    );
  }

  /// Widget d'état vide pour les services
  Widget _buildEmptyServices() {
    return Container(
      height: 200,
      decoration: BoxDecoration(
        color: AppColors.overlayLight,
        borderRadius: AppRadius.cardRadius,
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.search_off,
              size: 48,
              color: AppColors.textMuted,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              'Aucun service trouvé',
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Essayez de modifier vos critères de recherche',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textMuted,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

/// Modèle pour les promotions
class PromotionModel {
  final String id;
  final String discount;
  final String title;
  final String subtitle;
  final String? imagePath;

  const PromotionModel({
    required this.id,
    required this.discount,
    required this.title,
    required this.subtitle,
    this.imagePath,
  });
}

/// Extension pour les données de test
extension HomePageTestData on HomePage {
  static List<CategoryModel> get mockCategories => [
    const CategoryModel(
      id: 'plumbing',
      name: 'Plomberie',
      icon: Icons.plumbing,
      iconColor: AppColors.accentPrimary,
    ),
    const CategoryModel(
      id: 'electrical',
      name: 'Électricité',
      icon: Icons.electrical_services,
      iconColor: AppColors.accentWarning,
    ),
    const CategoryModel(
      id: 'cleaning',
      name: 'Ménage',
      icon: Icons.cleaning_services,
      iconColor: AppColors.accentSuccess,
    ),
    const CategoryModel(
      id: 'gardening',
      name: 'Jardinage',
      icon: Icons.grass,
      iconColor: AppColors.accentSuccess,
    ),
    const CategoryModel(
      id: 'painting',
      name: 'Peinture',
      icon: Icons.format_paint,
      iconColor: AppColors.accentDanger,
    ),
    const CategoryModel(
      id: 'carpentry',
      name: 'Menuiserie',
      icon: Icons.carpenter,
      iconColor: AppColors.accentWarning,
    ),
  ];

  static List<ServiceModel> get mockServices => [
    ServiceModel(
      id: '1',
      title: 'Réparation de plomberie',
      description: 'Service de réparation rapide pour tous vos problèmes de plomberie',
      price: 25000,
      currency: 'FCFA',
      imageUrl: 'https://example.com/plumbing.jpg',
      rating: 4.8,
      reviewCount: 156,
      provider: const ProviderModel(
        id: 'p1',
        name: 'Jean Dupont',
        role: 'Plombier Expert',
        isVerified: true,
        rating: 4.9,
      ),
      isFavorite: false,
    ),
    ServiceModel(
      id: '2',
      title: 'Installation électrique',
      description: 'Installation et maintenance électrique professionnelle',
      price: 35000,
      currency: 'FCFA',
      imageUrl: 'https://example.com/electrical.jpg',
      rating: 4.6,
      reviewCount: 89,
      provider: const ProviderModel(
        id: 'p2',
        name: 'Marie Martin',
        role: 'Électricienne',
        isVerified: true,
        rating: 4.7,
      ),
      isFavorite: true,
    ),
    ServiceModel(
      id: '3',
      title: 'Ménage complet',
      description: 'Service de ménage complet pour votre domicile',
      price: 15000,
      currency: 'FCFA',
      imageUrl: 'https://example.com/cleaning.jpg',
      rating: 4.9,
      reviewCount: 234,
      provider: const ProviderModel(
        id: 'p3',
        name: 'Sophie Dubois',
        role: 'Femme de ménage',
        isVerified: false,
        rating: 4.8,
      ),
      isFavorite: false,
    ),
  ];

  static PromotionModel get mockPromotion => const PromotionModel(
    id: 'promo1',
    discount: '20%',
    title: 'Offre Spéciale!',
    subtitle: 'Réduction sur tous les services aujourd\'hui',
    imagePath: 'assets/images/worker_illustration.png',
  );
}