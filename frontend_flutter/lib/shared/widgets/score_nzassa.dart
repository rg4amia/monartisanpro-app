import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

enum ScoreSize { small, medium, large }

class ScoreProsArtisan extends StatelessWidget {
  final int score;
  final ScoreSize size;
  final bool showLabel;

  const ScoreProsArtisan({
    super.key,
    required this.score,
    this.size = ScoreSize.medium,
    this.showLabel = false,
  });

  double get _diameter {
    switch (size) {
      case ScoreSize.small:
        return 32;
      case ScoreSize.medium:
        return 48;
      case ScoreSize.large:
        return 80;
    }
  }

  double get _fontSize {
    switch (size) {
      case ScoreSize.small:
        return 11;
      case ScoreSize.medium:
        return 14;
      case ScoreSize.large:
        return 22;
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = AppColors.scoreColor(score);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: _diameter,
          height: _diameter,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: color.withValues(alpha: 0.12),
            border: Border.all(color: color, width: 2),
          ),
          child: Center(
            child: Text(
              '$score',
              style: TextStyle(
                fontSize: _fontSize,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ),
        ),
        if (showLabel) ...[
          const SizedBox(height: 4),
          Text(
            'Score ProsArtisan',
            style: TextStyle(
              fontSize: 10,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );
  }
}

/// Badge compact pour afficher le score ProsArtisan dans les listes
class ScoreProsArtisanBadge extends StatelessWidget {
  final int score;
  
  const ScoreProsArtisanBadge({
    super.key,
    required this.score,
  });

  @override
  Widget build(BuildContext context) {
    final color = AppColors.scoreColor(score);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$score',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
          Text(
            'ProsArtisan',
            style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}
