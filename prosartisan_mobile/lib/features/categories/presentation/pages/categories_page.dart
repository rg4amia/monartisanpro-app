import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../shared/widgets/cards/category_card.dart';
import '../../../../shared/widgets/navigation/bottom_nav_bar.dart';
import '../../../../shared/widgets/common/search_bar.dart';

/// Page des catégories
class CategoriesPage extends StatefulWidget {
  const CategoriesPage({super.key});

  @override
  State<CategoriesPage> createState() => _CategoriesPageState();
}

class _CategoriesPageState extends State<CategoriesPage> {
  String? selectedCategoryId;

  final categories = [
    const CategoryModel(
      id: 'plumbing',
      name: 'Plomberie',
      icon: Icons.plumbing,
      iconColor: AppColors.accentPrimary,
    ),
    const CategoryModel(
      id: 'electrical',
      name: 'Électricité',
      icon: Icons.electrical_services,
      iconColor: AppColors.accentWarning,
    ),
    const CategoryModel(
      id: 'cleaning',
      name: 'Ménage',
      icon: Icons.cleaning_services,
      iconColor: AppColors.accentSuccess,
    ),
    const CategoryModel(
      id: 'gardening',
      name: 'Jardinage',
      icon: Icons.grass,
      iconColor: AppColors.accentSuccess,
    ),
    const CategoryModel(
      id: 'painting',
      name: 'Peinture',
      icon: Icons.format_paint,
      iconColor: AppColors.accentDanger,
    ),
    const CategoryModel(
      id: 'carpentry',
      name: 'Menuiserie',
      icon: Icons.carpenter,
      iconColor: AppColors.accentWarning,
    ),
    const CategoryModel(
      id: 'appliance',
      name: 'Électroménager',
      icon: Icons.kitchen,
      iconColor: AppColors.accentPrimary,
    ),
    const CategoryModel(
      id: 'security',
      name: 'Sécurité',
      icon: Icons.security,
      iconColor: AppColors.accentDanger,
    ),
    const CategoryModel(
      id: 'moving',
      name: 'Déménagement',
      icon: Icons.local_shipping,
      iconColor: AppColors.accentWarning,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Catégories',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: AppSpacing.screenPaddingAll,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Barre de recherche
            SearchBarWidget(
              hintText: 'Rechercher une catégorie...',
              onChanged: (query) {
                // Filtrer les catégories
              },
            ),

            const SizedBox(height: AppSpacing.xl),

            // Grille des catégories
            CategoryGrid(
              categories: categories,
              activeCategory: selectedCategoryId,
              onCategorySelected: (category) {
                setState(() {
                  selectedCategoryId = selectedCategoryId == category.id
                      ? null
                      : category.id;
                });
              },
              crossAxisCount: 3,
            ),
          ],
        ),
      ),
      bottomNavigationBar: CustomBottomNavBar(
        items: DefaultBottomNavItems.items,
        currentRoute: 'categories',
        onItemTapped: (route) {
          if (route != 'categories') {
            Navigator.pushReplacementNamed(context, '/$route');
          }
        },
      ),
    );
  }
}
