import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/search_controller.dart' as artisan_search;
import 'artisan_profile_screen.dart';

class SearchResultsScreen extends StatelessWidget {
  const SearchResultsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final searchController = Get.find<artisan_search.ArtisanSearchController>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Résultats de recherche'),
      ),
      body: Obx(() {
        if (searchController.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        if (searchController.searchResults.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.search_off,
                  size: 80,
                  color: AppColors.lightTextTertiary,
                ),
                const SizedBox(height: Spacing.lg),
                Text(
                  'Aucun artisan trouvé',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: Spacing.sm),
                Text(
                  'Essayez d\'ajuster vos filtres',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
              ],
            ),
          );
        }

        return Column(
          children: [
            // Results count
            Container(
              padding: const EdgeInsets.all(Spacing.base),
              color: AppColors.lightBackground,
              child: Row(
                children: [
                  Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 20,
                  ),
                  const SizedBox(width: Spacing.sm),
                  Text(
                    '${searchController.searchResults.length} artisan(s) trouvé(s)',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
            ),

            // Results list
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                itemCount: searchController.searchResults.length,
                separatorBuilder: (context, index) =>
                    const SizedBox(height: Spacing.md),
                itemBuilder: (context, index) {
                  final artisan = searchController.searchResults[index];
                  return _ArtisanCard(artisan: artisan);
                },
              ),
            ),
          ],
        );
      }),
    );
  }
}

class _ArtisanCard extends StatelessWidget {
  final dynamic artisan;

  const _ArtisanCard({required this.artisan});

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          Get.to(() => ArtisanProfileScreen(artisanId: artisan.id));
        },
        child: Padding(
          padding: const EdgeInsets.all(Spacing.base),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Avatar
              CircleAvatar(
                radius: 30,
                backgroundImage: artisan.avatar != null
                    ? NetworkImage(artisan.avatar!)
                    : null,
                child: artisan.avatar == null
                    ? Icon(Icons.person, size: 30)
                    : null,
              ),
              const SizedBox(width: Spacing.md),

              // Details
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            artisan.name,
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (artisan.isNearby)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: Spacing.sm,
                              vertical: Spacing.xs,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.goldenMarker.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(Spacing.radiusSm),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.location_on,
                                  size: 14,
                                  color: AppColors.goldenMarker,
                                ),
                                const SizedBox(width: 2),
                                Text(
                                  'Proche',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: AppColors.goldenMarker,
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: Spacing.xs),

                    // Trade
                    Row(
                      children: [
                        Icon(
                          Icons.work_outline,
                          size: 14,
                          color: AppColors.lightTextSecondary,
                        ),
                        const SizedBox(width: Spacing.xs),
                        Text(
                          artisan.tradeName,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: AppColors.lightTextSecondary,
                              ),
                        ),
                        if (artisan.experienceYears > 0) ...[
                          Text(
                            ' • ',
                            style: TextStyle(color: AppColors.lightTextSecondary),
                          ),
                          Text(
                            '${artisan.experienceYears} ans d\'exp.',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.lightTextSecondary,
                                ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: Spacing.sm),

                    // Rating & Distance
                    Row(
                      children: [
                        if (artisan.averageRating != null) ...[
                          Icon(
                            Icons.star,
                            size: 16,
                            color: AppColors.starRating,
                          ),
                          const SizedBox(width: 2),
                          Text(
                            artisan.averageRating.toStringAsFixed(1),
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                          Text(
                            ' (${artisan.reviewsCount})',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: AppColors.lightTextSecondary,
                                ),
                          ),
                          const SizedBox(width: Spacing.md),
                        ],
                        Icon(
                          Icons.location_on_outlined,
                          size: 14,
                          color: AppColors.lightTextSecondary,
                        ),
                        const SizedBox(width: 2),
                        Text(
                          artisan.distanceText,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: AppColors.lightTextSecondary,
                              ),
                        ),
                      ],
                    ),

                    // Availability badge
                    if (artisan.isAvailable)
                      Padding(
                        padding: const EdgeInsets.only(top: Spacing.sm),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.sm,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(Spacing.radiusSm),
                          ),
                          child: Text(
                            'Disponible',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: AppColors.success,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),

              // Arrow
              Icon(
                Icons.chevron_right,
                color: AppColors.lightTextTertiary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
