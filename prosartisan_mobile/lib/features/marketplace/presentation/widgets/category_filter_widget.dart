import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

class CategoryFilterWidget extends StatelessWidget {
  final TradeCategory? selectedCategory;
  final Function(TradeCategory?) onCategoryChanged;
  final bool showAllOption;

  const CategoryFilterWidget({
    super.key,
    required this.selectedCategory,
    required this.onCategoryChanged,
    this.showAllOption = false,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          if (showAllOption) ...[
            _buildCategoryChip(
              label: 'Tous',
              icon: Icons.all_inclusive,
              isSelected: selectedCategory == null,
              onTap: () => onCategoryChanged(null),
            ),
            SizedBox(width: AppSpacing.sm),
          ],

          ...TradeCategory.values.map(
            (category) => Padding(
              padding: EdgeInsets.only(right: AppSpacing.sm),
              child: _buildCategoryChip(
                label: category.displayName,
                icon: _getCategoryIcon(category),
                isSelected: selectedCategory == category,
                onTap: () => onCategoryChanged(category),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryChip({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return FilterChip(
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 16,
            color: isSelected ? AppColors.textPrimary : AppColors.accentPrimary,
          ),
          SizedBox(width: AppSpacing.xs),
          Text(label),
        ],
      ),
      selected: isSelected,
      onSelected: (_) => onTap(),
      selectedColor: AppColors.accentPrimary,
      backgroundColor: AppColors.cardBg,
      checkmarkColor: AppColors.textPrimary,
      shape: RoundedRectangleBorder(
        borderRadius: AppRadius.buttonRadius,
        side: BorderSide(
          color: isSelected ? AppColors.accentPrimary : AppColors.overlayMedium,
        ),
      ),
      labelStyle: AppTypography.bodySmall.copyWith(
        color: isSelected ? AppColors.textPrimary : AppColors.accentPrimary,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
      ),
    );
  }

  IconData _getCategoryIcon(TradeCategory category) {
    switch (category) {
      case TradeCategory.plumber:
        return Icons.plumbing;
      case TradeCategory.electrician:
        return Icons.electrical_services;
      case TradeCategory.mason:
        return Icons.construction;
    }
  }
}
