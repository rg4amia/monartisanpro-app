import 'package:flutter/material.dart';
import '../../domain/models/score_snapshot.dart';

class ScoreHistoryChart extends StatelessWidget {
  final List<ScoreSnapshot> scoreHistory;

  const ScoreHistoryChart({Key? key, required this.scoreHistory})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (scoreHistory.isEmpty) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Center(
            child: Text(
              'Aucun historique de score disponible',
              style: TextStyle(color: Colors.grey, fontSize: 16),
            ),
          ),
        ),
      );
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Historique des Scores',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            SizedBox(height: 200, child: _buildSimpleChart()),
            const SizedBox(height: 16),
            _buildScoreList(),
          ],
        ),
      ),
    );
  }

  Widget _buildSimpleChart() {
    // Simple visual representation of score progression
    final sortedHistory = List<ScoreSnapshot>.from(scoreHistory)
      ..sort((a, b) => a.recordedAt.compareTo(b.recordedAt));

    if (sortedHistory.length < 2) {
      return const Center(
        child: Text(
          'Pas assez de données pour afficher un graphique',
          style: TextStyle(color: Colors.grey),
        ),
      );
    }

    return CustomPaint(
      size: const Size(double.infinity, 200),
      painter: ScoreChartPainter(sortedHistory),
    );
  }

  Widget _buildScoreList() {
    final sortedHistory = List<ScoreSnapshot>.from(scoreHistory)
      ..sort((a, b) => b.recordedAt.compareTo(a.recordedAt));

    return Column(
      children: sortedHistory.take(5).map((snapshot) {
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 24,
                decoration: BoxDecoration(
                  color: _getScoreColor(snapshot.score),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Center(
                  child: Text(
                    '${snapshot.score}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      snapshot.reason,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    Text(
                      _formatDate(snapshot.recordedAt),
                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Color _getScoreColor(int score) {
    if (score >= 80) return Colors.green;
    if (score >= 60) return Colors.orange;
    if (score >= 40) return Colors.deepOrange;
    return Colors.red;
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}

class ScoreChartPainter extends CustomPainter {
  final List<ScoreSnapshot> scoreHistory;

  ScoreChartPainter(this.scoreHistory);

  @override
  void paint(Canvas canvas, Size size) {
    if (scoreHistory.length < 2) return;

    final paint = Paint()
      ..color = Colors.blue
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke;

    final pointPaint = Paint()
      ..color = Colors.blue
      ..style = PaintingStyle.fill;

    final path = Path();
    final points = <Offset>[];

    // Calculate points
    for (int i = 0; i < scoreHistory.length; i++) {
      final x = (i / (scoreHistory.length - 1)) * size.width;
      final y = size.height - (scoreHistory[i].score / 100) * size.height;
      points.add(Offset(x, y));

      if (i == 0) {
        path.moveTo(x, y);
      } else {
        path.lineTo(x, y);
      }
    }

    // Draw line
    canvas.drawPath(path, paint);

    // Draw points
    for (final point in points) {
      canvas.drawCircle(point, 4, pointPaint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}
