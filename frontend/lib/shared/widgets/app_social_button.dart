import 'package:flutter/material.dart';
import '../../core/theme/design_tokens.dart';

/// Social login button component following MinimalAuthMobile design system
class AppSocialButton extends StatefulWidget {
  final String text;
  final Widget icon;
  final VoidCallback? onPressed;
  final bool enabled;

  const AppSocialButton({
    super.key,
    required this.text,
    required this.icon,
    this.onPressed,
    this.enabled = true,
  });

  @override
  State<AppSocialButton> createState() => _AppSocialButtonState();
}

class _AppSocialButtonState extends State<AppSocialButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _scaleController;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();
    _scaleController = AnimationController(
      vsync: this,
      duration: DesignTokens.durationFast,
    );
    _scaleAnimation =
        Tween<double>(begin: 1.0, end: DesignTokens.buttonPressScale).animate(
          CurvedAnimation(parent: _scaleController, curve: DesignTokens.easing),
        );
  }

  @override
  void dispose() {
    _scaleController.dispose();
    super.dispose();
  }

  void _handleTapDown(TapDownDetails details) {
    if (_isInteractive) {
      _scaleController.forward();
    }
  }

  void _handleTapUp(TapUpDetails details) {
    if (_isInteractive) {
      _scaleController.reverse();
    }
  }

  void _handleTapCancel() {
    if (_isInteractive) {
      _scaleController.reverse();
    }
  }

  bool get _isInteractive => widget.enabled && widget.onPressed != null;

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scaleAnimation,
      child: GestureDetector(
        onTapDown: _handleTapDown,
        onTapUp: _handleTapUp,
        onTapCancel: _handleTapCancel,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: _isInteractive ? widget.onPressed : null,
            borderRadius: BorderRadius.circular(DesignTokens.radiusButton),
            child: Container(
              height: DesignTokens.socialButtonHeight,
              decoration: BoxDecoration(
                color: DesignTokens.backgroundCard,
                borderRadius: BorderRadius.circular(DesignTokens.radiusButton),
                border: Border.all(color: DesignTokens.neutral300, width: 1),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconTheme(
                    data: const IconThemeData(size: 24),
                    child: widget.icon,
                  ),
                  const SizedBox(width: DesignTokens.spacing12),
                  Text(
                    widget.text,
                    style: DesignTokens.buttonStyle(
                      color: DesignTokens.neutral900,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
