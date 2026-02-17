import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

enum ButtonType { primary, secondary, outline, danger }

class CustomButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final ButtonType type;
  final IconData? icon;
  final bool fullWidth;
  final double? height;

  const CustomButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.type = ButtonType.primary,
    this.icon,
    this.fullWidth = true,
    this.height,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    Color backgroundColor;
    Color textColor;
    Color? borderColor;

    switch (type) {
      case ButtonType.primary:
        backgroundColor = AppColors.lightAccentPrimary;
        textColor = Colors.white;
        borderColor = null;
        break;
      case ButtonType.secondary:
        backgroundColor = AppColors.lightBackgroundSecondary;
        textColor = AppColors.lightAccentPrimary;
        borderColor = null;
        break;
      case ButtonType.outline:
        backgroundColor = Colors.transparent;
        textColor = AppColors.lightAccentPrimary;
        borderColor = AppColors.lightAccentPrimary;
        break;
      case ButtonType.danger:
        backgroundColor = AppColors.error;
        textColor = Colors.white;
        borderColor = null;
        break;
    }

    final child = isLoading
        ? SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              valueColor: AlwaysStoppedAnimation<Color>(textColor),
            ),
          )
        : Row(
            mainAxisSize: fullWidth ? MainAxisSize.max : MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 20, color: textColor),
                const SizedBox(width: Spacing.sm),
              ],
              Text(
                text,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: textColor,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          );

    return SizedBox(
      width: fullWidth ? double.infinity : null,
      height: height ?? 50,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: backgroundColor,
          foregroundColor: textColor,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            side: borderColor != null
                ? BorderSide(color: borderColor, width: 1.5)
                : BorderSide.none,
          ),
          elevation: type == ButtonType.outline ? 0 : 2,
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.lg,
            vertical: Spacing.md,
          ),
        ),
        child: child,
      ),
    );
  }
}
