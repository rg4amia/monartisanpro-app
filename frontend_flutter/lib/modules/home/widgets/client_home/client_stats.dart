import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';
import 'client_stat_card.dart';

/// Rangée de deux `ClientStatCard` : artisans disponibles à proximité et
/// missions actives suivies en temps réel.
class ClientStats extends StatelessWidget {
  const ClientStats({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      return Row(
        children: [
          Expanded(
            child: ClientStatCard(
              title: 'Artisans disponibles',
              value: '${controller.displayedNearbyArtisansCount}',
              subtitle: 'Autour de votre position',
              color: AppColors.client,
              background: AppColors.clientSoft,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: ClientStatCard(
              title: 'Missions actives',
              value: '${controller.activeMissionsCount.value}',
              subtitle: 'Suivies en temps réel',
              color: AppColors.accent,
              background: AppColors.artisanSoft,
            ),
          ),
        ],
      );
    });
  }
}
