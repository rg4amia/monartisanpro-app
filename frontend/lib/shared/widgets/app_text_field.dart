import 'package:flutter/material.dart';
import '../../core/theme/design_tokens.dart';

/// Reusable text field component following MinimalAuthMobile design system
class AppTextField extends StatefulWidget {
  final TextEditingController? controller;
  final String? hintText;
  final String? labelText;
  final bool obscureText;
  final TextInputType? keyboardType;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final void Function(String)? onChanged;
  final bool enabled;
  final int? maxLines;
  final TextInputAction? textInputAction;
  final void Function(String)? onSubmitted;
  final FocusNode? focusNode;
  final bool autofocus;

  const AppTextField({
    super.key,
    this.controller,
    this.hintText,
    this.labelText,
    this.obscureText = false,
    this.keyboardType,
    this.prefixIcon,
    this.suffixIcon,
    this.validator,
    this.onChanged,
    this.enabled = true,
    this.maxLines = 1,
    this.textInputAction,
    this.onSubmitted,
    this.focusNode,
    this.autofocus = false,
  });

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  bool _isFocused = false;
  String? _errorText;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (widget.labelText != null) ...[
          Text(
            widget.labelText!,
            style: DesignTokens.bodyStyle(color: DesignTokens.neutral700),
          ),
          const SizedBox(height: DesignTokens.spacing8),
        ],
        Focus(
          onFocusChange: (hasFocus) {
            setState(() {
              _isFocused = hasFocus;
            });
          },
          child: Container(
            height: widget.maxLines == 1 ? DesignTokens.inputHeight : null,
            decoration: BoxDecoration(
              color: widget.enabled
                  ? DesignTokens.backgroundCard
                  : DesignTokens.neutral100,
              borderRadius: BorderRadius.circular(DesignTokens.radiusInput),
              border: Border.all(color: _getBorderColor(), width: 1.5),
            ),
            child: TextFormField(
              controller: widget.controller,
              obscureText: widget.obscureText,
              keyboardType: widget.keyboardType,
              enabled: widget.enabled,
              maxLines: widget.maxLines,
              textInputAction: widget.textInputAction,
              focusNode: widget.focusNode,
              autofocus: widget.autofocus,
              style: DesignTokens.bodyStyle(
                color: widget.enabled
                    ? DesignTokens.neutral900
                    : DesignTokens.neutral500,
              ),
              decoration: InputDecoration(
                hintText: widget.hintText,
                hintStyle: DesignTokens.bodyStyle(
                  color: DesignTokens.neutral500,
                ),
                prefixIcon: widget.prefixIcon != null
                    ? IconTheme(
                        data: IconThemeData(
                          color: _isFocused
                              ? DesignTokens.primaryBase
                              : DesignTokens.neutral500,
                          size: 20,
                        ),
                        child: widget.prefixIcon!,
                      )
                    : null,
                suffixIcon: widget.suffixIcon != null
                    ? IconTheme(
                        data: IconThemeData(
                          color: DesignTokens.neutral500,
                          size: 20,
                        ),
                        child: widget.suffixIcon!,
                      )
                    : null,
                contentPadding: EdgeInsets.symmetric(
                  horizontal: DesignTokens.spacing16,
                  vertical: widget.maxLines == 1 ? 0 : DesignTokens.spacing16,
                ),
                border: InputBorder.none,
                errorStyle: const TextStyle(height: 0, fontSize: 0),
              ),
              validator: (value) {
                final error = widget.validator?.call(value);
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (mounted) {
                    setState(() {
                      _errorText = error;
                    });
                  }
                });
                return error;
              },
              onChanged: widget.onChanged,
              onFieldSubmitted: widget.onSubmitted,
            ),
          ),
        ),
        if (_errorText != null) ...[
          const SizedBox(height: DesignTokens.spacing8),
          Text(
            _errorText!,
            style: DesignTokens.captionStyle(color: DesignTokens.error),
          ),
        ],
      ],
    );
  }

  Color _getBorderColor() {
    if (_errorText != null) {
      return DesignTokens.error;
    }
    if (_isFocused) {
      return DesignTokens.primaryBase;
    }
    return DesignTokens.neutral300;
  }
}
