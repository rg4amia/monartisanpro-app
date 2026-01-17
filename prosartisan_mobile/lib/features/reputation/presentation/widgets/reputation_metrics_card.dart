import 'package:flutter/material.dart';
import '../../domain/models/reputation_profile.dart';

class ReputationMetricsCard extends StatelessWidget {
  final ReputationMetrics metrics;

  const ReputationMetricsCard({Key? key, required this.metrics})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Détail des Métriques',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            _buildMetricRow(
              'Fiabilité',
              metrics.reliabilityScore,
              40,
              Icons.verified,
              Colors.blue,
            ),
            const SizedBox(height: 12),
            _buildMetricRow(
              'Intégrité',
              metrics.integrityScore,
              30,
              Icons.security,
              Colors.green,
            ),
            const SizedBox(height: 12),
            _buildMetricRow(
              'Qualité',
              metrics.qualityScore,
              20,
              Icons.star,
              Colors.orange,
            ),
            const SizedBox(height: 12),
            _buildMetricRow(
              'Réactivité',
              metrics.reactivityScore,
              10,
              Icons.speed,
              Colors.purple,
            ),
            const Divider(height: 24),
            _buildStatsRow(),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricRow(
    String label,
    double score,
    int weight,
    IconData icon,
    Color color,
  ) {
    return Row(
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 8),
        Expanded(
          flex: 2,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontWeight: FontWeight.w500)),
              Text(
                '$weight% du score',
                style: const TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ),
        Expanded(
          flex: 3,
          child: Column(
            children: [
              LinearProgressIndicator(
                value: score / 100,
                backgroundColor: Colors.grey[300],
                valueColor: AlwaysStoppedAnimation<Color>(color),
              ),
              const SizedBox(height: 4),
              Text(
                '${score.toStringAsFixed(1)}/100',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStatsRow() {
    return Row(
      children: [
        Expanded(
          child: _buildStatItem(
            'Projets terminés',
            metrics.completedProjects.toString(),
            Icons.check_circle,
            Colors.green,
          ),
        ),
        Expanded(
          child: _buildStatItem(
            'Note moyenne',
            '${metrics.averageRating.toStringAsFixed(1)}/5',
            Icons.star,
            Colors.orange,
          ),
        ),
        Expanded(
          child: _buildStatItem(
            'Temps de réponse',
            '${metrics.averageResponseTimeHours.toStringAsFixed(1)}h',
            Icons.access_time,
            Colors.blue,
          ),
        ),
      ],
    );
  }

  Widget _buildStatItem(
    String label,
    String value,
    IconData icon,
    Color color,
  ) {
    return Column(
      children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        Text(
          label,
          style: const TextStyle(fontSize: 12, color: Colors.grey),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }
}
