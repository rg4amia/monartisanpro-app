import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';
import '../badges/rating_badge.dart';
import '../badges/status_badge.dart';
import '../common/provider_info.dart';

/// Modèle pour un service
class ServiceModel {
  final String id;
  final String title;
  final String description;
  final double price;
  final String currency;
  final String? imageUrl;
  final double rating;
  final int reviewCount;
  final ProviderModel provider;
  final String? status;
  final bool isFavorite;
  final List<String>? tags;

  const ServiceModel({
    required this.id,
    required this.title,
    required this.description,
    required this.price,
    this.currency = 'FCFA',
    this.imageUrl,
    required this.rating,
    required this.reviewCount,
    required this.provider,
    this.status,
    this.isFavorite = false,
    this.tags,
  });
}

/// Modèle pour un prestataire
class ProviderModel {
  final String id;
  final String name;
  final String role;
  final String? avatarUrl;
  final double? rating;
  final bool isVerified;

  const ProviderModel({
    required this.id,
    required this.name,
    required this.role,
    this.avatarUrl,
    this.rating,
    this.isVerified = false,
  });
}

/// Carte de service principale
/// Structure verticale avec image, titre, prix, rating et info prestataire
class ServiceCard extends StatelessWidget {
  final ServiceModel service;
  final VoidCallback? onTap;
  final VoidCallback? onFavoritePressed;
  final double? width;
  final double? height;

