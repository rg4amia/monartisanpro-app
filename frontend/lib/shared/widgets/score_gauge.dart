import 'package:flutter/material.dart';
import 'dart:math' as math;
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

class ScoreGauge extends StatelessWidget {
  final double score;
  final double maxScore;
  final double size;
  final String? badgeLevel;
  final bool showBadge;

  const ScoreGauge({
    super.key,
    required this.score,
    this.maxScore = 100,
    this.size = 200,
    this.badgeLevel,
    this.showBadge = true,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final percentage = (score / maxScore).clamp(0.0, 1.0);

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Gauge background
          CustomPaint(
            size: Size(size, size),
            painter: _GaugePainter(
              percentage: percentage,
              color: _getScoreColor(score),
              backgroundColor: AppColors.lightBackground,
            ),
          ),

          // Score text
          Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                score.toStringAsFixed(1),
                style: theme.textTheme.displayMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: _getScoreColor(score),
                ),
              ),
              Text(
                '/${maxScore.toInt()}',
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
              ),
              if (showBadge && badgeLevel != null && badgeLevel != 'none') ...[
                const SizedBox(height: Spacing.sm),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.sm,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: _getBadgeColor(badgeLevel!).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                    border: Border.all(
                      color: _getBadgeColor(badgeLevel!).withValues(alpha: 0.3),
                    ),
                  ),
                  child: Text(
                    _getBadgeText(badgeLevel!),
                    style: TextStyle(
                      color: _getBadgeColor(badgeLevel!),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Color _getScoreColor(double score) {
    if (score >= 80) return AppColors.success;
    if (score >= 65) return AppColors.lightAccentSecondary;
    if (score >= 50) return AppColors.lightAccentPrimary;
    return AppColors.error;
  }

  Color _getBadgeColor(String badge) {
    switch (badge.toLowerCase()) {
      case 'gold':
        return AppColors.warning;
      case 'silver':
        return AppColors.lightTextSecondary;
      case 'bronze':
        return const Color(0xFFCD7F32);
      default:
        return AppColors.lightTextSecondary;
    }
  }

  String _getBadgeText(String badge) {
    switch (badge.toLowerCase()) {
      case 'gold':
        return '🥇 Or';
      case 'silver':
        return '🥈 Argent';
      case 'bronze':
        return '🥉 Bronze';
      default:
        return badge;
    }
  }
}

class _GaugePainter extends CustomPainter {
  final double percentage;
  final Color color;
  final Color backgroundColor;

  _GaugePainter({
    required this.percentage,
    required this.color,
    required this.backgroundColor,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width / 2;
    const strokeWidth = 20.0;

    // Background arc
    final backgroundPaint = Paint()
      ..color = backgroundColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius - strokeWidth / 2),
      -math.pi * 0.75, // Start angle (7:30 position)
      math.pi * 1.5, // Sweep angle (270 degrees)
      false,
      backgroundPaint,
    );

    // Foreground arc (score)
    final foregroundPaint = Paint()
      ..shader = LinearGradient(
        colors: [
          color,
          color.withValues(alpha: 0.7),
        ],
      ).createShader(Rect.fromCircle(center: center, radius: radius))
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius - strokeWidth / 2),
      -math.pi * 0.75,
      math.pi * 1.5 * percentage,
      false,
      foregroundPaint,
    );
  }

  @override
  bool shouldRepaint(_GaugePainter oldDelegate) {
    return oldDelegate.percentage != percentage ||
        oldDelegate.color != color ||
        oldDelegate.backgroundColor != backgroundColor;
  }
}
