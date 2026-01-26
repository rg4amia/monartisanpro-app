import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';

class SearchRadiusSlider extends StatelessWidget {
  final double value;
  final Function(double) onChanged;
  final bool showLabel;

  const SearchRadiusSlider({
    super.key,
    required this.value,
    required this.onChanged,
    this.showLabel = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (showLabel) ...[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Rayon de recherche',
                style: AppTypography.body.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              Text(
                '${value.toStringAsFixed(1)} km',
                style: AppTypography.body.copyWith(
                  color: AppColors.accentPrimary,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.sm),
        ],

        Row(
          children: [
            if (!showLabel) ...[
              Icon(Icons.location_on, size: 16, color: AppColors.textSecondary),
              SizedBox(width: AppSpacing.sm),
            ],

            Expanded(
              child: SliderTheme(
                data: SliderTheme.of(context).copyWith(
                  activeTrackColor: AppColors.accentPrimary,
                  inactiveTrackColor: AppColors.overlayMedium,
                  thumbColor: AppColors.accentPrimary,
                  overlayColor: AppColors.accentPrimary.withValues(alpha: 0.2),
                  trackHeight: 4,
                  thumbShape: const RoundSliderThumbShape(
                    enabledThumbRadius: 8,
                  ),
                ),
                child: Slider(
                  value: value,
                  min: 1.0,
                  max: 50.0,
                  divisions: 49,
                  onChanged: onChanged,
                ),
              ),
            ),

            if (!showLabel) ...[
              SizedBox(width: AppSpacing.sm),
              SizedBox(
                width: 50,
                child: Text(
                  '${value.toStringAsFixed(1)} km',
                  style: AppTypography.caption.copyWith(
                    color: AppColors.accentPrimary,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ],
        ),

        if (showLabel) ...[
          SizedBox(height: AppSpacing.xs),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '1 km',
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              Text(
                '50 km',
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
