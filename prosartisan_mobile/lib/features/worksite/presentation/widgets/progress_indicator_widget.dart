import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Custom progress indicator widget for worksite progress
///
/// Shows visual progress with completed/total milestones
/// Requirements: 6.1
class WorksiteProgressIndicator extends StatelessWidget {
  final double progress; // 0.0 to 1.0
  final int completedMilestones;
  final int totalMilestones;
  final Color? progressColor;
  final Color? backgroundColor;

  const WorksiteProgressIndicator({
    super.key,
    required this.progress,
    required this.completedMilestones,
    required this.totalMilestones,
    this.progressColor,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final effectiveProgressColor = progressColor ?? AppColors.accentPrimary;
    final effectiveBackgroundColor = backgroundColor ?? AppColors.overlayMedium;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Progress bar
        Container(
          height: 8,
          decoration: BoxDecoration(
            color: effectiveBackgroundColor,
            borderRadius: BorderRadius.circular(AppRadius.sm),
          ),
          child: FractionallySizedBox(
            alignment: Alignment.centerLeft,
            widthFactor: progress.clamp(0.0, 1.0),
            child: Container(
              decoration: BoxDecoration(
                color: effectiveProgressColor,
                borderRadius: BorderRadius.circular(AppRadius.sm),
              ),
            ),
          ),
        ),

        SizedBox(height: AppSpacing.sm),

        // Progress text and percentage
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '$completedMilestones/$totalMilestones jalons terminés',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            Text(
              '${(progress * 100).toInt()}%',
              style: AppTypography.bodySmall.copyWith(
                fontWeight: FontWeight.w600,
                color: effectiveProgressColor,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Circular progress indicator for worksite progress
class WorksiteCircularProgress extends StatelessWidget {
  final double progress; // 0.0 to 1.0
  final int completedMilestones;
  final int totalMilestones;
  final double size;
  final Color? progressColor;
  final Color? backgroundColor;

  const WorksiteCircularProgress({
    super.key,
    required this.progress,
    required this.completedMilestones,
    required this.totalMilestones,
    this.size = 80,
    this.progressColor,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final effectiveProgressColor = progressColor ?? AppColors.accentPrimary;
    final effectiveBackgroundColor = backgroundColor ?? AppColors.overlayMedium;

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          // Background circle
          SizedBox(
            width: size,
            height: size,
            child: CircularProgressIndicator(
              value: 1.0,
              strokeWidth: 6,
              valueColor: AlwaysStoppedAnimation<Color>(
                effectiveBackgroundColor,
              ),
            ),
          ),

          // Progress circle
          SizedBox(
            width: size,
            height: size,
            child: CircularProgressIndicator(
              value: progress.clamp(0.0, 1.0),
              strokeWidth: 6,
              valueColor: AlwaysStoppedAnimation<Color>(effectiveProgressColor),
            ),
          ),

          // Center text
          Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  '${(progress * 100).toInt()}%',
                  style: AppTypography.sectionTitle.copyWith(
                    fontWeight: FontWeight.bold,
                    color: effectiveProgressColor,
                  ),
                ),
                Text(
                  '$completedMilestones/$totalMilestones',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Milestone progress steps widget
class MilestoneProgressSteps extends StatelessWidget {
  final List<MilestoneStep> steps;
  final int currentStep;

  const MilestoneProgressSteps({
    super.key,
    required this.steps,
    required this.currentStep,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: steps.asMap().entries.map((entry) {
        final index = entry.key;
        final step = entry.value;
        final isCompleted = index < currentStep;
        final isCurrent = index == currentStep;
        final isLast = index == steps.length - 1;

        return Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Step indicator
                Column(
                  children: [
                    Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: isCompleted
                            ? AppColors.accentSuccess
                            : isCurrent
                            ? AppColors.accentPrimary
                            : AppColors.overlayMedium,
                      ),
                      child: Center(
                        child: isCompleted
                            ? const Icon(
                                Icons.check,
                                color: Colors.white,
                                size: 16,
                              )
                            : Text(
                                '${index + 1}',
                                style: TextStyle(
                                  color: isCurrent
                                      ? Colors.white
                                      : AppColors.textSecondary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                      ),
                    ),
                    if (!isLast)
                      Container(
                        width: 2,
                        height: 40,
                        color: isCompleted
                            ? AppColors.accentSuccess
                            : AppColors.overlayMedium,
                      ),
                  ],
                ),

                SizedBox(width: AppSpacing.base),

                // Step content
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        step.title,
                        style: AppTypography.body.copyWith(
                          fontWeight: isCurrent
                              ? FontWeight.bold
                              : FontWeight.normal,
                          color: isCompleted
                              ? AppColors.accentSuccess
                              : isCurrent
                              ? AppColors.accentPrimary
                              : AppColors.textSecondary,
                        ),
                      ),
                      if (step.description != null) ...[
                        SizedBox(height: AppSpacing.xs),
                        Text(
                          step.description!,
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            if (!isLast) SizedBox(height: AppSpacing.sm),
          ],
        );
      }).toList(),
    );
  }
}

/// Data class for milestone steps
class MilestoneStep {
  final String title;
  final String? description;

  const MilestoneStep({required this.title, this.description});
}
