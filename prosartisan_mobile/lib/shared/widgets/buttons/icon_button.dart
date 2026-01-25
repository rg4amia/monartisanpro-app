import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Bouton d'icône selon le design system
/// Forme: cercle ou carré arrondi
/// Size: 40-48px
/// Background: semi-transparent overlay
/// Icon size: 20-24px
class CustomIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? iconColor;
  final bool isCircular;
  final bool hasShadow;
  final String? tooltip;
  final int? badgeCount;

  const CustomIconButton({
    super.key,
    required this.icon,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.iconColor,
    this.isCircular = true,
    this.hasShadow = false,
    this.tooltip,
    this.badgeCount,
  });

  @override
  Widget build(BuildContext context) {
    final buttonSize = size ?? AppSpacing.minTouchTarget;
    final iconSize = buttonSize * 0.5;

    Widget button = Container(
      width: buttonSize,
      height: buttonSize,
      decoration: BoxDecoration(
        color: backgroundColor ?? AppColors.overlayLight,
        borderRadius: isCircular
            ? AppRadius.circular(buttonSize / 2)
            : AppRadius.mediumRadius,
        boxShadow: hasShadow ? AppShadows.card : null,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: isCircular
              ? AppRadius.circular(buttonSize / 2)
              : AppRadius.mediumRadius,
          child: Center(
            child: Icon(
              icon,
              size: iconSize,
              color: iconColor ?? AppColors.textPrimary,
            ),
          ),
        ),
      ),
    );

    // Ajouter le badge si nécessaire
    if (badgeCount != null && badgeCount! > 0) {
      button = Stack(
        clipBehavior: Clip.none,
        children: [
          button,
          Positioned(
            right: -2,
            top: -2,
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: AppColors.accentDanger,
                borderRadius: AppRadius.circular(10),
              ),
              constraints: const BoxConstraints(minWidth: 20, minHeight: 20),
              child: Text(
                badgeCount! > 99 ? '99+' : badgeCount.toString(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
            ),
          ),
        ],
      );
    }

    // Ajouter le tooltip si nécessaire
    if (tooltip != null) {
      button = Tooltip(message: tooltip!, child: button);
    }

    return button;
  }
}

/// Bouton d'icône avec effet de pression
class PressableIconButton extends StatefulWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? iconColor;
  final bool isCircular;

  const PressableIconButton({
    super.key,
    required this.icon,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.iconColor,
    this.isCircular = true,
  });

  @override
  State<PressableIconButton> createState() => _PressableIconButtonState();
}

class _PressableIconButtonState extends State<PressableIconButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 100),
      vsync: this,
    );
    _scaleAnimation = Tween<double>(begin: 1.0, end: 0.95).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => _animationController.forward(),
      onTapUp: (_) => _animationController.reverse(),
      onTapCancel: () => _animationController.reverse(),
      onTap: widget.onPressed,
      child: AnimatedBuilder(
        animation: _scaleAnimation,
        builder: (context, child) {
          return Transform.scale(
            scale: _scaleAnimation.value,
            child: CustomIconButton(
              icon: widget.icon,
              size: widget.size,
              backgroundColor: widget.backgroundColor,
              iconColor: widget.iconColor,
              isCircular: widget.isCircular,
              hasShadow: true,
            ),
          );
        },
      ),
    );
  }
}

/// Bouton d'icône avec gradient
class GradientIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final double? size;
  final List<Color> gradientColors;
  final Color? iconColor;
  final bool isCircular;

  const GradientIconButton({
    super.key,
    required this.icon,
    this.onPressed,
    this.size,
    required this.gradientColors,
    this.iconColor,
    this.isCircular = true,
  });

  @override
  Widget build(BuildContext context) {
    final buttonSize = size ?? AppSpacing.minTouchTarget;
    final iconSize = buttonSize * 0.5;

    return Container(
      width: buttonSize,
      height: buttonSize,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: gradientColors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: isCircular
            ? AppRadius.circular(buttonSize / 2)
            : AppRadius.mediumRadius,
        boxShadow: AppShadows.card,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onPressed,
          borderRadius: isCircular
              ? AppRadius.circular(buttonSize / 2)
              : AppRadius.mediumRadius,
          child: Center(
            child: Icon(icon, size: iconSize, color: iconColor ?? Colors.white),
          ),
        ),
      ),
    );
  }
}

