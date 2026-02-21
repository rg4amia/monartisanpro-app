import 'package:flutter/material.dart';
import '../../core/theme/design_tokens.dart';

/// Divider with centered label following MinimalAuthMobile design system
class AppDividerWithLabel extends StatelessWidget {
  final String label;

  const AppDividerWithLabel({super.key, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: Container(height: 1, color: DesignTokens.neutral200)),
        Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: DesignTokens.spacing16,
          ),
          child: Text(
            label,
            style: DesignTokens.captionStyle(color: DesignTokens.neutral500),
          ),
        ),
        Expanded(child: Container(height: 1, color: DesignTokens.neutral200)),
      ],
    );
  }
}
