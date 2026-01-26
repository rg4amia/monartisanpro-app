import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';

/// Empty state card widget for displaying empty states
///
/// Used throughout the app to show when there's no content to display
class EmptyStateCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final String? actionText;
  final VoidCallback? onActionPressed;
  final Color? iconColor;

  const EmptyStateCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    this.actionText,
    this.onActionPressed,
    this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.all(AppSpacing.xxl),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: EdgeInsets.all(AppSpacing.lg),
            decoration: BoxDecoration(
              color: (iconColor ?? AppColors.textMuted).withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              size: 48,
              color: iconColor ?? AppColors.textMuted,
            ),
          ),
          SizedBox(height: AppSpacing.lg),
          Text(
            title,
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w600,
            ),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: AppSpacing.sm),
          Text(
            subtitle,
            style: AppTypography.body.copyWith(color: AppColors.textSecondary),
            textAlign: TextAlign.center,
          ),
          if (actionText != null && onActionPressed != null) ...[
            SizedBox(height: AppSpacing.xl),
            ElevatedButton(
              onPressed: onActionPressed,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.accentPrimary,
                foregroundColor: Colors.white,
                padding: EdgeInsets.symmetric(
                  horizontal: AppSpacing.xl,
                  vertical: AppSpacing.md,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: AppRadius.buttonRadius,
                ),
              ),
              child: Text(
                actionText!,
                style: AppTypography.button.copyWith(color: Colors.white),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Error variant of EmptyStateCard
class ErrorEmptyStateCard extends EmptyStateCard {
  const ErrorEmptyStateCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.actionText,
    super.onActionPressed,
  }) : super(icon: Icons.error_outline, iconColor: AppColors.accentDanger);
}

/// No data variant of EmptyStateCard
class NoDataEmptyStateCard extends EmptyStateCard {
  const NoDataEmptyStateCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.actionText,
    super.onActionPressed,
  }) : super(icon: Icons.inbox_outlined, iconColor: AppColors.textMuted);
}

/// No connection variant of EmptyStateCard
class NoConnectionEmptyStateCard extends EmptyStateCard {
  const NoConnectionEmptyStateCard({
    super.key,
    required super.title,
    required super.subtitle,
    super.actionText,
    super.onActionPressed,
  }) : super(icon: Icons.wifi_off_outlined, iconColor: AppColors.accentWarning);
}
