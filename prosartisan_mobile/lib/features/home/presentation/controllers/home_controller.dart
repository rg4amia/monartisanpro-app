import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/models/category_model.dart';
import '../../data/models/service_model.dart';
import '../../data/repositories/home_repository.dart';
import '../pages/home_page.dart';

/// Contrôleur pour la page d'accueil
/// Gère l'état et la logique métier de l'écran d'accueil
class HomeController extends GetxController {
  final HomeRepository _homeRepository = Get.find<HomeRepository>();

  // ==================== OBSERVABLES ====================

  /// Nom de l'utilisateur connecté
  final userName = 'Utilisateur'.obs;

  /// Nombre de notifications non lues
  final notificationCount = 0.obs;

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

  /// État de chargement des catégories
  final isLoadingCategories = false.obs;

  /// État de chargement du profil utilisateur
  final isLoadingProfile = false.obs;

  /// Terme de recherche actuel
  final searchQuery = ''.obs;

  /// Message d'erreur
  final errorMessage = ''.obs;

  // ==================== LIFECYCLE ====================

  @override
  void onInit() {
    super.onInit();
    _initializeData();
  }

  @override
  void onReady() {
    super.onReady();
    _loadAllData();
  }

  // ==================== PRIVATE METHODS ====================

  /// Initialise les données de base
  void _initializeData() {
    // Load cached data or set defaults
    _loadUserProfile();
    _loadNotificationsCount();
  }

  /// Charge toutes les données nécessaires
  Future<void> _loadAllData() async {
    await Future.wait([_loadCategories(), _loadPopularServices()]);
  }

  /// Charge le profil utilisateur
  Future<void> _loadUserProfile() async {
    try {
      isLoadingProfile.value = true;
      final profile = await _homeRepository.getUserProfile();

      if (profile.isNotEmpty) {
        userName.value = profile['name'] ?? 'Utilisateur';
      }
    } catch (e) {
      // Keep default name if profile loading fails
      print('Failed to load user profile: $e');
    } finally {
      isLoadingProfile.value = false;
    }
  }

  /// Charge le nombre de notifications
  Future<void> _loadNotificationsCount() async {
    try {
      final count = await _homeRepository.getNotificationsCount();
      notificationCount.value = count;
    } catch (e) {
      // Keep default count if notifications loading fails
      print('Failed to load notifications count: $e');
    }
  }

  /// Charge les catégories depuis l'API
  Future<void> _loadCategories() async {
    try {
      isLoadingCategories.value = true;
      errorMessage.value = '';

      final apiCategories = await _homeRepository.getCategories();
      categories.assignAll(apiCategories);
    } catch (e) {
      errorMessage.value = 'Impossible de charger les catégories';
      // Fallback to mock data
      categories.assignAll(HomePageTestData.mockCategories);

      Get.snackbar(
        'Erreur',
        'Impossible de charger les catégories',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: Colors.white,
      );
    } finally {
      isLoadingCategories.value = false;
    }
  }

  /// Charge les services populaires depuis l'API
  Future<void> _loadPopularServices() async {
    try {
      isLoadingServices.value = true;
      errorMessage.value = '';

      final services = await _homeRepository.getPopularServices(
        limit: 10,
        category: selectedCategoryId.value,
      );

      popularServices.assignAll(services);
    } catch (e) {
      errorMessage.value = 'Impossible de charger les services';
      // Fallback to mock data
      popularServices.assignAll(HomePageTestData.mockServices);

      Get.snackbar(
        'Erreur',
        'Impossible de charger les services',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: Colors.white,
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
    _searchServices(query);
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
    _loadPopularServices(); // Reload services with new category filter
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
      final updatedService = service.copyWith(isFavorite: !service.isFavorite);
      popularServices[index] = updatedService;

      // TODO: Call API to update favorite status
      _updateFavoriteStatus(service.id, !service.isFavorite);
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
        Get.toNamed(AppRoutes.bookings);
        break;
      case 'categories':
        Get.toNamed(AppRoutes.categories);
        break;
      case 'chat':
        Get.toNamed(AppRoutes.chat);
        break;
      case 'profile':
        Get.toNamed(AppRoutes.profile);
        break;
    }
  }

  // ==================== HELPER METHODS ====================

  /// Recherche des services
  Future<void> _searchServices(String query) async {
    if (query.isEmpty) {
      await _loadPopularServices();
      return;
    }

    try {
      isLoadingServices.value = true;

      final services = await _homeRepository.getAllServices(
        search: query,
        category: selectedCategoryId.value,
      );

      popularServices.assignAll(services);
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de rechercher les services',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: Colors.white,
      );
    } finally {
      isLoadingServices.value = false;
    }
  }

  /// Met à jour le statut favori d'un service
  Future<void> _updateFavoriteStatus(String serviceId, bool isFavorite) async {
    try {
      // TODO: Implement API call to update favorite status
      // await _homeRepository.updateFavoriteStatus(serviceId, isFavorite);
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de mettre à jour les favoris',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: Colors.white,
      );
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
                  Obx(
                    () => Wrap(
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
                  ),

                  const Spacer(),

                  // Boutons d'action
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            selectedCategoryId.value = null;
                            _loadPopularServices();
                          },
                          child: const Text('Réinitialiser'),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {
                            Get.back();
                            _loadPopularServices();
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
    await _loadAllData();
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
