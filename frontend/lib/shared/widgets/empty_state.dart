import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';
import 'custom_button.dart';

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xxl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Icon
            Container(
              padding: const EdgeInsets.all(Spacing.xl),
              decoration: BoxDecoration(
                color: AppColors.lightBackground,
                shape: BoxShape.circle,
              ),
              child: Icon(
                icon,
                size: 64,
                color: AppColors.lightTextTertiary,
              ),
            ),
            const SizedBox(height: Spacing.xl),

            // Title
            Text(
              title,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w600,
                color: AppColors.lightTextPrimary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: Spacing.sm),

            // Message
            Text(
              message,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
              textAlign: TextAlign.center,
            ),

            // Action button
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: Spacing.xl),
              CustomButton(
                text: actionLabel!,
                onPressed: onAction,
                fullWidth: false,
                type: ButtonType.primary,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
