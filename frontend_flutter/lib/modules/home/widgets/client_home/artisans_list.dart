import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../shared/widgets/artisan_card.dart';
import '../../../../shared/widgets/loading_shimmer.dart';
import '../../controllers/home_controller.dart';
import 'feature_icon.dart';

/// Liste des artisans à proximité (ou éloignés) — shimmer au chargement, état
/// vide contextualisé (mode nuit / aucune position), sinon une pile d'`ArtisanCard`.
class ArtisansList extends StatelessWidget {
  const ArtisansList({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      if (controller.isLoading.value) {
        return LoadingShimmer.list(count: 3);
      }

      final list = controller.displayedArtisans;

      if (list.isEmpty) {
        return Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            children: [
              FeatureIcon(
                icon: controller.isNightModeActive
                    ? Icons.dark_mode_outlined
                    : Icons.search_off_rounded,
                color: AppColors.client,
                background: AppColors.clientSoft,
              ),
              const SizedBox(height: 14),
              Text(
                controller.isNightModeActive
                    ? 'Aucun artisan disponible cette nuit'
                    : 'Aucun artisan trouvé pour le moment',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              Text(
                controller.isNightModeActive
                    ? 'Les interventions de nuit sont réservées aux urgences. Repassez en journée pour plus de choix.'
                    : 'Activez votre position ou relancez la recherche pour voir les profils vérifiés près de vous.',
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.45,
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        );
      }

      return Column(
        children: list
            .map(
              (artisan) => ArtisanCard(
                artisan: artisan,
                onTap: () => Get.toNamed(
                  Routes.artisanProfile,
                  arguments: artisan,
                ),
              ),
            )
            .toList(),
      );
    });
  }
}
