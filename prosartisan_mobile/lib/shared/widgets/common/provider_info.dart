import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../badges/rating_badge.dart';
import '../cards/service_card.dart'; // Pour ProviderModel

/// Widget d'information sur le prestataire
/// Affiche avatar, nom, rôle et badge de vérification
class ProviderInfo extends StatelessWidget {
  final ProviderModel provider;
  final bool showRating;
  final bool compact;
  final VoidCallback? onTap;

  const ProviderInfo({
    super.key,
    required this.provider,
    this.showRating = false,
    this.compact = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Row(
        children: [
          // Avatar
          _buildAvatar(),

          const SizedBox(width: AppSpacing.md),

          // Informations
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Nom avec badge de vérification
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        provider.name,
                        style:
                            (compact
                                    ? AppTypography.bodySmall
                                    : AppTypography.body)
                                .copyWith(
                                  color: AppColors.textPrimary,
                                  fontWeight: FontWeight.w600,
                                ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (provider.isVerified) ...[
                      const SizedBox(width: AppSpacing.xs),
                      Icon(
                        Icons.verified,
                        size: compact ? 14 : 16,
                        color: AppColors.accentPrimary,
                      ),
                    ],
                  ],
                ),

                const SizedBox(height: AppSpacing.xs),

                // Rôle
                Text(
                  provider.role,
                  style:
                      (compact
                              ? AppTypography.caption
                              : AppTypography.bodySmall)
                          .copyWith(color: AppColors.textSecondary),
                ),

                // Rating (si demandé et disponible)
                if (showRating && provider.rating != null) ...[
                  const SizedBox(height: AppSpacing.xs),
                  SimpleRatingBadge(
                    rating: provider.rating!,
                    backgroundColor: AppColors.overlayLight,
                    textColor: AppColors.textPrimary,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAvatar() {
    final size = compact ? AppSpacing.avatarSizeSmall : AppSpacing.avatarSize;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: AppRadius.circular(size / 2),
        border: Border.all(
          color: provider.isVerified
              ? AppColors.accentPrimary
              : AppColors.overlayMedium,
          width: 2,
        ),
      ),
      child: ClipRRect(
        borderRadius: AppRadius.circular(size / 2),
        child: provider.avatarUrl != null
            ? CachedNetworkImage(
                imageUrl: provider.avatarUrl!,
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(
                  color: AppColors.overlayLight,
                  child: Icon(
                    Icons.person,
                    color: AppColors.textMuted,
                    size: size * 0.5,
                  ),
                ),
                errorWidget: (context, url, error) =>
                    _buildPlaceholderAvatar(size),
              )
            : _buildPlaceholderAvatar(size),
      ),
    );
  }

  Widget _buildPlaceholderAvatar(double size) {
    return Container(
      color: AppColors.overlayLight,
      child: Icon(Icons.person, color: AppColors.textMuted, size: size * 0.5),
    );
  }
}

/// Widget d'information compacte sur le prestataire (pour listes)
class CompactProviderInfo extends StatelessWidget {
  final ProviderModel provider;
  final VoidCallback? onTap;

  const CompactProviderInfo({super.key, required this.provider, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Avatar petit
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              borderRadius: AppRadius.circular(12),
              border: Border.all(
                color: provider.isVerified
                    ? AppColors.accentPrimary
                    : AppColors.overlayMedium,
                width: 1,
              ),
            ),
            child: ClipRRect(
              borderRadius: AppRadius.circular(12),
              child: provider.avatarUrl != null
                  ? CachedNetworkImage(
                      imageUrl: provider.avatarUrl!,
                      fit: BoxFit.cover,
                      placeholder: (context, url) => Container(
                        color: AppColors.overlayLight,
                        child: const Icon(
                          Icons.person,
                          color: AppColors.textMuted,
                          size: 12,
                        ),
                      ),
                      errorWidget: (context, url, error) => Container(
                        color: AppColors.overlayLight,
                        child: const Icon(
                          Icons.person,
                          color: AppColors.textMuted,
                          size: 12,
                        ),
                      ),
                    )
                  : Container(
                      color: AppColors.overlayLight,
                      child: const Icon(
                        Icons.person,
                        color: AppColors.textMuted,
                        size: 12,
                      ),
                    ),
            ),
          ),

          const SizedBox(width: AppSpacing.sm),

          // Nom
          Text(
            provider.name,
            style: AppTypography.caption.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
          ),

          // Badge de vérification
          if (provider.isVerified) ...[
            const SizedBox(width: AppSpacing.xs),
            const Icon(
              Icons.verified,
              size: 12,
              color: AppColors.accentPrimary,
            ),
          ],
        ],
      ),
    );
  }
}

