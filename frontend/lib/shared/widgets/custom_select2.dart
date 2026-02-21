import 'package:flutter/material.dart';
import 'package:dropdown_search/dropdown_search.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

/// Custom Select2-like dropdown with search functionality
/// Supports single and multi-selection modes
class CustomSelect2<T> extends StatelessWidget {
  final T? selectedItem;
  final List<T> items;
  final String Function(T) itemAsString;
  final void Function(T?)? onChanged;
  final String? label;
  final String? hint;
  final IconData? prefixIcon;
  final bool enabled;
  final bool showSearchBox;
  final String? Function(T?)? validator;
  final bool isRequired;
  final String searchHint;
  final Widget Function(BuildContext, T, bool)? itemBuilder;
  final bool isDarkMode;

  const CustomSelect2({
    super.key,
    this.selectedItem,
    required this.items,
    required this.itemAsString,
    this.onChanged,
    this.label,
    this.hint,
    this.prefixIcon,
    this.enabled = true,
    this.showSearchBox = true,
    this.validator,
    this.isRequired = false,
    this.searchHint = 'Rechercher...',
    this.itemBuilder,
    this.isDarkMode = false,
  });

  @override
  Widget build(BuildContext context) {
    final backgroundColor = isDarkMode
        ? AppColors.darkCard
        : AppColors.lightBackground;
    final textColor = isDarkMode
        ? AppColors.darkTextPrimary
        : AppColors.lightTextPrimary;
    final borderColor = isDarkMode
        ? AppColors.overlayLight
        : AppColors.lightTextTertiary.withValues(alpha: 0.2);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null) ...[
          Row(
            children: [
              Text(
                label!,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: textColor,
                ),
              ),
              if (isRequired)
                Text(
                  ' *',
                  style: TextStyle(
                    color: isDarkMode
                        ? AppColors.darkAccentDanger
                        : AppColors.lightAccentDanger,
                    fontSize: 14,
                  ),
                ),
            ],
          ),
          const SizedBox(height: Spacing.sm),
        ],
        Container(
          decoration: BoxDecoration(
            color: backgroundColor,
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            border: Border.all(color: borderColor, width: 1.5),
          ),
          child: DropdownSearch<T>(
            items: items,
            selectedItem: selectedItem,
            itemAsString: itemAsString,
            onChanged: enabled ? onChanged : null,
            enabled: enabled,
            dropdownDecoratorProps: DropDownDecoratorProps(
              dropdownSearchDecoration: InputDecoration(
                hintText: hint ?? 'Sélectionner...',
                hintStyle: TextStyle(
                  color: isDarkMode
                      ? AppColors.darkTextTertiary
                      : AppColors.lightTextTertiary,
                  fontSize: 15,
                ),
                prefixIcon: prefixIcon != null
                    ? Icon(
                        prefixIcon,
                        color: isDarkMode
                            ? AppColors.darkTextSecondary
                            : AppColors.lightTextSecondary,
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: Spacing.md,
                  vertical: Spacing.sm,
                ),
              ),
            ),
            popupProps: PopupProps.menu(
              showSearchBox: showSearchBox,
              searchFieldProps: TextFieldProps(
                decoration: InputDecoration(
                  hintText: searchHint,
                  prefixIcon: Icon(
                    Icons.search,
                    color: isDarkMode
                        ? AppColors.darkTextSecondary
                        : AppColors.lightTextSecondary,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                    borderSide: BorderSide(color: borderColor),
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: Spacing.md,
                    vertical: Spacing.sm,
                  ),
                ),
              ),
              itemBuilder: itemBuilder ?? _defaultItemBuilder,
              menuProps: MenuProps(
                backgroundColor: backgroundColor,
                elevation: 8,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
              ),
              emptyBuilder: (context, searchEntry) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.search_off,
                        size: 48,
                        color: isDarkMode
                            ? AppColors.darkTextTertiary
                            : AppColors.lightTextTertiary,
                      ),
                      const SizedBox(height: Spacing.md),
                      Text(
                        'Aucun résultat trouvé',
                        style: TextStyle(
                          color: isDarkMode
                              ? AppColors.darkTextSecondary
                              : AppColors.lightTextSecondary,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            validator: validator,
          ),
        ),
      ],
    );
  }

  Widget _defaultItemBuilder(BuildContext context, T item, bool isSelected) {
    final textColor = isDarkMode
        ? AppColors.darkTextPrimary
        : AppColors.lightTextPrimary;
    final selectedColor = isDarkMode
        ? AppColors.darkAccentPrimary
        : AppColors.lightAccentPrimary;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.md,
        vertical: Spacing.sm,
      ),
      decoration: BoxDecoration(
        color: isSelected
            ? selectedColor.withValues(alpha: 0.1)
            : Colors.transparent,
      ),
      child: Row(
        children: [
          if (isSelected)
            Icon(Icons.check_circle, color: selectedColor, size: 20),
          if (isSelected) const SizedBox(width: Spacing.sm),
          Expanded(
            child: Text(
              itemAsString(item),
              style: TextStyle(
                color: isSelected ? selectedColor : textColor,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Multi-select version of CustomSelect2
class CustomMultiSelect2<T> extends StatelessWidget {
  final List<T> selectedItems;
  final List<T> items;
  final String Function(T) itemAsString;
  final void Function(List<T>) onChanged;
  final String? label;
  final String? hint;
  final IconData? prefixIcon;
  final bool enabled;
  final bool showSearchBox;
  final String? Function(List<T>?)? validator;
  final bool isRequired;
  final String searchHint;
  final bool isDarkMode;

  const CustomMultiSelect2({
    super.key,
    required this.selectedItems,
    required this.items,
    required this.itemAsString,
    required this.onChanged,
    this.label,
    this.hint,
    this.prefixIcon,
    this.enabled = true,
    this.showSearchBox = true,
    this.validator,
    this.isRequired = false,
    this.searchHint = 'Rechercher...',
    this.isDarkMode = false,
  });

  @override
  Widget build(BuildContext context) {
    final backgroundColor = isDarkMode
        ? AppColors.darkCard
        : AppColors.lightBackground;
    final textColor = isDarkMode
        ? AppColors.darkTextPrimary
        : AppColors.lightTextPrimary;
    final borderColor = isDarkMode
        ? AppColors.overlayLight
        : AppColors.lightTextTertiary.withValues(alpha: 0.2);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null) ...[
          Row(
            children: [
              Text(
                label!,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: textColor,
                ),
              ),
              if (isRequired)
                Text(
                  ' *',
                  style: TextStyle(
                    color: isDarkMode
                        ? AppColors.darkAccentDanger
                        : AppColors.lightAccentDanger,
                    fontSize: 14,
                  ),
                ),
            ],
          ),
          const SizedBox(height: Spacing.sm),
        ],
        Container(
          decoration: BoxDecoration(
            color: backgroundColor,
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            border: Border.all(color: borderColor, width: 1.5),
          ),
          child: DropdownSearch<T>.multiSelection(
            items: items,
            selectedItems: selectedItems,
            itemAsString: itemAsString,
            onChanged: enabled ? onChanged : null,
            enabled: enabled,
            dropdownDecoratorProps: DropDownDecoratorProps(
              dropdownSearchDecoration: InputDecoration(
                hintText: hint ?? 'Sélectionner...',
                hintStyle: TextStyle(
                  color: isDarkMode
                      ? AppColors.darkTextTertiary
                      : AppColors.lightTextTertiary,
                  fontSize: 15,
                ),
                prefixIcon: prefixIcon != null
                    ? Icon(
                        prefixIcon,
                        color: isDarkMode
                            ? AppColors.darkTextSecondary
                            : AppColors.lightTextSecondary,
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: Spacing.md,
                  vertical: Spacing.sm,
                ),
              ),
            ),
            popupProps: PopupPropsMultiSelection.menu(
              showSearchBox: showSearchBox,
              searchFieldProps: TextFieldProps(
                decoration: InputDecoration(
                  hintText: searchHint,
                  prefixIcon: Icon(
                    Icons.search,
                    color: isDarkMode
                        ? AppColors.darkTextSecondary
                        : AppColors.lightTextSecondary,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                    borderSide: BorderSide(color: borderColor),
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: Spacing.md,
                    vertical: Spacing.sm,
                  ),
                ),
              ),
              itemBuilder: _defaultItemBuilder,
              menuProps: MenuProps(
                backgroundColor: backgroundColor,
                elevation: 8,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
              ),
              emptyBuilder: (context, searchEntry) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.xl),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.search_off,
                        size: 48,
                        color: isDarkMode
                            ? AppColors.darkTextTertiary
                            : AppColors.lightTextTertiary,
                      ),
                      const SizedBox(height: Spacing.md),
                      Text(
                        'Aucun résultat trouvé',
                        style: TextStyle(
                          color: isDarkMode
                              ? AppColors.darkTextSecondary
                              : AppColors.lightTextSecondary,
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            validator: validator,
          ),
        ),
      ],
    );
  }

  Widget _defaultItemBuilder(BuildContext context, T item, bool isSelected) {
    final textColor = isDarkMode
        ? AppColors.darkTextPrimary
        : AppColors.lightTextPrimary;
    final selectedColor = isDarkMode
        ? AppColors.darkAccentPrimary
        : AppColors.lightAccentPrimary;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.md,
        vertical: Spacing.sm,
      ),
      decoration: BoxDecoration(
        color: isSelected
            ? selectedColor.withValues(alpha: 0.1)
            : Colors.transparent,
      ),
      child: Row(
        children: [
          Checkbox(
            value: isSelected,
            onChanged: null,
            activeColor: selectedColor,
          ),
          const SizedBox(width: Spacing.sm),
          Expanded(
            child: Text(
              itemAsString(item),
              style: TextStyle(
                color: isSelected ? selectedColor : textColor,
                fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
