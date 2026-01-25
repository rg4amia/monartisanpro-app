import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Carte promotionnelle avec gradient et illustration
/// Utilisée pour afficher des offres spéciales et promotions
class PromotionalCard extends StatelessWidget {
  final String discount;
  final String title;
  final String subtitle;
  final String? imagePath;
  final VoidCallback? onTap;
  final List<Color>? gradientColors;
  final double? height;

  const PromotionalCard({
    super.key,
    required this.discount,
    required this.title,
    required this.subtitle,
    this.imagePath,
    this.onTap,
    this.gradientColors,
    this.height,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: height ?? AppSpacing.promotionalCardHeight,
        padding: AppSpacing.cardPaddingAll,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: gradientColors ?? AppColors.promotionalGradient,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: AppRadius.cardRadius,
          boxShadow: AppShadows.promotionalCard,
        ),
        child: Row(
          children: [
            // Contenu textuel
            Expanded(
              flex: 2,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Pourcentage/Discount avec emoji
                  Text(
                    '$discount 🔥',
                    style: AppTypography.h1.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),

                  // Titre principal
                  Text(
                    title,
                    style: AppTypography.h3.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xs),

                  // Sous-titre descriptif
                  Text(
                    subtitle,
                    style: AppTypography.bodySmall.copyWith(
                      color: Colors.white.withOpacity(0.9),
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),

            // Illustration (si fournie)
            if (imagePath != null) ...[
              const SizedBox(width: AppSpacing.md),
              Expanded(
                flex: 1,
                child: ClipRRect(
                  borderRadius: AppRadius.imageRadius,
                  child: Image.asset(
                    imagePath!,
                    height: 100,
                    fit: BoxFit.contain,
                    errorBuilder: (context, error, stackTrace) {
                      return Container(
                        height: 100,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.1),
                          borderRadius: AppRadius.imageRadius,
                        ),
                        child: const Icon(
                          Icons.image_outlined,
                          color: Colors.white,
                          size: 40,
                        ),
                      );
                    },
                  ),
                ),
              ),
            ] else ...[
              // Icône par défaut si pas d'image
              const SizedBox(width: AppSpacing.md),
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.1),
                  borderRadius: AppRadius.circular(40),
                ),
                child: const Icon(
                  Icons.local_offer_outlined,
                  color: Colors.white,
                  size: 40,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Variante compacte de la carte promotionnelle
class CompactPromotionalCard extends StatelessWidget {
  final String text;
  final String emoji;
  final VoidCallback? onTap;
  final List<Color>? gradientColors;

  const CompactPromotionalCard({
    super.key,
    required this.text,
    required this.emoji,
    this.onTap,
    this.gradientColors,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: AppSpacing.symmetric(
          horizontal: AppSpacing.base,
          vertical: AppSpacing.md,
        ),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: gradientColors ?? AppColors.promotionalGradient,
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
          ),
          borderRadius: AppRadius.mediumRadius,
          boxShadow: AppShadows.card,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(emoji, style: const TextStyle(fontSize: 20)),
            const SizedBox(width: AppSpacing.sm),
            Text(
              text,
              style: AppTypography.bodySmall.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte promotionnelle avec action button
class PromotionalCardWithAction extends StatelessWidget {
  final String discount;
  final String title;
  final String subtitle;
  final String buttonText;
  final String? imagePath;
  final VoidCallback? onButtonPressed;
  final List<Color>? gradientColors;

  const PromotionalCardWithAction({
    super.key,
    required this.discount,
    required this.title,
    required this.subtitle,
    required this.buttonText,
    this.imagePath,
    this.onButtonPressed,
    this.gradientColors,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: AppSpacing.cardPaddingAll,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: gradientColors ?? AppColors.promotionalGradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: AppRadius.cardRadius,
        boxShadow: AppShadows.promotionalCard,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              // Contenu textuel
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$discount 🔥',
                      style: AppTypography.h2.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      title,
                      style: AppTypography.h4.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      subtitle,
                      style: AppTypography.bodySmall.copyWith(
                        color: Colors.white.withOpacity(0.9),
                      ),
                    ),
                  ],
                ),
              ),

              // Illustration
              if (imagePath != null)
                ClipRRect(
                  borderRadius: AppRadius.imageRadius,
                  child: Image.asset(
                    imagePath!,
                    width: 80,
                    height: 80,
                    fit: BoxFit.contain,
                  ),
                ),
            ],
          ),

          const SizedBox(height: AppSpacing.base),

          // Bouton d'action
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: onButtonPressed,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor:
                    gradientColors?.first ?? AppColors.accentPrimary,
                elevation: 0,
                padding: AppSpacing.symmetric(vertical: AppSpacing.md),
                shape: RoundedRectangleBorder(
                  borderRadius: AppRadius.buttonRadius,
                ),
              ),
              child: Text(
                buttonText,
                style: AppTypography.button.copyWith(
                  color: gradientColors?.first ?? AppColors.accentPrimary,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
