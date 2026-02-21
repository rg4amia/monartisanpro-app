import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../../shared/models/artisan_search_model.dart';
import 'artisan_profile_screen.dart';

class SearchResultsScreen extends StatefulWidget {
  const SearchResultsScreen({super.key});

  @override
  State<SearchResultsScreen> createState() => _SearchResultsScreenState();
}

class _SearchResultsScreenState extends State<SearchResultsScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _fadeAnimation = CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeInOut,
    );
    _animationController.forward();
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final searchController = Get.find<artisan_search.ArtisanSearchController>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Résultats de recherche'),
        actions: [
          // Sort menu
          PopupMenuButton<String>(
            icon: const Icon(Icons.sort),
            onSelected: (value) => searchController.setSortBy(value),
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'distance',
                child: Row(
                  children: [
                    Icon(Icons.near_me, size: 20),
                    SizedBox(width: 8),
                    Text('Distance'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'rating',
                child: Row(
                  children: [
                    Icon(Icons.star, size: 20),
                    SizedBox(width: 8),
                    Text('Note'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'experience',
                child: Row(
                  children: [
                    Icon(Icons.work, size: 20),
                    SizedBox(width: 8),
                    Text('Expérience'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: Obx(() {
        if (searchController.isLoading.value) {
          return _buildLoadingState();
        }

        if (searchController.searchResults.isEmpty) {
          return _buildEmptyState(context);
        }

        return FadeTransition(
          opacity: _fadeAnimation,
          child: Column(
            children: [
              _buildResultsHeader(context, searchController),
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(Spacing.screenPadding),
                  itemCount: searchController.searchResults.length,
                  separatorBuilder: (context, index) =>
                      const SizedBox(height: Spacing.md),
                  itemBuilder: (context, index) {
                    final artisan = searchController.searchResults[index];
                    return _ArtisanCard(artisan: artisan, index: index);
                  },
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildLoadingState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: Spacing.lg),
          Text(
            'Recherche en cours...',
            style: TextStyle(color: AppColors.lightTextSecondary, fontSize: 16),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(Spacing.xl),
              decoration: BoxDecoration(
                color: AppColors.lightBackground,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.search_off,
                size: 80,
                color: AppColors.lightTextTertiary,
              ),
            ),
            const SizedBox(height: Spacing.xl),
            Text(
              'Aucun artisan trouvé',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              'Essayez d\'ajuster vos filtres de recherche\nou d\'élargir la zone de recherche',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
            ),
            const SizedBox(height: Spacing.xl),
            ElevatedButton.icon(
              onPressed: () => Get.back(),
              icon: const Icon(Icons.tune),
              label: const Text('Modifier les filtres'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: Spacing.xl,
                  vertical: Spacing.md,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResultsHeader(
    BuildContext context,
    artisan_search.ArtisanSearchController controller,
  ) {
    final nearbyCount = controller.nearbyArtisansCount;

    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  Icons.check_circle,
                  color: AppColors.success,
                  size: 20,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${controller.searchResults.length} artisan(s) trouvé(s)',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    if (nearbyCount > 0)
                      Text(
                        '$nearbyCount à proximité (<2km)',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.goldenMarker,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ArtisanCard extends StatelessWidget {
  final ArtisanSearchResult artisan;
  final int index;

  const _ArtisanCard({required this.artisan, required this.index});

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: InkWell(
        onTap: () {
          Get.to(() => ArtisanProfileScreen(artisanId: artisan.id));
        },
        child: Padding(
          padding: const EdgeInsets.all(Spacing.base),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Avatar with badge
              Stack(
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: AppColors.lightBackground,
                    backgroundImage: artisan.avatar != null
                        ? NetworkImage(artisan.avatar!)
                        : null,
                    child: artisan.avatar == null
                        ? const Icon(Icons.person, size: 32)
                        : null,
                  ),
                  if (artisan.badgeLevel != null &&
                      artisan.badgeLevel != 'none')
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: _getBadgeColor(artisan.badgeLevel!),
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: Icon(
                          Icons.verified,
                          size: 12,
                          color: Colors.white,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(width: Spacing.md),

              // Details
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Name and nearby badge
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            artisan.name,
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.bold),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (artisan.isNearby) _buildNearbyBadge(),
                      ],
                    ),
                    const SizedBox(height: Spacing.xs),

                    // Trade and experience
                    _buildTradeInfo(context),
                    const SizedBox(height: Spacing.sm),

                    // Rating and distance
                    _buildRatingAndDistance(context),

                    // Availability and score
                    const SizedBox(height: Spacing.sm),
                    Row(
                      children: [
                        if (artisan.available) _buildAvailabilityBadge(),
                        if (artisan.available && artisan.nzassaScore != null)
                          const SizedBox(width: Spacing.sm),
                        if (artisan.nzassaScore != null) _buildScoreBadge(),
                      ],
                    ),
                  ],
                ),
              ),

              // Arrow
              Icon(Icons.chevron_right, color: AppColors.lightTextTertiary),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNearbyBadge() {
    return Container(
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
          Icon(Icons.location_on, size: 12, color: AppColors.goldenMarker),
          const SizedBox(width: 2),
          Text(
            'Proche',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              color: AppColors.goldenMarker,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTradeInfo(BuildContext context) {
    return Row(
      children: [
        Icon(Icons.work_outline, size: 14, color: AppColors.lightTextSecondary),
        const SizedBox(width: Spacing.xs),
        Expanded(
          child: Text(
            artisan.tradeName ?? 'Non spécifié',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.lightTextSecondary,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        if (artisan.experienceYears > 0) ...[
          Text(' • ', style: TextStyle(color: AppColors.lightTextSecondary)),
          Text(
            '${artisan.experienceYears} ans',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.lightTextSecondary,
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildRatingAndDistance(BuildContext context) {
    return Row(
      children: [
        if (artisan.averageRating != null) ...[
          Icon(Icons.star, size: 16, color: AppColors.starRating),
          const SizedBox(width: 2),
          Text(
            artisan.averageRating!.toStringAsFixed(1),
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
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
        Expanded(
          child: Text(
            artisan.formattedDistance,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.lightTextSecondary,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _buildAvailabilityBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.success.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
        border: Border.all(
          color: AppColors.success.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: AppColors.success,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 4),
          Text(
            'Disponible',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              color: AppColors.success,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildScoreBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.lightAccentPrimary.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.emoji_events,
            size: 10,
            color: AppColors.lightAccentPrimary,
          ),
          const SizedBox(width: 2),
          Text(
            'Score: ${artisan.nzassaScore}',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              color: AppColors.lightAccentPrimary,
            ),
          ),
        ],
      ),
    );
  }

  Color _getBadgeColor(String level) {
    switch (level) {
      case 'gold':
        return const Color(0xFFFFD700);
      case 'silver':
        return const Color(0xFFC0C0C0);
      case 'bronze':
        return const Color(0xFFCD7F32);
      default:
        return Colors.grey;
    }
  }
}
