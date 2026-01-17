import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

class CategorySelectorWidget extends StatelessWidget {
  final TradeCategory? selectedCategory;
  final Function(TradeCategory) onCategorySelected;

  const CategorySelectorWidget({
    super.key,
    required this.selectedCategory,
    required this.onCategorySelected,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: TradeCategory.values.map((category) {
        final isSelected = selectedCategory == category;

        return Card(
          margin: const EdgeInsets.only(bottom: 8),
          color: isSelected ? Colors.blue[50] : null,
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: isSelected ? Colors.blue[600] : Colors.grey[300],
              child: Icon(
                _getCategoryIcon(category),
                color: isSelected ? Colors.white : Colors.grey[600],
              ),
            ),
            title: Text(
              category.displayName,
              style: TextStyle(
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                color: isSelected ? Colors.blue[700] : null,
              ),
            ),
            subtitle: Text(_getCategoryDescription(category)),
            trailing: isSelected
                ? Icon(Icons.check_circle, color: Colors.blue[600])
                : const Icon(Icons.radio_button_unchecked, color: Colors.grey),
            onTap: () => onCategorySelected(category),
          ),
        );
      }).toList(),
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

  String _getCategoryDescription(TradeCategory category) {
    switch (category) {
      case TradeCategory.plumber:
        return 'Installation et réparation de plomberie';
      case TradeCategory.electrician:
        return 'Installation et réparation électrique';
      case TradeCategory.mason:
        return 'Travaux de maçonnerie et construction';
    }
  }
}
