import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_theme.dart';

/// Jeton status badge widget
class JetonStatusBadge extends StatelessWidget {
  final String status;

  const JetonStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final statusInfo = _getStatusInfo(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: statusInfo['color'].withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppTheme.radiusLg),
        border: Border.all(
          color: statusInfo['color'].withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(statusInfo['icon'], size: 16, color: statusInfo['color']),
          SizedBox(width: AppSpacing.sm),
          Text(
            statusInfo['label'],
            style: AppTypography.bodySmall.copyWith(
              color: statusInfo['color'],
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _getStatusInfo(String status) {
    switch (status.toUpperCase()) {
      case 'ACTIVE':
        return {
          'label': 'Actif',
          'color': AppColors.success,
          'icon': Icons.check_circle,
        };
      case 'PARTIALLY_USED':
        return {
          'label': 'Partiellement utilisé',
          'color': AppColors.warning,
          'icon': Icons.pie_chart,
        };
      case 'FULLY_USED':
        return {
          'label': 'Entièrement utilisé',
          'color': AppColors.info,
          'icon': Icons.done_all,
        };
      case 'EXPIRED':
        return {
          'label': 'Expiré',
          'color': AppColors.error,
          'icon': Icons.access_time,
        };
      default:
        return {
          'label': status,
          'color': AppColors.textSecondary,
          'icon': Icons.help,
        };
    }
  }
}
