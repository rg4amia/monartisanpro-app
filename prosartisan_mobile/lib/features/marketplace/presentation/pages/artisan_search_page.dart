import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
import '../controllers/artisan_search_controller.dart';
import '../widgets/category_filter_widget.dart';
import '../widgets/search_radius_slider.dart';

class ArtisanSearchPage extends GetView<ArtisanSearchController> {
  const ArtisanSearchPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Rechercher des artisans',
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(
              Icons.filter_list,
              color: AppColors.textLight,
            ),
            onPressed: _showFilterBottomSheet,
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading && controller.currentLocation == null) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CircularProgressIndicator(
                  color: AppColors.primary,
                ),
                SizedBox(height: AppSpacing.md),
                Text(
                  'Obtention de votre localisation...',
                  style: AppTypography.bodyMedium.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          );
        }

        if (controller.currentLocation == null) {
          return EmptyStateCard(
            icon: Icons.location_off,
            title: 'Localisation non disponible',
            subtitle: 'Veuillez activer la géolocalisation pour rechercher des artisans',
          );
        }
                  style: TextStyle(color: Colors.grey),
                ),
              ],
            ),
          );
        }

        return Stack(
          children: [
            // Google Map
            GoogleMap(
              onMapCreated: controller.onMapCreated,
              initialCameraPosition: CameraPosition(
                target: LatLng(
                  controller.currentLocation!.latitude,
                  controller.currentLocation!.longitude,
                ),
                zoom: 14.0,
              ),
              markers: controller.markers,
              myLocationEnabled: true,
              myLocationButtonEnabled: true,
              zoomControlsEnabled: false,
              mapToolbarEnabled: false,
            ),

            // Search filters overlay
            Positioned(
              top: 16,
              left: 16,
              right: 16,
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CategoryFilterWidget(
                        selectedCategory: controller.selectedCategory,
                        onCategoryChanged: controller.setCategory,
                      ),
                      const SizedBox(height: 8),
                      SearchRadiusSlider(
                        value: controller.searchRadius,
                        onChanged: controller.setSearchRadius,
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Loading overlay
            if (controller.isLoading)
              Positioned(
                bottom: 16,
                left: 16,
                right: 16,
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 12),
                        const Text('Recherche en cours...'),
                      ],
                    ),
                  ),
                ),
              ),

            // Results summary
            if (!controller.isLoading && controller.artisans.isNotEmpty)
              Positioned(
                bottom: 16,
                left: 16,
                right: 16,
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Icon(Icons.person, color: Colors.blue[600]),
                        const SizedBox(width: 8),
                        Text(
                          '${controller.artisans.length} artisan(s) trouvé(s)',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        const Spacer(),
                        TextButton(
                          onPressed: _showArtisansList,
                          child: const Text('Voir la liste'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        );
      }),
      floatingActionButton: FloatingActionButton(
        onPressed: () => Get.toNamed('/mission/create'),
        backgroundColor: Colors.blue[600],
        tooltip: 'Créer une mission',
        child: const Icon(Icons.add, color: Colors.white),
      ),
    );
  }

  void _showFilterBottomSheet() {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Filtres de recherche', style: Get.textTheme.headlineSmall),
            const SizedBox(height: 20),

            Text('Catégorie d\'artisan', style: Get.textTheme.titleMedium),
            const SizedBox(height: 8),
            Obx(
              () => CategoryFilterWidget(
                selectedCategory: controller.selectedCategory,
                onCategoryChanged: controller.setCategory,
                showAllOption: true,
              ),
            ),

            const SizedBox(height: 20),

            Text('Rayon de recherche', style: Get.textTheme.titleMedium),
            const SizedBox(height: 8),
            Obx(
              () => SearchRadiusSlider(
                value: controller.searchRadius,
                onChanged: controller.setSearchRadius,
                showLabel: true,
              ),
            ),

            const SizedBox(height: 20),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Get.back();
                  controller.searchArtisans();
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue[600],
                  foregroundColor: Colors.white,
                ),
                child: const Text('Appliquer les filtres'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showArtisansList() {
    Get.bottomSheet(
      Container(
        height: Get.height * 0.7,
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text('Artisans trouvés', style: Get.textTheme.headlineSmall),
                const Spacer(),
                IconButton(
                  onPressed: () => Get.back(),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 16),

            Expanded(
              child: Obx(
                () => ListView.builder(
                  itemCount: controller.artisans.length,
                  itemBuilder: (context, index) {
                    final artisan = controller.artisans[index];
                    final distance = artisan.distanceToLocation(
                      controller.currentLocation!,
                    );
                    final isGolden = artisan.isWithinGoldenRange(
                      controller.currentLocation!,
                    );

                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: isGolden
                              ? Colors.amber
                              : Colors.blue,
                          child: Text(
                            artisan.category.displayName[0],
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        title: Text(
                          artisan.businessName ?? artisan.email,
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(artisan.category.displayName),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                const Icon(
                                  Icons.star,
                                  color: Colors.amber,
                                  size: 16,
                                ),
                                const SizedBox(width: 4),
                                Text(artisan.averageRating.toStringAsFixed(1)),
                                const SizedBox(width: 8),
                                Text('Score: ${artisan.nzassaScore.toInt()}'),
                                const SizedBox(width: 8),
                                Text(
                                  '${(distance / 1000).toStringAsFixed(1)} km',
                                ),
                              ],
                            ),
                          ],
                        ),
                        trailing: isGolden
                            ? const Icon(Icons.star, color: Colors.amber)
                            : null,
                        onTap: () {
                          Get.back();
                          controller.animateToLocation(artisan.location);
                        },
                      ),
                    );
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
