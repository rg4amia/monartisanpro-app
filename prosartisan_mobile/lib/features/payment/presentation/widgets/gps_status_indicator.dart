import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';

/// GPS status indicator widget
class GPSStatusIndicator extends StatelessWidget {
  final bool hasPermission;
  final double accuracy;

  const GPSStatusIndicator({
    super.key,
    required this.hasPermission,
    required this.accuracy,
  });

  @override
  Widget build(BuildContext context) {
    if (!hasPermission) {
      return Row(
        children: [
          Icon(Icons.location_off, color: AppColors.accentDanger, size: 20),
          SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Permission GPS requise pour la validation',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.accentDanger,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      );
    }

    final isAccurate = accuracy <= 10.0;
    final color = isAccurate
        ? AppColors.accentSuccess
        : AppColors.accentWarning;

    return Row(
      children: [
        Icon(
          isAccurate ? Icons.gps_fixed : Icons.gps_not_fixed,
          color: color,
          size: 20,
        ),
        SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            isAccurate
                ? 'GPS précis (${accuracy.toStringAsFixed(1)}m)'
                : 'GPS imprécis (${accuracy.toStringAsFixed(1)}m)',
            style: AppTypography.bodySmall.copyWith(
              color: color,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }
}
