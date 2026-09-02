import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';

const _kCategories = [
  (
    label: 'Plomberie',
    subtitle: 'Fuites, sanitaires & tuyaux',
    badge: 'Urgence 24/7',
    icon: Icons.water_drop_rounded,
    color: AppColors.tradePlumbing,
    gradient: AppColors.gradientPlumbing,
  ),
  (
    label: 'Électricité',
    subtitle: 'Tableaux, pannes & câblage',
    badge: 'Certifié',
    icon: Icons.bolt_rounded,
    color: AppColors.tradeElectricity,
    gradient: AppColors.gradientElectricity,
  ),
  (
    label: 'Climatisation',
    subtitle: 'Split, recharge gaz & froid',
    badge: 'Spécialiste',
    icon: Icons.ac_unit_rounded,
    color: AppColors.tradeHVAC,
    gradient: AppColors.gradientHVAC,
  ),
  (
    label: 'Maçonnerie',
    subtitle: 'Gros œuvre, dalles & briques',
    badge: 'BTP',
    icon: Icons.foundation_rounded,
    color: AppColors.tradeMasonry,
    gradient: AppColors.gradientMasonry,
  ),
  (
    label: 'Peinture',
    subtitle: 'Enduit, façades & finitions',
    badge: 'Déco Pro',
    icon: Icons.format_paint_rounded,
    color: AppColors.tradePainting,
    gradient: AppColors.gradientPainting,
  ),
  (
    label: 'Menuiserie',
    subtitle: 'Portes, placards & bois',
    badge: 'Sur-mesure',
    icon: Icons.carpenter_rounded,
    color: AppColors.tradeCarpentry,
    gradient: AppColors.gradientCarpentry,
  ),
  (
    label: 'Serrurerie & Soudure',
    subtitle: 'Grilles, verrous & métal',
    badge: 'Sécurité',
    icon: Icons.lock_reset_rounded,
    color: AppColors.tradeWelding,
    gradient: AppColors.gradientWelding,
  ),
  (
    label: 'Électroménager',
    subtitle: 'Frigo, lave-linge & four',
    badge: 'Dépannage',
    icon: Icons.kitchen_rounded,
    color: AppColors.tradeAppliance,
    gradient: AppColors.gradientWelding,
  ),
];

/// Grille 2 colonnes des catégories de métiers populaires ; chaque carte ouvre
/// la création de demande pré-remplie avec la catégorie choisie.
class CategoriesGrid extends StatelessWidget {
  const CategoriesGrid({super.key});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 14,
        mainAxisSpacing: 14,
        childAspectRatio: 1.14,
      ),
      itemCount: _kCategories.length,
      itemBuilder: (_, index) {
        final category = _kCategories[index];
        return Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () => Get.toNamed(
              Routes.missionRequest,
              arguments: {'category': category.label},
            ),
            borderRadius: BorderRadius.circular(22),
            child: Ink(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(
                  color: category.color.withValues(alpha: 0.18),
                  width: 1.2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: category.color.withValues(alpha: 0.06),
                    blurRadius: 14,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          gradient: category.gradient,
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [
                            BoxShadow(
                              color: category.color.withValues(alpha: 0.3),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child:
                            Icon(category.icon, color: Colors.white, size: 24),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: category.color.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          category.badge,
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: category.color,
                          ),
                        ),
                      ),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        category.label,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                          letterSpacing: -0.2,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        category.subtitle,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: AppColors.textSecondary.withValues(alpha: 0.9),
                          height: 1.2,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
