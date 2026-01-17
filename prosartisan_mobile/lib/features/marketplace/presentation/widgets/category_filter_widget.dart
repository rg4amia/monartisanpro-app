import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

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
            const SizedBox(width: 8),
          ],

          ...TradeCategory.values
              .map(
                (category) => Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: _buildCategoryChip(
                    label: category.displayName,
                    icon: _getCategoryIcon(category),
                    isSelected: selectedCategory == category,
                    onTap: () => onCategoryChanged(category),
                  ),
                ),
              )
              .toList(),
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
            color: isSelected ? Colors.white : Colors.blue[600],
          ),
          const SizedBox(width: 4),
          Text(label),
        ],
      ),
      selected: isSelected,
      onSelected: (_) => onTap(),
      selectedColor: Colors.blue[600],
      checkmarkColor: Colors.white,
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : Colors.blue[600],
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
