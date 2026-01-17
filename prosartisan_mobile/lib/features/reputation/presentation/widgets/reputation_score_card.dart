import 'package:flutter/material.dart';
import '../../domain/models/reputation_profile.dart';

class ReputationScoreCard extends StatelessWidget {
  final ReputationProfile reputation;

  const ReputationScoreCard({Key? key, required this.reputation})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 4,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            const Text(
              'Score N\'Zassa',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Colors.grey,
              ),
            ),
            const SizedBox(height: 16),
            Stack(
              alignment: Alignment.center,
              children: [
                SizedBox(
                  width: 120,
                  height: 120,
                  child: CircularProgressIndicator(
                    value: reputation.currentScore / 100,
                    strokeWidth: 8,
                    backgroundColor: Colors.grey[300],
                    valueColor: AlwaysStoppedAnimation<Color>(
                      _getScoreColor(reputation.currentScore),
                    ),
                  ),
                ),
                Column(
                  children: [
                    Text(
                      '${reputation.currentScore}',
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.bold,
                        color: _getScoreColor(reputation.currentScore),
                      ),
                    ),
                    const Text(
                      '/100',
                      style: TextStyle(fontSize: 16, color: Colors.grey),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              _getScoreLabel(reputation.currentScore),
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w500,
                color: _getScoreColor(reputation.currentScore),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Dernière mise à jour: ${_formatDate(reputation.lastCalculatedAt)}',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ],
        ),
      ),
    );
  }

  Color _getScoreColor(int score) {
    if (score >= 80) return Colors.green;
    if (score >= 60) return Colors.orange;
    if (score >= 40) return Colors.deepOrange;
    return Colors.red;
  }

  String _getScoreLabel(int score) {
    if (score >= 80) return 'Excellent';
    if (score >= 60) return 'Bon';
    if (score >= 40) return 'Moyen';
    return 'À améliorer';
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}
