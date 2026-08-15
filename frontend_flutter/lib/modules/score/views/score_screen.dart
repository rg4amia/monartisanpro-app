import 'package:flutter/material.dart';
import 'package:frontend_flutter/modules/score/views/micro_credit_screen.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/score_prosartisan.dart';
import '../controllers/score_controller.dart';

class ScoreScreen extends GetView<ScoreController> {
  const ScoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Score ProsArtisan')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }
        return SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              // Score global
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      ScoreProsArtisan(
                        score: controller.score.value,
                        size: ScoreSize.large,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        _scoreLabel(controller.score.value),
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppColors.scoreColor(controller.score.value),
                        ),
                      ),
                      if (controller.hasAccesMicrocredit.value) ...[
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 8),
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: AppColors.success),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.star, color: AppColors.success, size: 16),
                              SizedBox(width: 6),
                              Text(
                                'Accès micro-crédit débloqué',
                                style: TextStyle(
                                  color: AppColors.success,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Radar chart / barres détaillées
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Détail des critères',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 20),
                      _CritereBar(
                        label: 'Fiabilité',
                        weight: '40%',
                        value: controller.fiabilite.value / 100,
                        color: AppColors.primary,
                      ),
                      _CritereBar(
                        label: 'Intégrité',
                        weight: '30%',
                        value: controller.integrite.value / 100,
                        color: AppColors.accent,
                      ),
                      _CritereBar(
                        label: 'Qualité',
                        weight: '20%',
                        value: controller.qualite.value / 100,
                        color: AppColors.success,
                      ),
                      _CritereBar(
                        label: 'Réactivité',
                        weight: '10%',
                        value: controller.reactivite.value / 100,
                        color: AppColors.info,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Évolution (graphique fictif de tendance)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Évolution',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      SizedBox(
                        height: 120,
                        child: LineChart(
                          LineChartData(
                            gridData: const FlGridData(show: false),
                            titlesData: const FlTitlesData(show: false),
                            borderData: FlBorderData(show: false),
                            lineBarsData: [
                              LineChartBarData(
                                spots: [
                                  const FlSpot(0, 40),
                                  const FlSpot(1, 52),
                                  const FlSpot(2, 48),
                                  const FlSpot(3, 61),
                                  FlSpot(4, controller.score.value.toDouble()),
                                ],
                                isCurved: true,
                                color: AppColors.primary,
                                barWidth: 3,
                                dotData: const FlDotData(show: false),
                                belowBarData: BarAreaData(
                                  show: true,
                                  color: AppColors.primary.withValues(alpha: 0.1),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Info micro-crédit
              Card(
                child: ListTile(
                  leading: const Icon(Icons.account_balance,
                      color: AppColors.primary),
                  title: const Text('Micro-crédit d\'urgence'),
                  subtitle: Text(
                    controller.hasAccesMicrocredit.value
                        ? 'Déblocage en moins de 2h'
                        : 'Score > 70 requis (actuel : ${controller.score.value})',
                  ),
                  trailing: controller.hasAccesMicrocredit.value
                      ? ElevatedButton(
                          onPressed: () => Get.to(() => const MicroCreditScreen(),
                              binding: BindingsBuilder.put(() => MicroCreditController())),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.success,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                          ),
                          child: const Text('Demander'),
                        )
                      : const Icon(Icons.lock, color: AppColors.textMuted),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  String _scoreLabel(int s) {
    if (s >= 70) return 'Excellent — Marqueur Doré';
    if (s >= 50) return 'Bon profil';
    if (s >= 30) return 'En progression';
    return 'Débutant';
  }
}

class _CritereBar extends StatelessWidget {
  final String label;
  final String weight;
  final double value; // 0.0 à 1.0
  final Color color;
  const _CritereBar({
    required this.label,
    required this.weight,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label,
                  style: const TextStyle(
                      fontWeight: FontWeight.w500, fontSize: 13)),
              Row(
                children: [
                  Text(
                    '${(value * 100).round()}/100',
                    style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.bold,
                        fontSize: 13),
                  ),
                  const SizedBox(width: 8),
                  Text(weight,
                      style: const TextStyle(
                          color: AppColors.textMuted, fontSize: 11)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: value,
              backgroundColor: color.withValues(alpha: 0.15),
              valueColor: AlwaysStoppedAnimation<Color>(color),
              minHeight: 8,
            ),
          ),
        ],
      ),
    );
  }
}
