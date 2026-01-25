import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Transaction filter chips widget
class TransactionFilterChips extends StatelessWidget {
  final RxString selectedFilter;
  final ValueChanged<String> onFilterChanged;

  const TransactionFilterChips({
    super.key,
    required this.selectedFilter,
    required this.onFilterChanged,
  });

  @override
  Widget build(BuildContext context) {
    final filters = [
      {'key': 'all', 'label': 'Toutes'},
      {'key': 'ESCROW_BLOCK', 'label': 'Séquestre'},
      {'key': 'MATERIAL_RELEASE', 'label': 'Matériaux'},
      {'key': 'LABOR_RELEASE', 'label': 'Main d\'œuvre'},
      {'key': 'REFUND', 'label': 'Remboursement'},
    ];

    return SizedBox(
      height: 40,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: filters.length,
        itemBuilder: (context, index) {
          final filter = filters[index];
          final key = filter['key']!;
          final label = filter['label']!;

          return Padding(
            padding: EdgeInsets.only(
              left: index == 0 ? 0 : AppSpacing.sm,
              right: index == filters.length - 1 ? 0 : 0,
            ),
            child: Obx(() {
              final isSelected = selectedFilter.value == key;

              return FilterChip(
                label: Text(label),
                selected: isSelected,
                onSelected: (selected) {
                  if (selected) {
                    onFilterChanged(key);
                  }
                },
                backgroundColor: AppColors.cardBg,
                selectedColor: AppColors.accentPrimary.withValues(alpha: 0.2),
                checkmarkColor: AppColors.accentPrimary,
                labelStyle: AppTypography.bodySmall.copyWith(
                  color: isSelected
                      ? AppColors.accentPrimary
                      : AppColors.textSecondary,
                  fontWeight: isSelected ? FontWeight.w500 : FontWeight.normal,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: AppRadius.buttonRadius,
                ),
              );
            }),
          );
        },
      ),
    );
  }
}
