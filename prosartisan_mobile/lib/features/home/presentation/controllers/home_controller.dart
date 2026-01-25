import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/cards/category_card.dart';
import '../../../../shared/widgets/cards/service_card.dart';
import '../pages/home_page.dart';

/// Contrôleur pour la page d'accueil
/// Gère l'état et la logique métier de l'écran d'accueil
class HomeController extends GetxController {
  // ==================== OBSERVABLES ====================

  /// Nom de l'utilisateur connecté
  final userName = 'Amadou Diallo'.obs;

  /// Nombre de notifications non lues
  final notificationCount = 3.obs;

  /// Route actuelle pour la navigation
  final currentRoute = 'home'.obs;

  /// Promotion actuelle
  final currentPromotion = HomePageTestData.mockPromotion.obs;

  /// Catégories disponibles
  final RxList<CategoryModel> categories = <CategoryModel>[].obs;

  /// Services populaires
  final RxList<ServiceModel> popularServices = <ServiceModel>[].obs;

  /// ID de la catégorie sélectionnée
  final selectedCategoryId = RxnString();

  /// État de chargement des services
  final isLoadingServices = false.obs;

  /// Terme de recherche actuel
  final searchQuery = ''.obs;

  // ==================== LIFECYCLE ====================

  @override
  void onInit() {
    super.onInit();
    _initializeData();
  }

  @override
  void onReady() {
    super.onReady();
    _loadPopularServices();
  }

  // ==================== PRIVATE METHODS ====================

  /// Initialise les données de base
  void _initializeData() {
    categories.assignAll(HomePageTestData.mockCategories);
  }

  /// Charge les services populaires
  Future<void> _loadPopularServices() async {
    try {
      isLoadingServices.value = true;

      // Simulation d'un appel API
      await Future.delayed(const Duration(seconds: 1));

      popularServices.assignAll(HomePageTestData.mockServices);
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les services',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoadingServices.value = false;
    }
  }

  // ==================== EVENT HANDLERS ====================

  /// Gère le tap sur les notifications
  void onNotificationPressed() {
    Get.toNamed('/notifications');
  }

  /// Gère le tap sur la carte promotionnelle
  void onPromotionTapped() {
    Get.toNamed('/promotions', arguments: currentPromotion.value);
  }

  /// Gère les changements dans la barre de recherche
  void onSearchChanged(String query) {
    searchQuery.value = query;
    _filterServices(query);
  }

  /// Gère la soumission de la recherche
  void onSearchSubmitted(String query) {
    Get.toNamed('/search', arguments: {'query': query});
  }

  /// Gère le tap sur le bouton filtre
  void onFilterPressed() {
    Get.bottomSheet(
      _buildFilterBottomSheet(),
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
    );
  }

  /// Gère la sélection d'une catégorie
  void onCategorySelected(CategoryModel category) {
    if (selectedCategoryId.value == category.id) {
      selectedCategoryId.value = null;
    } else {
      selectedCategoryId.value = category.id;
    }
    _filterServicesByCategory();
  }

  /// Gère le tap sur "Voir toutes les catégories"
  void onSeeAllCategoriesPressed() {
    Get.toNamed('/categories');
  }

  /// Gère le tap sur "Voir tous les services"
  void onSeeAllServicesPressed() {
    Get.toNamed('/services');
  }

  /// Gère le tap sur un service
  void onServiceTapped(ServiceModel service) {
    Get.toNamed('/service-details', arguments: service);
  }

  /// Gère le toggle des favoris
  void onServiceFavoriteToggled(ServiceModel service) {
    final index = popularServices.indexWhere((s) => s.id == service.id);
    if (index != -1) {
      final updatedService = ServiceModel(
        id: service.id,
        title: service.title,
        description: service.description,
        price: service.price,
        currency: service.currency,
        imageUrl: service.imageUrl,
        rating: service.rating,
        reviewCount: service.reviewCount,
        provider: service.provider,
        status: service.status,
        isFavorite: !service.isFavorite,
        tags: service.tags,
      );
      popularServices[index] = updatedService;
    }
  }

  /// Gère la navigation entre les onglets
  void onNavItemTapped(String route) {
    currentRoute.value = route;

    switch (route) {
      case 'home':
        // Déjà sur la page d'accueil
        break;
      case 'bookings':
        Get.toNamed('/bookings');
        break;
      case 'categories':
        Get.toNamed('/categories');
        break;
      case 'chat':
        Get.toNamed('/chat');
        break;
      case 'profile':
        Get.toNamed('/profile');
        break;
    }
  }

  // ==================== HELPER METHODS ====================

  /// Filtre les services par terme de recherche
  void _filterServices(String query) {
    if (query.isEmpty) {
      popularServices.assignAll(HomePageTestData.mockServices);
    } else {
      final filtered = HomePageTestData.mockServices
          .where(
            (service) =>
                service.title.toLowerCase().contains(query.toLowerCase()) ||
                service.description.toLowerCase().contains(query.toLowerCase()),
          )
          .toList();
      popularServices.assignAll(filtered);
    }
  }

  /// Filtre les services par catégorie
  void _filterServicesByCategory() {
    if (selectedCategoryId.value == null) {
      popularServices.assignAll(HomePageTestData.mockServices);
    } else {
      // Ici vous pourriez filtrer par catégorie
      // Pour l'exemple, on garde tous les services
      popularServices.assignAll(HomePageTestData.mockServices);
    }
  }

  /// Construit la bottom sheet des filtres
  Widget _buildFilterBottomSheet() {
    return Container(
      height: Get.height * 0.6,
      decoration: const BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(24),
          topRight: Radius.circular(24),
        ),
      ),
      child: Column(
        children: [
          // Handle
          Container(
            width: 40,
            height: 4,
            margin: const EdgeInsets.only(top: 12),
            decoration: BoxDecoration(
              color: AppColors.overlayMedium,
              borderRadius: BorderRadius.circular(2),
            ),
          ),

          // Titre
          Padding(
            padding: const EdgeInsets.all(20),
            child: Text(
              'Filtres',
              style: AppTypography.h4.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),

          // Contenu des filtres
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Catégories',
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Liste des catégories
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: categories.map((category) {
                      final isSelected =
                          selectedCategoryId.value == category.id;
                      return GestureDetector(
                        onTap: () => onCategorySelected(category),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: isSelected
                                ? AppColors.accentPrimary
                                : AppColors.overlayLight,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            category.name,
                            style: TextStyle(
                              color: isSelected
                                  ? Colors.white
                                  : AppColors.textPrimary,
                              fontWeight: isSelected
                                  ? FontWeight.w600
                                  : FontWeight.normal,
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),

                  const Spacer(),

                  // Boutons d'action
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            selectedCategoryId.value = null;
                            _filterServicesByCategory();
                          },
                          child: const Text('Réinitialiser'),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {
                            Get.back();
                            _filterServicesByCategory();
                          },
                          child: const Text('Appliquer'),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ==================== PUBLIC METHODS ====================

  /// Rafraîchit les données
  Future<void> refreshData() async {
    await _loadPopularServices();
  }

  /// Met à jour le nom d'utilisateur
  void updateUserName(String name) {
    userName.value = name;
  }

  /// Met à jour le nombre de notifications
  void updateNotificationCount(int count) {
    notificationCount.value = count;
  }
}
