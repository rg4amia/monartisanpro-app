import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Top 3 des artisans à proximité triés par note ; état d'attente si la
/// recherche n'a encore rien retourné.
class TopArtisansSection extends StatelessWidget {
  const TopArtisansSection({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    final sortedArtisans = controller.artisans.toList()
      ..sort((a, b) => b.rating.compareTo(a.rating));
    final topList = sortedArtisans.take(3).toList();

    if (topList.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.border),
        ),
        child: const Center(
          child: Text(
            'Recherche d\'artisans qualifiés en cours...',
            style: TextStyle(color: AppColors.textSecondary),
          ),
        ),
      );
    }

    return Column(
      children: topList.map((artisan) {
        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: ListTile(
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            leading: CircleAvatar(
              radius: 26,
              backgroundColor: AppColors.clientSoft,
              child: Text(
                artisan.name?.substring(0, 1) ?? 'A',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.client,
                  fontSize: 18,
                ),
              ),
            ),
            title: Text(
              artisan.name ?? 'Artisan qualifié',
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 15,
                color: AppColors.textPrimary,
              ),
            ),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 2),
                Text(
                  artisan.trade ?? 'Artisan',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
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
                      artisan.rating.toStringAsFixed(1),
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '•  ${artisan.completedMissions} missions',
                      style: const TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            trailing: const Icon(
              Icons.arrow_forward_ios_rounded,
              size: 14,
              color: AppColors.textMuted,
            ),
            onTap: () => Get.toNamed(Routes.artisanProfile, arguments: artisan),
          ),
        );
      }).toList(),
    );
  }
}
