import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/score_nzassa.dart';
import '../controllers/artisan_controller.dart';

class ArtisanProfileScreen extends StatelessWidget {
  const ArtisanProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<ArtisanController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Obx(() {
        if (c.isLoading.value || c.artisan.value == null) {
          return const Center(child: CircularProgressIndicator());
        }
        final a = c.artisan.value!;
        return CustomScrollView(
          slivers: [
            // Photo header
            SliverAppBar(
              expandedHeight: 220,
              pinned: true,
              backgroundColor: AppColors.primary,
              flexibleSpace: FlexibleSpaceBar(
                background: a.photo != null
                    ? CachedNetworkImage(
                        imageUrl: a.photo!,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => Container(
                            color: AppColors.primary.withValues(alpha: 0.3)),
                        errorWidget: (_, __, ___) =>
                            Container(color: AppColors.primary),
                      )
                    : Container(
                        color: AppColors.accent.withValues(alpha: 0.15),
                        child: Center(
                          child: Text(
                            Formatters.initial(a.name ?? 'A'),
                            style: const TextStyle(
                                fontSize: 72,
                                fontWeight: FontWeight.w800,
                                color: AppColors.accent),
                          ),
                        ),
                      ),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Name + score
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Text(a.name ?? 'Artisan',
                                      style: const TextStyle(
                                          fontSize: 22,
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.textPrimary)),
                                  if (a.isGoldenMarker) ...[
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFFF3CD),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: const Row(
                                        children: [
                                          Icon(Icons.star,
                                              size: 12,
                                              color: Color(0xFFD4A017)),
                                          SizedBox(width: 3),
                                          Text('Artisan d\'élite',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: Color(0xFFD4A017),
                                                  fontWeight: FontWeight.w600)),
                                        ],
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              if (a.trade != null)
                                Text(a.trade!,
                                    style: const TextStyle(
                                        fontSize: 15,
                                        color: AppColors.textSecondary)),
                              if (a.nightInterventionAvailable) ...[
                                const SizedBox(height: 10),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 6,
                                  ),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary.withValues(alpha: 0.08),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        Icons.nightlight_round,
                                        size: 14,
                                        color: AppColors.primary,
                                      ),
                                      SizedBox(width: 6),
                                      Text(
                                        'Intervention de nuit',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.primary,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              if (a.distance != null)
                                Row(
                                  children: [
                                    const Icon(Icons.location_on_outlined,
                                        size: 14, color: AppColors.textMuted),
                                    Text(a.distance!,
                                        style: const TextStyle(
                                            fontSize: 13,
                                            color: AppColors.textMuted)),
                                  ],
                                ),
                            ],
                          ),
                        ),
                        ScoreNzassa(
                            score: a.scoreNzassa,
                            size: ScoreSize.large,
                            showLabel: true),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Actions
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () => launchUrl(
                              Uri.parse('tel:${a.phone}'),
                            ),
                            icon: const Icon(Icons.call_outlined),
                            label: const Text('Appeler'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () => Get.toNamed(
                              Routes.missionRequest,
                              arguments: {'artisan': a},
                            ),
                            icon: const Icon(Icons.send_outlined,
                                color: Colors.white),
                            label: const Text('Demander devis'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Score breakdown
                    _buildScoreBreakdown(c),
                  ],
                ),
              ),
            ),
          ],
        );
      }),
    );
  }

  Widget _buildScoreBreakdown(ArtisanController c) {
    final scoreData = c.score.value;
    if (scoreData == null) return const SizedBox.shrink();
    final rootData =
        (scoreData['data'] as Map<String, dynamic>?) ?? scoreData;
    final breakdown = rootData['breakdown'] is Map
        ? Map<String, dynamic>.from(
            rootData['breakdown'] as Map,
          )
        : const <String, dynamic>{};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Score N\'Zassa',
            style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary)),
        const SizedBox(height: 12),
        _ScoreDimension(
            label: 'Fiabilité',
            pct: 40,
            value: _weightedBreakdownValue(
              breakdown['fiabilite'],
              40,
            ),
            color: AppColors.primary),
        _ScoreDimension(
            label: 'Intégrité',
            pct: 30,
            value: _weightedBreakdownValue(
              breakdown['integrite'],
              30,
            ),
            color: AppColors.accent),
        _ScoreDimension(
            label: 'Qualité',
            pct: 20,
            value: _weightedBreakdownValue(
              breakdown['qualite'],
              20,
            ),
            color: AppColors.success),
        _ScoreDimension(
            label: 'Réactivité',
            pct: 10,
            value: _weightedBreakdownValue(
              breakdown['reactivite'],
              10,
            ),
            color: AppColors.warning),
      ],
    );
  }

  int _weightedBreakdownValue(dynamic value, int maxPoints) {
    double parsed;

    if (value is num) {
      parsed = value.toDouble();
    } else {
      parsed = double.tryParse(value?.toString() ?? '') ?? 0;
    }

    return (parsed / 5 * maxPoints).round().clamp(0, maxPoints);
  }
}

class _ScoreDimension extends StatelessWidget {
  final String label;
  final int pct;
  final int value;
  final Color color;

  const _ScoreDimension({
    required this.label,
    required this.pct,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label,
                  style: const TextStyle(
                      fontSize: 13, color: AppColors.textSecondary)),
              Text('$value / $pct pts',
                  style: TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w600, color: color)),
            ],
          ),
          const SizedBox(height: 4),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: pct > 0 ? value / pct : 0,
              backgroundColor: color.withValues(alpha: 0.12),
              valueColor: AlwaysStoppedAnimation(color),
              minHeight: 6,
            ),
          ),
        ],
      ),
    );
  }
}
