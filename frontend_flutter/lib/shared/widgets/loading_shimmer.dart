import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../../core/theme/app_colors.dart';

class LoadingShimmer extends StatelessWidget {
  final double height;
  final double? width;
  final double borderRadius;

  const LoadingShimmer({
    super.key,
    this.height = 80,
    this.width,
    this.borderRadius = 14,
  });

  static Widget card({double height = 100}) => LoadingShimmer(height: height);

  static Widget list({int count = 4}) => Column(
        children: List.generate(
          count,
          (_) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: LoadingShimmer(height: 90),
          ),
        ),
      );

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.border,
      highlightColor: AppColors.card,
      child: Container(
        width: width ?? double.infinity,
        height: height,
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(borderRadius),
        ),
      ),
    );
  }
}
