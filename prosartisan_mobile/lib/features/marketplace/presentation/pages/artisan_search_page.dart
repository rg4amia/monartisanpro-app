import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
import '../controllers/artisan_search_controller.dart';
import '../widgets/category_filter_widget.dart';
import '../widgets/search_radius_slider.dart';

class ArtisanSearchPage extends GetView<ArtisanSearchController> {
  const ArtisanSearchPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Rechercher des artisans',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(Icons.filter_list, color: AppColors.textPrimary),
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
                CircularProgressIndicator(color: AppColors.accentPrimary),
                SizedBox(height: AppSpacing.md),
                Text(
                  'Obtention de votre localisation...',
                  style: AppTypography.body.copyWith(
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
            subtitle:
                'Veuillez activer la géolocalisation pour rechercher des artisans',
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
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.cardBg,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(color: AppColors.overlayLight),
                ),
                padding: EdgeInsets.all(AppSpacing.md),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CategoryFilterWidget(
                      selectedCategory: controller.selectedCategory,
                      onCategoryChanged: controller.setCategory,
                    ),
                    SizedBox(height: AppSpacing.sm),
                    SearchRadiusSlider(
                      value: controller.searchRadius,
                      onChanged: controller.setSearchRadius,
                    ),
                  ],
                ),
              ),
            ),

            // Loading overlay
            if (controller.isLoading)
              Positioned(
                bottom: 16,
                left: 16,
                right: 16,
                child: Container(
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    border: Border.all(color: AppColors.overlayLight),
                  ),
                  padding: EdgeInsets.all(AppSpacing.base),
                  child: Row(
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.accentPrimary,
                        ),
                      ),
                      SizedBox(width: AppSpacing.md),
                      Text(
                        'Recherche en cours...',
                        style: AppTypography.body.copyWith(
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

            // Results summary
            if (!controller.isLoading && controller.artisans.isNotEmpty)
              Positioned(
                bottom: 16,
                left: 16,
                right: 16,
                child: Container(
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    border: Border.all(color: AppColors.overlayLight),
                  ),
                  padding: EdgeInsets.all(AppSpacing.base),
                  child: Row(
                    children: [
                      Icon(Icons.person, color: AppColors.accentPrimary),
                      SizedBox(width: AppSpacing.sm),
                      Text(
                        '${controller.artisans.length} artisan(s) trouvé(s)',
                        style: AppTypography.body.copyWith(
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const Spacer(),
                      TextButton(
                        onPressed: _showArtisansList,
                        child: Text(
                          'Voir la liste',
                          style: AppTypography.body.copyWith(
                            color: AppColors.accentPrimary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      }),
      floatingActionButton: FloatingActionButton(
        onPressed: () => Get.toNamed('/mission/create'),
        backgroundColor: AppColors.accentPrimary,
        tooltip: 'Créer une mission',
        child: Icon(Icons.add, color: AppColors.textPrimary),
      ),
    );
  }

  void _showFilterBottomSheet() {
    Get.bottomSheet(
      Container(
        padding: EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.xl),
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Filtres de recherche',
              style: AppTypography.h3.copyWith(color: AppColors.textPrimary),
            ),
            SizedBox(height: AppSpacing.lg),

            Text(
              'Catégorie d\'artisan',
              style: AppTypography.sectionTitle.copyWith(
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: AppSpacing.sm),
            Obx(
              () => CategoryFilterWidget(
                selectedCategory: controller.selectedCategory,
                onCategoryChanged: controller.setCategory,
                showAllOption: true,
              ),
            ),

            SizedBox(height: AppSpacing.lg),

            Text(
              'Rayon de recherche',
              style: AppTypography.sectionTitle.copyWith(
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: AppSpacing.sm),
            Obx(
              () => SearchRadiusSlider(
                value: controller.searchRadius,
                onChanged: controller.setSearchRadius,
                showLabel: true,
              ),
            ),

            SizedBox(height: AppSpacing.lg),

            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Get.back();
                  controller.searchArtisans();
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.accentPrimary,
                  foregroundColor: AppColors.textPrimary,
                  padding: EdgeInsets.symmetric(vertical: AppSpacing.base),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                ),
                child: Text(
                  'Appliquer les filtres',
                  style: AppTypography.button.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),
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
        padding: EdgeInsets.all(AppSpacing.lg),
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.xl),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Artisans trouvés',
                  style: AppTypography.h3.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),
                const Spacer(),
                IconButton(
                  onPressed: () => Get.back(),
                  icon: Icon(Icons.close, color: AppColors.textPrimary),
                ),
              ],
            ),
            SizedBox(height: AppSpacing.base),

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

                    return Container(
                      margin: EdgeInsets.only(bottom: AppSpacing.sm),
                      decoration: BoxDecoration(
                        color: AppColors.secondaryBg,
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        border: Border.all(color: AppColors.overlayLight),
                      ),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: isGolden
                              ? AppColors.accentWarning
                              : AppColors.accentPrimary,
                          child: Text(
                            artisan.category.displayName[0],
                            style: AppTypography.body.copyWith(
                              color: AppColors.textPrimary,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        title: Text(
                          artisan.businessName ?? artisan.email,
                          style: AppTypography.body.copyWith(
                            fontWeight: FontWeight.bold,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              artisan.category.displayName,
                              style: AppTypography.bodySmall.copyWith(
                                color: AppColors.textSecondary,
                              ),
                            ),
                            SizedBox(height: AppSpacing.xs),
                            Row(
                              children: [
                                Icon(
                                  Icons.star,
                                  color: AppColors.accentWarning,
                                  size: 16,
                                ),
                                SizedBox(width: AppSpacing.xs),
                                Text(
                                  artisan.averageRating.toStringAsFixed(1),
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                SizedBox(width: AppSpacing.sm),
                                Text(
                                  'Score: ${artisan.nzassaScore.toInt()}',
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                SizedBox(width: AppSpacing.sm),
                                Text(
                                  '${(distance / 1000).toStringAsFixed(1)} km',
                                  style: AppTypography.bodySmall.copyWith(
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        trailing: isGolden
                            ? Icon(Icons.star, color: AppColors.accentWarning)
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