/// Bouton d'icône flottant
class FloatingIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? iconColor;
  final String? heroTag;

  const FloatingIconButton({
    super.key,
    required this.icon,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.iconColor,
    this.heroTag,
  });

  @override
  Widget build(BuildContext context) {
    final buttonSize = size ?? 56.0;

    return FloatingActionButton(
      onPressed: onPressed,
      backgroundColor: backgroundColor ?? AppColors.accentPrimary,
      foregroundColor: iconColor ?? Colors.white,
      elevation: 8,
      heroTag: heroTag,
      child: Icon(icon, size: buttonSize * 0.4),
    );
  }
}

/// Bouton d'icône avec texte en dessous
class IconButtonWithLabel extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? iconColor;
  final Color? textColor;
  final bool isCircular;

  const IconButtonWithLabel({
    super.key,
    required this.icon,
    required this.label,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.iconColor,
    this.textColor,
    this.isCircular = true,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onPressed,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomIconButton(
            icon: icon,
            size: size,
            backgroundColor: backgroundColor,
            iconColor: iconColor,
            isCircular: isCircular,
            hasShadow: true,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: textColor ?? AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

/// Bouton d'icône avec état actif/inactif
class ToggleIconButton extends StatelessWidget {
  final IconData icon;
  final IconData? activeIcon;
  final bool isActive;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? activeBackgroundColor;
  final Color? iconColor;
  final Color? activeIconColor;
  final bool isCircular;

  const ToggleIconButton({
    super.key,
    required this.icon,
    this.activeIcon,
    required this.isActive,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.activeBackgroundColor,
    this.iconColor,
    this.activeIconColor,
    this.isCircular = true,
  });

  @override
  Widget build(BuildContext context) {
    return CustomIconButton(
      icon: isActive ? (activeIcon ?? icon) : icon,
      onPressed: onPressed,
      size: size,
      backgroundColor: isActive
          ? (activeBackgroundColor ?? AppColors.accentPrimary)
          : (backgroundColor ?? AppColors.overlayLight),
      iconColor: isActive
          ? (activeIconColor ?? Colors.white)
          : (iconColor ?? AppColors.textPrimary),
      isCircular: isCircular,
      hasShadow: isActive,
    );
  }
}

/// Bouton d'icône avec animation de rotation
class RotatingIconButton extends StatefulWidget {
  final IconData icon;
  final VoidCallback? onPressed;
  final double? size;
  final Color? backgroundColor;
  final Color? iconColor;
  final bool isCircular;
  final Duration animationDuration;

  const RotatingIconButton({
    super.key,
    required this.icon,
    this.onPressed,
    this.size,
    this.backgroundColor,
    this.iconColor,
    this.isCircular = true,
    this.animationDuration = const Duration(milliseconds: 500),
  });

  @override
  State<RotatingIconButton> createState() => _RotatingIconButtonState();
}

class _RotatingIconButtonState extends State<RotatingIconButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _rotationAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: widget.animationDuration,
      vsync: this,
    );
    _rotationAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _animationController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  void _handleTap() {
    _animationController.forward().then((_) {
      _animationController.reset();
    });
    widget.onPressed?.call();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _handleTap,
      child: AnimatedBuilder(
        animation: _rotationAnimation,
        builder: (context, child) {
          return Transform.rotate(
            angle: _rotationAnimation.value * 2 * 3.14159,
            child: CustomIconButton(
              icon: widget.icon,
              size: widget.size,
              backgroundColor: widget.backgroundColor,
              iconColor: widget.iconColor,
              isCircular: widget.isCircular,
              hasShadow: true,
            ),
          );
        },
      ),
    );
  }
}
