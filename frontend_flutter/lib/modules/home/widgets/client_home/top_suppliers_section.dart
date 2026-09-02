import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Liste des fournisseurs les mieux notés (données de `HomeController`), chaque
/// carte menant au réseau de quincailleries agréées.
class TopSuppliersSection extends StatelessWidget {
  const TopSuppliersSection({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: controller.topSuppliers.map((supplier) {
        return GestureDetector(
          onTap: () => Get.toNamed(Routes.clientSuppliers),
          child: Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.store_mall_directory_rounded,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        supplier['name'] as String,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 14.5,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            color: Colors.amber,
                            size: 16,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            '${supplier['rating']}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            '•  ${supplier['deliveries']} commandes livrées',
                            style: const TextStyle(
                              color: AppColors.textMuted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }
}
