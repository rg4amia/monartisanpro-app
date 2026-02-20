import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';
import '../../shared/models/artisan_search_model.dart';
import 'rating_stars.dart';
import 'custom_card.dart';

class ArtisanCard extends StatelessWidget {
  final ArtisanSearchResult artisan;
  final bool showDistance;
  final VoidCallback onTap;

  const ArtisanCard({
    super.key,
    required this.artisan,
    this.showDistance = true,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return CustomCard(
      onTap: onTap,
      padding: const EdgeInsets.all(Spacing.md),
      child: Row(
        children: [
          // Avatar
          Stack(
            children: [
              Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: artisan.isNearby
                      ? Border.all(color: AppColors.goldenMarker, width: 3)
                      : null,
                ),
                child: CircleAvatar(
                  radius: 30,
                  backgroundImage: artisan.avatar != null
                      ? CachedNetworkImageProvider(artisan.avatar!)
                      : null,
                  child: artisan.avatar == null
                      ? const Icon(Icons.person, size: 30)
                      : null,
                ),
              ),
              if (artisan.badgeLevel != null && artisan.badgeLevel != 'none')
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      _getBadgeEmoji(artisan.badgeLevel!),
                      style: const TextStyle(fontSize: 16),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(width: Spacing.md),

          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Name & Nearby Indicator
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        artisan.name,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (artisan.isNearby)
                      Icon(
                        Icons.location_on,
                        size: 18,
                        color: AppColors.goldenMarker,
                      ),
                  ],
                ),
                const SizedBox(height: 4),

                // Trade
                Text(
                  artisan.tradeName ?? 'Artisan',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: AppColors.lightTextSecondary,
                  ),
                ),
                const SizedBox(height: 4),

                // Rating & Reviews
                Row(
                  children: [
                    RatingStars(rating: artisan.averageRating ?? 0, size: 16),
                    const SizedBox(width: 4),
                    Text(
                      '(${artisan.totalReviews})',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                    ),
                  ],
                ),

                // Distance
                if (showDistance && artisan.formattedDistance.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        Icons.location_on_outlined,
                        size: 14,
                        color: AppColors.lightTextSecondary,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        artisan.formattedDistance,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),

          // Chevron
          const Icon(Icons.chevron_right, color: AppColors.lightTextTertiary),
        ],
      ),
    );
  }

  String _getBadgeEmoji(String badgeLevel) {
    switch (badgeLevel.toLowerCase()) {
      case 'gold':
        return '🥇';
      case 'silver':
        return '🥈';
      case 'bronze':
        return '🥉';
      default:
        return '';
    }
  }
}
