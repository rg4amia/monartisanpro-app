import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/score_controller.dart';
import '../../../../shared/controllers/auth_controller.dart';

class ScoreDashboardScreen extends StatefulWidget {
  final int? artisanId; // If null, use current user

  const ScoreDashboardScreen({super.key, this.artisanId});

  @override
  State<ScoreDashboardScreen> createState() => _ScoreDashboardScreenState();
}

class _ScoreDashboardScreenState extends State<ScoreDashboardScreen> {
  final _scoreController = Get.put(ScoreController());
  final _authController = Get.find<AuthController>();

  int? _artisanId;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _artisanId = widget.artisanId ?? _authController.currentUser.value?.id;
    if (_artisanId != null) {
      _loadScoreData();
    }
  }

  Future<void> _loadScoreData() async {
    setState(() => _isLoading = true);
    await _scoreController.refreshScoreData(_artisanId!);
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(() {
        final score = _scoreController.artisanScore.value;

        if (_isLoading && score == null) {
          return const Center(child: CircularProgressIndicator());
        }

        if (score == null) {
          return const Center(child: Text('Score non disponible'));
        }

        return RefreshIndicator(
          onRefresh: _loadScoreData,
          child: CustomScrollView(
            slivers: [
              // App Bar
              SliverAppBar(
                expandedHeight: 280,
                pinned: true,
                flexibleSpace: FlexibleSpaceBar(
                  title: const Text(
                    'Score N\'Zassa',
                    style: TextStyle(
                      shadows: [
                        Shadow(
                          color: Colors.black45,
                          blurRadius: 10,
                        ),
                      ],
                    ),
                  ),
                  background: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: _getScoreGradient(score.totalScore),
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const SizedBox(height: 50),
                          Text(
                            score.formattedScore,
                            style: Theme.of(context).textTheme.displayLarge?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 72,
                                ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          Text(
                            score.scoreLabel,
                            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.9),
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                          const SizedBox(height: Spacing.md),
                          _buildBadge(context, score.badgeLevel),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

              // Content
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.screenPadding),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Stats Row
                      Row(
                        children: [
                          Expanded(
                            child: _buildStatCard(
                              context,
                              '${score.projectsCompleted}',
                              'Projets réussis',
                              Icons.check_circle_outline,
                              AppColors.success,
                            ),
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: _buildStatCard(
                              context,
                              '${score.averageRating.toStringAsFixed(1)} ⭐',
                              'Note moyenne',
                              Icons.star_outline,
                              AppColors.warning,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.md),
                      Row(
                        children: [
                          Expanded(
                            child: _buildStatCard(
                              context,
                              '${score.completionRate.toStringAsFixed(0)}%',
                              'Taux de réussite',
                              Icons.trending_up,
                              AppColors.info,
                            ),
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: _buildStatCard(
                              context,
                              '${score.totalReviews}',
                              'Avis clients',
                              Icons.rate_review_outlined,
                              AppColors.lightAccentPrimary,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.xl),

                      // Score Breakdown
                      Text(
                        'Détails du score',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      const SizedBox(height: Spacing.md),
                      ...score.scoreBreakdown.entries.map((entry) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: Spacing.md),
                          child: _buildScoreBar(
                            context,
                            entry.key,
                            entry.value,
                            _getScoreColor(entry.value),
                          ),
                        );
                      }),
                      const SizedBox(height: Spacing.xl),

                      // Score Chart
                      if (_scoreController.scoreHistory.isNotEmpty) ...[
                        Text(
                          'Évolution du score',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        const SizedBox(height: Spacing.md),
                        Container(
                          height: 200,
                          padding: const EdgeInsets.all(Spacing.base),
                          decoration: BoxDecoration(
                            color: AppColors.lightBackground,
                            borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          ),
                          child: LineChart(
                            _buildScoreChart(),
                          ),
                        ),
                        const SizedBox(height: Spacing.xl),
                      ],

                      // Improvement Tips
                      if (!score.hasGoodReputation || score.isNewArtisan) ...[
                        Container(
                          padding: const EdgeInsets.all(Spacing.lg),
                          decoration: BoxDecoration(
                            color: AppColors.info.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(Spacing.radiusMd),
                            border: Border.all(
                              color: AppColors.info.withValues(alpha: 0.3),
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Icon(Icons.lightbulb_outline, color: AppColors.info),
                                  const SizedBox(width: Spacing.md),
                                  Text(
                                    'Conseils d\'amélioration',
                                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                          fontWeight: FontWeight.bold,
                                          color: AppColors.info,
                                        ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: Spacing.md),
                              ..._getImprovementTips(score).map((tip) {
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: Spacing.sm),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Icon(Icons.arrow_right, size: 20),
                                      const SizedBox(width: Spacing.sm),
                                      Expanded(
                                        child: Text(
                                          tip,
                                          style: Theme.of(context).textTheme.bodyMedium,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                            ],
                          ),
                        ),
                        const SizedBox(height: Spacing.xl),
                      ],

                      // Recent History
                      if (_scoreController.scoreHistory.isNotEmpty) ...[
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Historique récent',
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            TextButton(
                              onPressed: () {
                                // Navigate to full history
                              },
                              child: const Text('Voir tout'),
                            ),
                          ],
                        ),
                        const SizedBox(height: Spacing.md),
                        ..._scoreController.scoreHistory.take(5).map((history) {
                          return _buildHistoryCard(context, history);
                        }),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildStatCard(
    BuildContext context,
    String value,
    String label,
    IconData icon,
    Color color,
  ) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(height: Spacing.sm),
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: color,
                ),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildScoreBar(
    BuildContext context,
    String label,
    double value,
    Color color,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
            ),
            Text(
              '${value.toStringAsFixed(0)}/100',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
            ),
          ],
        ),
        const SizedBox(height: Spacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(Spacing.radiusSm),
          child: LinearProgressIndicator(
            value: value / 100,
            backgroundColor: Colors.grey[300],
            valueColor: AlwaysStoppedAnimation<Color>(color),
            minHeight: 8,
          ),
        ),
      ],
    );
  }

  Widget _buildBadge(BuildContext context, String level) {
    IconData icon;
    Color color;
    String label;

    switch (level) {
      case 'gold':
        icon = Icons.workspace_premium;
        color = const Color(0xFFFFD700);
        label = 'Badge Or';
        break;
      case 'silver':
        icon = Icons.workspace_premium;
        color = const Color(0xFFC0C0C0);
        label = 'Badge Argent';
        break;
      case 'bronze':
        icon = Icons.workspace_premium;
        color = const Color(0xFFCD7F32);
        label = 'Badge Bronze';
        break;
      default:
        icon = Icons.badge_outlined;
        color = Colors.grey;
        label = 'Sans badge';
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.md,
        vertical: Spacing.sm,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: Spacing.sm),
          Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryCard(BuildContext context, history) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: history.isIncrease
              ? AppColors.success.withValues(alpha: 0.1)
              : AppColors.error.withValues(alpha: 0.1),
          child: Icon(
            history.isIncrease ? Icons.trending_up : Icons.trending_down,
            color: history.isIncrease ? AppColors.success : AppColors.error,
          ),
        ),
        title: Text(history.changeReason),
        subtitle: Text(_formatDate(history.createdAt)),
        trailing: Text(
          history.formattedChange,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
                color: history.isIncrease ? AppColors.success : AppColors.error,
              ),
        ),
      ),
    );
  }

  LineChartData _buildScoreChart() {
    final history = _scoreController.scoreHistory.reversed.toList();

    return LineChartData(
      gridData: FlGridData(show: false),
      titlesData: FlTitlesData(show: false),
      borderData: FlBorderData(show: false),
      lineBarsData: [
        LineChartBarData(
          spots: history.asMap().entries.map((entry) {
            return FlSpot(entry.key.toDouble(), entry.value.scoreAfter);
          }).toList(),
          isCurved: true,
          color: AppColors.lightAccentPrimary,
          barWidth: 3,
          dotData: FlDotData(show: true),
          belowBarData: BarAreaData(
            show: true,
            color: AppColors.lightAccentPrimary.withValues(alpha: 0.1),
          ),
        ),
      ],
      minY: 0,
      maxY: 100,
    );
  }

  List<Color> _getScoreGradient(double score) {
    if (score >= 90) return [const Color(0xFF4CAF50), const Color(0xFF81C784)];
    if (score >= 75) return [const Color(0xFF2196F3), const Color(0xFF64B5F6)];
    if (score >= 60) return [const Color(0xFFFF9800), const Color(0xFFFFB74D)];
    return [const Color(0xFFF44336), const Color(0xFFE57373)];
  }

  Color _getScoreColor(double score) {
    if (score >= 90) return AppColors.success;
    if (score >= 75) return AppColors.info;
    if (score >= 60) return AppColors.warning;
    return AppColors.error;
  }

  List<String> _getImprovementTips(score) {
    final tips = <String>[];

    if (score.reliabilityScore < 75) {
      tips.add('Complétez vos projets à temps pour améliorer votre fiabilité');
    }
    if (score.integrityScore < 75) {
      tips.add('Utilisez les jetons matériaux correctement pour renforcer votre intégrité');
    }
    if (score.qualityScore < 75) {
      tips.add('Améliorez la qualité de votre travail pour obtenir de meilleures notes');
    }
    if (score.responsivenessScore < 75) {
      tips.add('Répondez rapidement aux demandes de devis et messages');
    }
    if (score.isNewArtisan) {
      tips.add('Complétez plus de projets pour établir votre réputation');
    }

    return tips.isEmpty
        ? ['Excellent travail! Maintenez ce niveau de performance']
        : tips;
  }

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);

    if (diff.inDays == 0) return 'Aujourd\'hui';
    if (diff.inDays == 1) return 'Hier';
    if (diff.inDays < 7) return 'Il y a ${diff.inDays} jours';

    return '${date.day}/${date.month}/${date.year}';
  }
}
