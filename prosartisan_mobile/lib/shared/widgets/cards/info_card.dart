import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';

/// Information card widget for displaying important information
///
/// Used throughout the app to display informational content with optional icon
class InfoCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData? icon;
  final Color? backgroundColor;
  final Color? iconColor;
  final Color? titleColor;
  final Color? subtitleColor;
  final VoidCallback? onTap;

  const InfoCard({
    super.key,
    required this.title,
    required this.subtitle,
    this.icon,
    this.backgroundColor,
    this.iconColor,
    this.titleColor,
    this.subtitleColor,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.all(AppSpacing.base),
        decoration: BoxDecoration(
          color: backgroundColor ?? AppColors.cardBg,
          borderRadius: AppRadius.cardRadius,
          border: Border.all(color: AppColors.overlayMedium),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (icon != null) ...[
              Container(
                padding: EdgeInsets.all(AppSpacing.sm),
                decoration: BoxDecoration(
                  color: (iconColor ?? AppColors.accentPrimary).withValues(
                    alpha: 0.1,
                  ),
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: Icon(
                  icon!,
                  color: iconColor ?? AppColors.accentPrimary,
                  size: 20,
                ),
              ),
              SizedBox(width: AppSpacing.md),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTypography.body.copyWith(
                      fontWeight: FontWeight.w600,
                      color: titleColor ?? AppColors.textPrimary,
                    ),
                  ),
                  SizedBox(height: AppSpacing.xs),
                  Text(
                    subtitle,
                    style: AppTypography.bodySmall.copyWith(
                      color: subtitleColor ?? AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Success variant of InfoCard
class SuccessInfoCard extends InfoCard {
  const SuccessInfoCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.onTap,
  }) : super(
         icon: Icons.check_circle_outline,
         iconColor: AppColors.accentSuccess,
         backgroundColor: AppColors.accentSuccess,
       );
}

/// Warning variant of InfoCard
class WarningInfoCard extends InfoCard {
  const WarningInfoCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.onTap,
  }) : super(
         icon: Icons.warning_outlined,
         iconColor: AppColors.accentWarning,
         backgroundColor: AppColors.accentWarning,
       );
}

/// Error variant of InfoCard
class ErrorInfoCard extends InfoCard {
  const ErrorInfoCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.onTap,
  }) : super(
         icon: Icons.error_outline,
         iconColor: AppColors.accentDanger,
         backgroundColor: AppColors.accentDanger,
       );
}
