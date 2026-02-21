import 'package:flutter/material.dart';
import '../../core/theme/design_tokens.dart';

/// Primary button component following MinimalAuthMobile design system
class AppPrimaryButton extends StatefulWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool enabled;
  final Widget? icon;
  final Color? color;

  const AppPrimaryButton({
    super.key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.enabled = true,
    this.icon,
    this.color,
  });

  @override
  State<AppPrimaryButton> createState() => _AppPrimaryButtonState();
}

class _AppPrimaryButtonState extends State<AppPrimaryButton>
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

  bool get _isInteractive =>
      widget.enabled && !widget.isLoading && widget.onPressed != null;

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
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              curve: Curves.easeInOut,
              height: DesignTokens.primaryButtonHeight,
              decoration: BoxDecoration(
                color: _getBackgroundColor(),
                borderRadius: BorderRadius.circular(DesignTokens.radiusButton),
              ),
              child: Center(
                child: widget.isLoading
                    ? SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            _isInteractive
                                ? DesignTokens.textOnPrimary
                                : DesignTokens.neutral500,
                          ),
                        ),
                      )
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          if (widget.icon != null) ...[
                            IconTheme(
                              data: IconThemeData(
                                color: _getTextColor(),
                                size: 20,
                              ),
                              child: widget.icon!,
                            ),
                            const SizedBox(width: DesignTokens.spacing8),
                          ],
                          Text(
                            widget.text,
                            style: DesignTokens.buttonStyle(
                              color: _getTextColor(),
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Color _getBackgroundColor() {
    if (!widget.enabled || widget.isLoading) {
      return DesignTokens.neutral300;
    }
    return widget.color ?? DesignTokens.primaryBase;
  }

  Color _getTextColor() {
    if (!widget.enabled || widget.isLoading) {
      return DesignTokens.neutral500;
    }
    return DesignTokens.textOnPrimary;
  }
}
