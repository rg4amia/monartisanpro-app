import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Bouton principal selon le design system
/// Background: gradient ou couleur accent solide
/// Text: blanc, 16px, weight 600
/// Border radius: 12px
/// Padding: 16px vertical, 24px horizontal
/// Shadow: medium elevation
class PrimaryButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool isDisabled;
  final IconData? icon;
  final double? width;
  final double? height;
  final List<Color>? gradientColors;
  final Color? backgroundColor;
  final Color? textColor;
  final EdgeInsets? padding;

  const PrimaryButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.isDisabled = false,
    this.icon,
    this.width,
    this.height,
    this.gradientColors,
    this.backgroundColor,
    this.textColor,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final isEnabled = !isDisabled && !isLoading && onPressed != null;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: width,
      height: height ?? AppSpacing.buttonHeight,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: isEnabled ? onPressed : null,
          borderRadius: AppRadius.buttonRadius,
          child: Container(
            padding: padding ?? AppSpacing.buttonPaddingDefault,
            decoration: BoxDecoration(
              gradient: gradientColors != null
                  ? LinearGradient(
                      colors: isEnabled
                          ? gradientColors!
                          : [Colors.grey, Colors.grey.shade600],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    )
                  : null,
              color: gradientColors == null
                  ? (isEnabled
                        ? (backgroundColor ?? AppColors.accentPrimary)
                        : Colors.grey)
                  : null,
              borderRadius: AppRadius.buttonRadius,
              boxShadow: isEnabled ? AppShadows.button : null,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (isLoading)
                  SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(
                        textColor ?? Colors.white,
                      ),
                    ),
                  )
                else if (icon != null) ...[
                  Icon(icon, size: 20, color: textColor ?? Colors.white),
                  const SizedBox(width: AppSpacing.sm),
                ],

                if (!isLoading)
                  Text(
                    text,
                    style: AppTypography.button.copyWith(
                      color: textColor ?? Colors.white,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton secondaire avec bordure
class SecondaryButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool isDisabled;
  final IconData? icon;
  final double? width;
  final double? height;
  final Color? borderColor;
  final Color? textColor;
  final Color? backgroundColor;
  final EdgeInsets? padding;

  const SecondaryButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.isDisabled = false,
    this.icon,
    this.width,
    this.height,
    this.borderColor,
    this.textColor,
    this.backgroundColor,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final isEnabled = !isDisabled && !isLoading && onPressed != null;
    final effectiveBorderColor = borderColor ?? AppColors.accentPrimary;
    final effectiveTextColor = textColor ?? AppColors.accentPrimary;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      width: width,
      height: height ?? AppSpacing.buttonHeight,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: isEnabled ? onPressed : null,
          borderRadius: AppRadius.buttonRadius,
          child: Container(
            padding: padding ?? AppSpacing.buttonPaddingDefault,
            decoration: BoxDecoration(
              color: backgroundColor ?? Colors.transparent,
              border: Border.all(
                color: isEnabled ? effectiveBorderColor : Colors.grey,
                width: 1,
              ),
              borderRadius: AppRadius.buttonRadius,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (isLoading)
                  SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(
                        isEnabled ? effectiveTextColor : Colors.grey,
                      ),
                    ),
                  )
                else if (icon != null) ...[
                  Icon(
                    icon,
                    size: 20,
                    color: isEnabled ? effectiveTextColor : Colors.grey,
                  ),
                  const SizedBox(width: AppSpacing.sm),
                ],

                if (!isLoading)
                  Text(
                    text,
                    style: AppTypography.button.copyWith(
                      color: isEnabled ? effectiveTextColor : Colors.grey,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton texte simple
class TextButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool isDisabled;
  final IconData? icon;
  final Color? textColor;
  final EdgeInsets? padding;

  const TextButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.isDisabled = false,
    this.icon,
    this.textColor,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final isEnabled = !isDisabled && !isLoading && onPressed != null;
    final effectiveTextColor = textColor ?? AppColors.accentPrimary;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: isEnabled ? onPressed : null,
        borderRadius: AppRadius.buttonRadius,
        child: Container(
          padding:
              padding ??
              AppSpacing.symmetric(
                horizontal: AppSpacing.base,
                vertical: AppSpacing.md,
              ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (isLoading)
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(
                      isEnabled ? effectiveTextColor : Colors.grey,
                    ),
                  ),
                )
              else if (icon != null) ...[
                Icon(
                  icon,
                  size: 18,
                  color: isEnabled ? effectiveTextColor : Colors.grey,
                ),
                const SizedBox(width: AppSpacing.sm),
              ],

              if (!isLoading)
                Text(
                  text,
                  style: AppTypography.button.copyWith(
                    color: isEnabled ? effectiveTextColor : Colors.grey,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Bouton avec gradient personnalisé
class GradientButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool isDisabled;
  final IconData? icon;
  final double? width;
  final double? height;
  final List<Color> gradientColors;
  final Color? textColor;
  final EdgeInsets? padding;

  const GradientButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.isDisabled = false,
    this.icon,
    this.width,
    this.height,
    required this.gradientColors,
    this.textColor,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    return PrimaryButton(
      text: text,
      onPressed: onPressed,
      isLoading: isLoading,
      isDisabled: isDisabled,
      icon: icon,
      width: width,
      height: height,
      gradientColors: gradientColors,
      textColor: textColor,
      padding: padding,
    );
  }
}

/// Bouton flottant avec ombre et effet de lueur
class FloatingButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final IconData? icon;
  final double? width;
  final List<Color>? gradientColors;
  final Color? backgroundColor;

  const FloatingButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.icon,
    this.width,
    this.gradientColors,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: AppSpacing.buttonHeight,
      decoration: BoxDecoration(
        gradient: gradientColors != null
            ? LinearGradient(
                colors: gradientColors!,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              )
            : null,
        color: gradientColors == null
            ? (backgroundColor ?? AppColors.accentPrimary)
            : null,
        borderRadius: AppRadius.circular(AppSpacing.buttonHeight / 2),
        boxShadow: AppShadows.floatingButton,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: AppRadius.circular(AppSpacing.buttonHeight / 2),
          child: Container(
            padding: AppSpacing.symmetric(
              horizontal: AppSpacing.xl,
              vertical: AppSpacing.base,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (isLoading)
                  const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                else if (icon != null) ...[
                  Icon(icon, size: 20, color: Colors.white),
                  const SizedBox(width: AppSpacing.sm),
                ],

                if (!isLoading)
                  Text(
                    text,
                    style: AppTypography.button.copyWith(color: Colors.white),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bouton avec état de succès
class SuccessButton extends StatefulWidget {
  final String text;
  final String successText;
  final VoidCallback? onPressed;
  final bool showSuccess;
  final Duration successDuration;

  const SuccessButton({
    super.key,
    required this.text,
    this.successText = 'Succès!',
    this.onPressed,
    this.showSuccess = false,
    this.successDuration = const Duration(seconds: 2),
  });

  @override
  State<SuccessButton> createState() => _SuccessButtonState();
}

class _SuccessButtonState extends State<SuccessButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _scaleAnimation;
  bool _isSuccess = false;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
    _scaleAnimation = Tween<double>(begin: 1.0, end: 1.1).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.elasticOut),
    );
  }

  @override
  void didUpdateWidget(SuccessButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.showSuccess && !oldWidget.showSuccess) {
      _showSuccess();
    }
  }

  void _showSuccess() {
    setState(() {
      _isSuccess = true;
    });
    _animationController.forward().then((_) {
      _animationController.reverse();
      Future.delayed(widget.successDuration, () {
        if (mounted) {
          setState(() {
            _isSuccess = false;
          });
        }
      });
    });
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _scaleAnimation,
      builder: (context, child) {
        return Transform.scale(
          scale: _scaleAnimation.value,
          child: PrimaryButton(
            text: _isSuccess ? widget.successText : widget.text,
            onPressed: _isSuccess ? null : widget.onPressed,
            backgroundColor: _isSuccess ? AppColors.accentSuccess : null,
            icon: _isSuccess ? Icons.check : null,
          ),
        );
      },
    );
  }
}
