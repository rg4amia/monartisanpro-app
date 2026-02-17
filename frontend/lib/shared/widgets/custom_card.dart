import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

class CustomCard extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets? padding;
  final double? elevation;
  final Color? backgroundColor;
  final BorderRadius? borderRadius;

  const CustomCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding,
    this.elevation,
    this.backgroundColor,
    this.borderRadius,
  });

  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: padding ?? const EdgeInsets.all(Spacing.base),
      child: child,
    );

    return Card(
      elevation: elevation ?? 2,
      color: backgroundColor ?? AppColors.lightCard,
      shape: RoundedRectangleBorder(
        borderRadius: borderRadius ?? BorderRadius.circular(Spacing.radiusMd),
      ),
      child: onTap != null
          ? InkWell(
              onTap: onTap,
              borderRadius:
                  borderRadius ?? BorderRadius.circular(Spacing.radiusMd),
              child: content,
            )
          : content,
    );
  }
}