/// Carte de prestataire détaillée
class ProviderCard extends StatelessWidget {
  final ProviderModel provider;
  final String? description;
  final int? completedJobs;
  final VoidCallback? onTap;
  final VoidCallback? onMessageTap;
  final VoidCallback? onCallTap;

  const ProviderCard({
    super.key,
    required this.provider,
    this.description,
    this.completedJobs,
    this.onTap,
    this.onMessageTap,
    this.onCallTap,
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
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: [
            // En-tête avec avatar et infos
            Row(
              children: [
                // Avatar
                Container(
                  width: AppSpacing.avatarSizeLarge,
                  height: AppSpacing.avatarSizeLarge,
                  decoration: BoxDecoration(
                    borderRadius: AppRadius.circular(
                      AppSpacing.avatarSizeLarge / 2,
                    ),
                    border: Border.all(
                      color: provider.isVerified
                          ? AppColors.accentPrimary
                          : AppColors.overlayMedium,
                      width: 2,
                    ),
                  ),
                  child: ClipRRect(
                    borderRadius: AppRadius.circular(
                      AppSpacing.avatarSizeLarge / 2,
                    ),
                    child: provider.avatarUrl != null
                        ? CachedNetworkImage(
                            imageUrl: provider.avatarUrl!,
                            fit: BoxFit.cover,
                            placeholder: (context, url) => Container(
                              color: AppColors.overlayLight,
                              child: Icon(
                                Icons.person,
                                color: AppColors.textMuted,
                                size: AppSpacing.avatarSizeLarge * 0.5,
                              ),
                            ),
                            errorWidget: (context, url, error) => Container(
                              color: AppColors.overlayLight,
                              child: Icon(
                                Icons.person,
                                color: AppColors.textMuted,
                                size: AppSpacing.avatarSizeLarge * 0.5,
                              ),
                            ),
                          )
                        : Container(
                            color: AppColors.overlayLight,
                            child: Icon(
                              Icons.person,
                              color: AppColors.textMuted,
                              size: AppSpacing.avatarSizeLarge * 0.5,
                            ),
                          ),
                  ),
                ),

                const SizedBox(width: AppSpacing.base),

                // Informations
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Nom avec badge
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              provider.name,
                              style: AppTypography.h4.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          if (provider.isVerified)
                            const Icon(
                              Icons.verified,
                              size: 20,
                              color: AppColors.accentPrimary,
                            ),
                        ],
                      ),

                      const SizedBox(height: AppSpacing.xs),

                      // Rôle
                      Text(
                        provider.role,
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),

                      const SizedBox(height: AppSpacing.xs),

                      // Rating et jobs complétés
                      Row(
                        children: [
                          if (provider.rating != null)
                            SimpleRatingBadge(
                              rating: provider.rating!,
                              backgroundColor: AppColors.overlayLight,
                              textColor: AppColors.textPrimary,
                            ),
                          if (provider.rating != null && completedJobs != null)
                            const SizedBox(width: AppSpacing.md),
                          if (completedJobs != null)
                            Text(
                              '$completedJobs missions',
                              style: AppTypography.caption.copyWith(
                                color: AppColors.textTertiary,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),

            // Description (si fournie)
            if (description != null) ...[
              const SizedBox(height: AppSpacing.base),
              Text(
                description!,
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textSecondary,
                ),
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
            ],

            // Boutons d'action
            if (onMessageTap != null || onCallTap != null) ...[
              const SizedBox(height: AppSpacing.base),
              Row(
                children: [
                  if (onMessageTap != null)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: onMessageTap,
                        icon: const Icon(Icons.message_outlined),
                        label: const Text('Message'),
                      ),
                    ),
                  if (onMessageTap != null && onCallTap != null)
                    const SizedBox(width: AppSpacing.md),
                  if (onCallTap != null)
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: onCallTap,
                        icon: const Icon(Icons.phone),
                        label: const Text('Appeler'),
                      ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
