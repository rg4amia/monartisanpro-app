import 'package:flutter/material.dart';
import 'custom_select2.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

/// Example usage of CustomSelect2 widget
/// This file demonstrates various use cases for the Select2-like dropdown
class CustomSelect2Example extends StatefulWidget {
  const CustomSelect2Example({super.key});

  @override
  State<CustomSelect2Example> createState() => _CustomSelect2ExampleState();
}

class _CustomSelect2ExampleState extends State<CustomSelect2Example> {
  String? selectedCountry;
  List<String> selectedLanguages = [];
  Map<String, dynamic>? selectedOption;

  final countries = [
    'France',
    'Côte d\'Ivoire',
    'Sénégal',
    'Mali',
    'Burkina Faso',
    'Bénin',
    'Togo',
    'Niger',
    'Guinée',
    'Cameroun',
  ];

  final languages = [
    'Français',
    'Anglais',
    'Espagnol',
    'Allemand',
    'Italien',
    'Portugais',
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('CustomSelect2 Examples')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Example 1: Simple Select with Search
            const Text(
              'Example 1: Simple Select with Search',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: Spacing.md),
            CustomSelect2<String>(
              selectedItem: selectedCountry,
              items: countries,
              itemAsString: (item) => item,
              label: 'Pays',
              hint: 'Sélectionner un pays',
              prefixIcon: Icons.flag,
              isRequired: true,
              showSearchBox: true,
              searchHint: 'Rechercher un pays...',
              onChanged: (value) {
                setState(() {
                  selectedCountry = value;
                });
              },
              validator: (value) {
                if (value == null) {
                  return 'Veuillez sélectionner un pays';
                }
                return null;
              },
            ),
            const SizedBox(height: Spacing.xxl),

            // Example 2: Multi-Select
            const Text(
              'Example 2: Multi-Select',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: Spacing.md),
            CustomMultiSelect2<String>(
              selectedItems: selectedLanguages,
              items: languages,
              itemAsString: (item) => item,
              label: 'Langues parlées',
              hint: 'Sélectionner des langues',
              prefixIcon: Icons.language,
              showSearchBox: true,
              searchHint: 'Rechercher une langue...',
              onChanged: (values) {
                setState(() {
                  selectedLanguages = values;
                });
              },
            ),
            const SizedBox(height: Spacing.xxl),

            // Example 3: Select with Custom Objects
            const Text(
              'Example 3: Select with Custom Objects',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: Spacing.md),
            CustomSelect2<Map<String, dynamic>>(
              selectedItem: selectedOption,
              items: [
                {'id': 1, 'name': 'Option 1', 'icon': Icons.star},
                {'id': 2, 'name': 'Option 2', 'icon': Icons.favorite},
                {'id': 3, 'name': 'Option 3', 'icon': Icons.thumb_up},
              ],
              itemAsString: (item) => item['name'] as String,
              label: 'Options',
              hint: 'Sélectionner une option',
              prefixIcon: Icons.settings,
              showSearchBox: false,
              itemBuilder: (context, item, isSelected) {
                return Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.md,
                    vertical: Spacing.sm,
                  ),
                  decoration: BoxDecoration(
                    color: isSelected
                        ? AppColors.lightAccentPrimary.withValues(alpha: 0.1)
                        : Colors.transparent,
                  ),
                  child: Row(
                    children: [
                      Icon(
                        item['icon'] as IconData,
                        color: isSelected
                            ? AppColors.lightAccentPrimary
                            : AppColors.lightTextSecondary,
                        size: 20,
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Text(
                          item['name'] as String,
                          style: TextStyle(
                            color: isSelected
                                ? AppColors.lightAccentPrimary
                                : AppColors.lightTextPrimary,
                            fontWeight: isSelected
                                ? FontWeight.w600
                                : FontWeight.w400,
                            fontSize: 14,
                          ),
                        ),
                      ),
                      if (isSelected)
                        Icon(
                          Icons.check_circle,
                          color: AppColors.lightAccentPrimary,
                          size: 20,
                        ),
                    ],
                  ),
                );
              },
              onChanged: (value) {
                setState(() {
                  selectedOption = value;
                });
              },
            ),
            const SizedBox(height: Spacing.xxl),

            // Example 4: Dark Mode
            Container(
              padding: const EdgeInsets.all(Spacing.lg),
              decoration: BoxDecoration(
                color: AppColors.darkBackground,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Example 4: Dark Mode',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: AppColors.darkTextPrimary,
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  CustomSelect2<String>(
                    selectedItem: selectedCountry,
                    items: countries,
                    itemAsString: (item) => item,
                    label: 'Pays',
                    hint: 'Sélectionner un pays',
                    prefixIcon: Icons.flag,
                    showSearchBox: true,
                    searchHint: 'Rechercher un pays...',
                    isDarkMode: true,
                    onChanged: (value) {
                      setState(() {
                        selectedCountry = value;
                      });
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.xxl),

            // Display selected values
            Container(
              padding: const EdgeInsets.all(Spacing.lg),
              decoration: BoxDecoration(
                color: AppColors.lightBackground,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                border: Border.all(
                  color: AppColors.lightTextTertiary.withValues(alpha: 0.2),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Selected Values:',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: Spacing.md),
                  Text('Country: ${selectedCountry ?? "None"}'),
                  const SizedBox(height: Spacing.sm),
                  Text(
                    'Languages: ${selectedLanguages.isEmpty ? "None" : selectedLanguages.join(", ")}',
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text('Option: ${selectedOption?['name'] ?? "None"}'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
