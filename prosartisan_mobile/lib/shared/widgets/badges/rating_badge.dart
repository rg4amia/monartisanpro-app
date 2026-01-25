import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';

/// Badge d'évaluation avec étoile et nombre d'avis
class RatingBadge extends StatelessWidget {
  final double rating;
  final int reviewCount;
  final bool compact;
  final Color? starColor;
  final Color? textColor;

  const RatingBadge({
    super.key,
    required this.rating,
    required this.reviewCount,
    this.compact = false,
    this.starColor,
    this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          Icons.star,
          size: compact ? 14 : 16,
          color: starColor ?? AppColors.ratingYellow,
        ),
        const SizedBox(width: AppSpacing.xs),
        Text(
          rating.toStringAsFixed(1),
          style: (compact ? AppTypography.caption : AppTypography.rating).copyWith(
            color: textColor ?? AppColors.textPrimary,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(width: AppSpacing.xs),
        Text(
          '(${_formatReviewCount(reviewCount)})',
          style: (compact ? AppTypography.caption : AppTypography.rating).copyWith(
            color: textColor ?? AppColors.textSecondary,
          ),
        ),
      ],
    );
  }

  String _formatReviewCount(int count) {
    if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(1)}k+';
    }
    return '$count+';
  }
}

/// Badge d'évaluation avec étoiles multiples
class StarRatingBadge extends StatelessWidget {
  final double rating;
  final int maxStars;
  final double starSize;
  final Color? activeColor;
  final Color? inactiveColor;
  final bool showRating;

  const StarRatingBadge({
    super.key,
    required this.rating,
    this.maxStars = 5,
    this.starSize = 16,
    this.activeColor,
    this.inactiveColor,
    this.showRating = true,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Étoiles
        Row(
          mainAxisSize: MainAxisSize.min,
          children: List.generate(maxStars, (index) {
            final starValue = index + 1;
            IconData iconData;
            Color color;

            if (rating >= starValue) {
              iconData = Icons.star;
              color = activeColor ?? AppColors.ratingYellow;
            } else if (rating >= starValue - 0.5) {
              iconData = Icons.star_half;
              color = activeColor ?? AppColors.ratingYellow;
            } else {
              iconData = Icons.star_border;
              color = inactiveColor ?? AppColors.textMuted;
            }

            return Icon(
              iconData,
              size: starSize,
              color: color,
            );
          }),
        ),
        
        // Note numérique (optionnelle)
        if (showRating) ...[
          const SizedBox(width: AppSpacing.sm),
          Text(
            rating.toStringAsFixed(1),
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );
  }
}

/// Badge d'évaluation interactif (pour laisser un avis)
class InteractiveRatingBadge extends StatefulWidget {
  final double initialRating;
  final Function(double)? onRatingChanged;
  final int maxStars;
  final double starSize;
  final Color? activeColor;
  final Color? inactiveColor;

  const InteractiveRatingBadge({
    super.key,
    this.initialRating = 0,
    this.onRatingChanged,
    this.maxStars = 5,
    this.starSize = 24,
    this.activeColor,
    this.inactiveColor,
  });

  @override
  State<InteractiveRatingBadge> createState() => _InteractiveRatingBadgeState();
}

class _InteractiveRatingBadgeState extends State<InteractiveRatingBadge> {
  late double _currentRating;

  @override
  void initState() {
    super.initState();
    _currentRating = widget.initialRating;
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(widget.maxStars, (index) {
        final starValue = index + 1;
        
        return GestureDetector(
          onTap: () {
            setState(() {
              _currentRating = starValue.toDouble();
            });
            widget.onRatingChanged?.call(_currentRating);
          },
          child: Container(
            padding: const EdgeInsets.all(4),
            child: Icon(
              _currentRating >= starValue ? Icons.star : Icons.star_border,
              size: widget.starSize,
              color: _currentRating >= starValue
                  ? (widget.activeColor ?? AppColors.ratingYellow)
                  : (widget.inactiveColor ?? AppColors.textMuted),
            ),
          ),
        );
      }),
    );
  }
}

/// Badge d'évaluation avec couleur selon la note
class ColoredRatingBadge extends StatelessWidget {
  final double rating;
  final int reviewCount;
  final bool compact;

  const ColoredRatingBadge({
    super.key,
    required this.rating,
    required this.reviewCount,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = _getRatingColor(rating);
    
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? AppSpacing.sm : AppSpacing.md,
        vertical: compact ? AppSpacing.xs : AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(compact ? 12 : 16),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.star,
            size: compact ? 14 : 16,
            color: color,
          ),
          const SizedBox(width: AppSpacing.xs),
          Text(
            rating.toStringAsFixed(1),
            style: (compact ? AppTypography.caption : AppTypography.rating).copyWith(
              color: color,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: AppSpacing.xs),
          Text(
            '(${_formatReviewCount(reviewCount)})',
            style: (compact ? AppTypography.caption : AppTypography.rating).copyWith(
              color: color.withOpacity(0.7),
            ),
          ),
        ],
      ),
    );
  }

  Color _getRatingColor(double rating) {
    if (rating >= 4.5) {
      return AppColors.accentSuccess;
    } else if (rating >= 3.5) {
      return AppColors.accentWarning;
    } else {
      return AppColors.accentDanger;
    }
  }

  String _formatReviewCount(int count) {
    if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(1)}k+';
    }
    return '$count+';
  }
}

/// Badge d'évaluation simple (juste la note)
class SimpleRatingBadge extends StatelessWidget {
  final double rating;
  final Color? backgroundColor;
  final Color? textColor;
  final bool showStar;

  const SimpleRatingBadge({
    super.key,
    required this.rating,
    this.backgroundColor,
    this.textColor,
    this.showStar = true,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: AppSpacing.symmetric(
        horizontal: AppSpacing.sm,
        vertical: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: backgroundColor ?? AppColors.accentPrimary,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (showStar) ...[
            Icon(
              Icons.star,
              size: 12,
              color: textColor ?? Colors.white,
            ),
            const SizedBox(width: AppSpacing.xs),
          ],
          Text(
            rating.toStringAsFixed(1),
            style: AppTypography.caption.copyWith(
              color: textColor ?? Colors.white,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}