import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/communication_banner.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/home_controller.dart';
import '../widgets/artisan_home/hero_header.dart';
import '../widgets/artisan_home/mission_queue.dart';
import '../widgets/artisan_home/prosartisan_score_card.dart';
import '../widgets/artisan_home/quick_actions.dart';
import '../widgets/artisan_home/stat_grid.dart';
import '../widgets/artisan_home/workflow_reminder.dart';

class ArtisanHomeScreen extends StatelessWidget {
  const ArtisanHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        onRefresh: controller.refresh,
        color: AppColors.primary,
        child: Obx(() {
          // ── État d'erreur : afficher un widget de retry ──
          if (controller.hasError.value &&
              controller.artisanMissions.isEmpty &&
              !controller.isLoading.value) {
            return _ErrorRetryView(onRetry: controller.refresh);
          }

          if (controller.isLoading.value &&
              controller.artisanMissions.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [LoadingShimmer.list(count: 4)],
            );
          }

          return CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: HeroHeader(controller: controller)),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      CommunicationBanner(
                        announcements: controller.announcements,
                      ),
                      LeSaviezVousCarousel(tips: controller.tips),
                      ProsArtisanScoreCard(controller: controller),
                      StatGrid(controller: controller),
                      const SizedBox(height: 24),
                      QuickActions(
                        controller: controller,
                        missions: controller.prioritizedArtisanMissions,
                      ),
                      const SizedBox(height: 24),
                      WorkflowReminder(controller: controller),
                      const SizedBox(height: 24),
                      MissionQueue(
                        controller: controller,
                        missions: controller.prioritizedArtisanMissions,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        }),
      ),
    );
  }
}

/// Vue affichée quand le chargement initial échoue et qu'aucune donnée n'est en
/// cache — invite à vérifier la connexion et à tirer pour réessayer.
class _ErrorRetryView extends StatelessWidget {
  const _ErrorRetryView({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(20),
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.2),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.wifi_off_rounded,
                  size: 48,
                  color: AppColors.danger,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Impossible de charger les données',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'Vérifiez votre connexion internet\net tirez vers le bas pour réessayer.',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Réessayer'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 28,
                    vertical: 14,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 2,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
