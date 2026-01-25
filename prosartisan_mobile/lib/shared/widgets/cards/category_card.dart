import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Modèle pour une catégorie
class CategoryModel {
  final String id;
  final String name;
  final IconData icon;
  final Color? iconColor;
  final String? imagePath;

  const CategoryModel({
    required this.id,
    required this.name,
    required this.icon,
    this.iconColor,
    this.imagePath,
  });
}

/// Carte de catégorie utilisée dans les grilles
/// Affiche une icône et un label avec état actif/inactif
class CategoryCard extends StatelessWidget {
  final CategoryModel category;
  final bool isActive;
  final VoidCallback? onTap;
  final double? size;

  const CategoryCard({
    super.key,
    required this.category,
    this.isActive = false,
    this.onTap,
    this.size,
  });

  @override
  Widget build(BuildContext context) {
    final cardSize = size ?? AppSpacing.categoryCardHeight;

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeInOut,
        width: cardSize,
        height: cardSize,
        decoration: BoxDecoration(
          color: isActive
              ? AppColors.categoryActive
              : AppColors.categoryInactive,
          borderRadius: AppRadius.categoryRadius,
          boxShadow: isActive ? AppShadows.card : null,
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Icône ou image
            if (category.imagePath != null)
              ClipRRect(
                borderRadius: AppRadius.circular(
                  AppSpacing.categoryIconSize / 2,
                ),
                child: Image.asset(
                  category.imagePath!,
                  width: AppSpacing.categoryIconSize,
                  height: AppSpacing.categoryIconSize,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) {
                    return _buildIcon();
                  },
                ),
              )
            else
              _buildIcon(),

            const SizedBox(height: AppSpacing.sm),

            // Label
            Text(
              category.name,
              style: AppTypography.bodySmall.copyWith(
                color: isActive ? Colors.white : AppColors.textPrimary,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
              ),
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildIcon() {
    return Container(
      width: AppSpacing.categoryIconSize,
      height: AppSpacing.categoryIconSize,
      decoration: BoxDecoration(
        color: isActive
            ? Colors.white.withOpacity(0.2)
            : AppColors.overlayLight,
        borderRadius: AppRadius.circular(AppSpacing.categoryIconSize / 2),
      ),
      child: Icon(
        category.icon,
        size: AppSpacing.iconSizeLarge,
        color: isActive
            ? Colors.white
            : (category.iconColor ?? AppColors.accentPrimary),
      ),
    );
  }
}

/// Grille de catégories avec 3 colonnes
class CategoryGrid extends StatelessWidget {
  final List<CategoryModel> categories;
  final String? activeCategory;
  final Function(CategoryModel)? onCategorySelected;
  final int crossAxisCount;
  final double? childAspectRatio;

  const CategoryGrid({
    super.key,
    required this.categories,
    this.activeCategory,
    this.onCategorySelected,
    this.crossAxisCount = 3,
    this.childAspectRatio = 1.0,
  });

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount,
        crossAxisSpacing: AppSpacing.gridGap,
        mainAxisSpacing: AppSpacing.gridGap,
        childAspectRatio: childAspectRatio ?? 1.0,
      ),
      itemCount: categories.length,
      itemBuilder: (context, index) {
        final category = categories[index];
        final isActive = activeCategory == category.id;

        return CategoryCard(
          category: category,
          isActive: isActive,
          onTap: () => onCategorySelected?.call(category),
        );
      },
    );
  }
}

/// Carte de catégorie horizontale (pour listes)
class HorizontalCategoryCard extends StatelessWidget {
  final CategoryModel category;
  final bool isActive;
  final VoidCallback? onTap;

  const HorizontalCategoryCard({
    super.key,
    required this.category,
    this.isActive = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeInOut,
        padding: AppSpacing.symmetric(
          horizontal: AppSpacing.base,
          vertical: AppSpacing.md,
        ),
        decoration: BoxDecoration(
          color: isActive
              ? AppColors.categoryActive
              : AppColors.categoryInactive,
          borderRadius: AppRadius.circular(AppRadius.navigationPill),
          boxShadow: isActive ? AppShadows.card : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              category.icon,
              size: AppSpacing.iconSize,
              color: isActive
                  ? Colors.white
                  : (category.iconColor ?? AppColors.accentPrimary),
            ),
            const SizedBox(width: AppSpacing.sm),
            Text(
              category.name,
              style: AppTypography.bodySmall.copyWith(
                color: isActive ? Colors.white : AppColors.textPrimary,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Liste horizontale de catégories (pills)
class HorizontalCategoryList extends StatelessWidget {
  final List<CategoryModel> categories;
  final String? activeCategory;
  final Function(CategoryModel)? onCategorySelected;
  final EdgeInsets? padding;

  const HorizontalCategoryList({
    super.key,
    required this.categories,
    this.activeCategory,
    this.onCategorySelected,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 50,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: padding ?? AppSpacing.screenPaddingHorizontal,
        itemCount: categories.length,
        separatorBuilder: (context, index) =>
            const SizedBox(width: AppSpacing.md),
        itemBuilder: (context, index) {
          final category = categories[index];
          final isActive = activeCategory == category.id;

          return HorizontalCategoryCard(
            category: category,
            isActive: isActive,
            onTap: () => onCategorySelected?.call(category),
          );
        },
      ),
    );
  }
}

/// Carte de catégorie avec compteur
class CategoryCardWithCount extends StatelessWidget {
  final CategoryModel category;
  final int count;
  final bool isActive;
  final VoidCallback? onTap;

  const CategoryCardWithCount({
    super.key,
    required this.category,
    required this.count,
    this.isActive = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeInOut,
        padding: AppSpacing.cardPaddingAll,
        decoration: BoxDecoration(
          color: isActive
              ? AppColors.categoryActive
              : AppColors.categoryInactive,
          borderRadius: AppRadius.categoryRadius,
          boxShadow: isActive ? AppShadows.card : null,
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Icône
            Container(
              width: AppSpacing.categoryIconSize,
              height: AppSpacing.categoryIconSize,
              decoration: BoxDecoration(
                color: isActive
                    ? Colors.white.withOpacity(0.2)
                    : AppColors.overlayLight,
                borderRadius: AppRadius.circular(
                  AppSpacing.categoryIconSize / 2,
                ),
              ),
              child: Icon(
                category.icon,
                size: AppSpacing.iconSizeLarge,
                color: isActive
                    ? Colors.white
                    : (category.iconColor ?? AppColors.accentPrimary),
              ),
            ),

            const SizedBox(height: AppSpacing.sm),

            // Nom de la catégorie
            Text(
              category.name,
              style: AppTypography.bodySmall.copyWith(
                color: isActive ? Colors.white : AppColors.textPrimary,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
              ),
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),

            const SizedBox(height: AppSpacing.xs),

            // Compteur
            Container(
              padding: AppSpacing.symmetric(
                horizontal: AppSpacing.sm,
                vertical: AppSpacing.xs,
              ),
              decoration: BoxDecoration(
                color: isActive
                    ? Colors.white.withOpacity(0.2)
                    : AppColors.accentPrimary.withOpacity(0.1),
                borderRadius: AppRadius.badgeRadius,
              ),
              child: Text(
                count.toString(),
                style: AppTypography.caption.copyWith(
                  color: isActive ? Colors.white : AppColors.accentPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