  const ServiceCard({
    super.key,
    required this.service,
    this.onTap,
    this.onFavoritePressed,
    this.width,
    this.height,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: width,
        height: height ?? AppSpacing.serviceCardHeight,
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: AppRadius.cardRadius,
          boxShadow: AppShadows.card,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image avec badge prix et bouton favori
            _buildImageSection(),

            // Contenu
            Expanded(
              child: Padding(
                padding: AppSpacing.cardPaddingAll,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Titre et status
                    _buildTitleSection(),

                    const SizedBox(height: AppSpacing.sm),

                    // Description
                    _buildDescription(),

                    const SizedBox(height: AppSpacing.sm),

                    // Rating
                    RatingBadge(
                      rating: service.rating,
                      reviewCount: service.reviewCount,
                    ),

                    const Spacer(),

                    // Info prestataire
                    ProviderInfo(provider: service.provider),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildImageSection() {
    return Stack(
      children: [
        // Image principale
        ClipRRect(
          borderRadius: AppRadius.cardTopRadius,
          child: SizedBox(
            height: 140,
            width: double.infinity,
            child: service.imageUrl != null
                ? CachedNetworkImage(
                    imageUrl: service.imageUrl!,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => Container(
                      color: AppColors.overlayLight,
                      child: const Center(
                        child: CircularProgressIndicator(
                          color: AppColors.accentPrimary,
                        ),
                      ),
                    ),
                    errorWidget: (context, url, error) =>
                        _buildPlaceholderImage(),
                  )
                : _buildPlaceholderImage(),
          ),
        ),

        // Badge prix
        Positioned(
          top: AppSpacing.md,
          left: AppSpacing.md,
          child: Container(
            padding: AppSpacing.symmetric(
              horizontal: AppSpacing.md,
              vertical: AppSpacing.sm,
            ),
            decoration: BoxDecoration(
              color: AppColors.accentPrimary,
              borderRadius: AppRadius.priceBadgeRadius,
              boxShadow: AppShadows.card,
            ),
            child: Text(
              '${service.price.toStringAsFixed(0)} ${service.currency}',
              style: AppTypography.badge.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),

        // Bouton favori
        Positioned(
          top: AppSpacing.md,
          right: AppSpacing.md,
          child: GestureDetector(
            onTap: onFavoritePressed,
            child: Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.3),
                borderRadius: AppRadius.circular(18),
              ),
              child: Icon(
                service.isFavorite ? Icons.favorite : Icons.favorite_border,
                color: service.isFavorite
                    ? AppColors.accentDanger
                    : Colors.white,
                size: 20,
              ),
            ),
          ),
        ),

        // Status badge (si présent)
        if (service.status != null)
          Positioned(
            bottom: AppSpacing.md,
            right: AppSpacing.md,
            child: StatusBadge(status: service.status!),
          ),
      ],
    );
  }

  Widget _buildPlaceholderImage() {
    return Container(
      color: AppColors.overlayLight,
      child: const Center(
        child: Icon(Icons.image_outlined, color: AppColors.textMuted, size: 40),
      ),
    );
  }

  Widget _buildTitleSection() {
    return Row(
      children: [
        Expanded(
          child: Text(
            service.title,
            style: AppTypography.h4.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w600,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _buildDescription() {
    return Text(
      service.description,
      style: AppTypography.bodySmall.copyWith(color: AppColors.textSecondary),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }
}

/// Carte de service horizontale (pour listes compactes)
class HorizontalServiceCard extends StatelessWidget {
  final ServiceModel service;
  final VoidCallback? onTap;
  final VoidCallback? onFavoritePressed;

  const HorizontalServiceCard({
    super.key,
    required this.service,
    this.onTap,
    this.onFavoritePressed,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: AppSpacing.cardPaddingAll,
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: AppRadius.cardRadius,
          boxShadow: AppShadows.card,
        ),
        child: Row(
          children: [
            // Image
            ClipRRect(
              borderRadius: AppRadius.imageRadius,
              child: SizedBox(
                width: 80,
                height: 80,
                child: service.imageUrl != null
                    ? CachedNetworkImage(
                        imageUrl: service.imageUrl!,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => Container(
                          color: AppColors.overlayLight,
                          child: const Center(
                            child: CircularProgressIndicator(
                              color: AppColors.accentPrimary,
                            ),
                          ),
                        ),
                        errorWidget: (context, url, error) => Container(
                          color: AppColors.overlayLight,
                          child: const Icon(
                            Icons.image_outlined,
                            color: AppColors.textMuted,
                          ),
                        ),
                      )
                    : Container(
                        color: AppColors.overlayLight,
                        child: const Icon(
                          Icons.image_outlined,
                          color: AppColors.textMuted,
                        ),
                      ),
              ),
            ),

            const SizedBox(width: AppSpacing.md),

            // Contenu
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Titre et prix
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          service.title,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                            fontWeight: FontWeight.w600,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Text(
                        '${service.price.toStringAsFixed(0)} ${service.currency}',
                        style: AppTypography.price.copyWith(
                          color: AppColors.accentPrimary,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: AppSpacing.xs),

                  // Description
                  Text(
                    service.description,
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),

                  const SizedBox(height: AppSpacing.sm),

                  // Rating et prestataire
                  Row(
                    children: [
                      RatingBadge(
                        rating: service.rating,
                        reviewCount: service.reviewCount,
                        compact: true,
                      ),
                      const Spacer(),
                      Text(
                        service.provider.name,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textTertiary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Bouton favori
            GestureDetector(
              onTap: onFavoritePressed,
              child: Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: AppColors.overlayLight,
                  borderRadius: AppRadius.circular(18),
                ),
                child: Icon(
                  service.isFavorite ? Icons.favorite : Icons.favorite_border,
                  color: service.isFavorite
                      ? AppColors.accentDanger
                      : AppColors.textMuted,
                  size: 20,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Liste de cartes de service
class ServiceCardList extends StatelessWidget {
  final List<ServiceModel> services;
  final Function(ServiceModel)? onServiceTap;
  final Function(ServiceModel)? onFavoriteTap;
  final bool horizontal;
  final double? itemWidth;

  const ServiceCardList({
    super.key,
    required this.services,
    this.onServiceTap,
    this.onFavoriteTap,
    this.horizontal = false,
    this.itemWidth,
  });

  @override
  Widget build(BuildContext context) {
    if (horizontal) {
      return SizedBox(
        height: AppSpacing.serviceCardHeight,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: AppSpacing.screenPaddingHorizontal,
          itemCount: services.length,
          separatorBuilder: (context, index) =>
              const SizedBox(width: AppSpacing.md),
          itemBuilder: (context, index) {
            final service = services[index];
            return ServiceCard(
              service: service,
              width: itemWidth ?? 250,
              onTap: () => onServiceTap?.call(service),
              onFavoritePressed: () => onFavoriteTap?.call(service),
            );
          },
        ),
      );
    }

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: services.length,
      separatorBuilder: (context, index) =>
          const SizedBox(height: AppSpacing.md),
      itemBuilder: (context, index) {
        final service = services[index];
        return ServiceCard(
          service: service,
          onTap: () => onServiceTap?.call(service),
          onFavoritePressed: () => onFavoriteTap?.call(service),
        );
      },
    );
  }
}
