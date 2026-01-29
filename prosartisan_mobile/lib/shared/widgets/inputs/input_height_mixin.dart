import 'package:flutter/material.dart';
import '../../../core/theme/app_spacing.dart';

/// Mixin to apply consistent input height to TextField and TextFormField widgets
mixin InputHeightMixin {
  /// Wraps a TextField with consistent height
  Widget withInputHeight(Widget textField, {double? customHeight}) {
    return SizedBox(
      height: customHeight ?? AppSpacing.inputHeight,
      child: textField,
    );
  }

  /// Creates a TextField with consistent height and styling
  Widget buildTextField({
    TextEditingController? controller,
    String? labelText,
    String? hintText,
    IconData? prefixIcon,
    Widget? suffixIcon,
    bool obscureText = false,
    TextInputType? keyboardType,
    String? Function(String?)? validator,
    void Function(String)? onChanged,
    void Function(String)? onSubmitted,
    int maxLines = 1,
    bool enabled = true,
    bool readOnly = false,
    VoidCallback? onTap,
    TextStyle? style,
    InputDecoration? decoration,
    double? height,
  }) {
    final inputHeight =
        height ?? (maxLines == 1 ? AppSpacing.inputHeight : null);

    Widget textField = TextFormField(
      controller: controller,
      obscureText: obscureText,
      keyboardType: keyboardType,
      validator: validator,
      onChanged: onChanged,
      onFieldSubmitted: onSubmitted,
      maxLines: maxLines,
      enabled: enabled,
      readOnly: readOnly,
      onTap: onTap,
      style: style,
      decoration: decoration,
    );

    if (inputHeight != null) {
      return SizedBox(height: inputHeight, child: textField);
    }

    return textField;
  }
}

/// Extension to easily apply input height to existing TextFields
extension TextFieldExtension on Widget {
  /// Wraps the widget with consistent input height
  Widget withInputHeight({double? customHeight}) {
    return SizedBox(
      height: customHeight ?? AppSpacing.inputHeight,
      child: this,
    );
  }
}
