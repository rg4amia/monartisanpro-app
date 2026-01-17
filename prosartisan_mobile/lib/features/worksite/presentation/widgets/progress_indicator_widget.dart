import 'package:flutter/material.dart';

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
    final effectiveProgressColor =
        progressColor ?? Theme.of(context).primaryColor;
    final effectiveBackgroundColor = backgroundColor ?? Colors.grey[300];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Progress bar
        Container(
          height: 8,
          decoration: BoxDecoration(
            color: effectiveBackgroundColor,
            borderRadius: BorderRadius.circular(4),
          ),
          child: FractionallySizedBox(
            alignment: Alignment.centerLeft,
            widthFactor: progress.clamp(0.0, 1.0),
            child: Container(
              decoration: BoxDecoration(
                color: effectiveProgressColor,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
        ),

        const SizedBox(height: 8),

        // Progress text and percentage
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              '$completedMilestones/$totalMilestones jalons terminés',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
            ),
            Text(
              '${(progress * 100).toInt()}%',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
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
    final effectiveProgressColor =
        progressColor ?? Theme.of(context).primaryColor;
    final effectiveBackgroundColor = backgroundColor ?? Colors.grey[300];

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
                effectiveBackgroundColor!,
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
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: effectiveProgressColor,
                  ),
                ),
                Text(
                  '$completedMilestones/$totalMilestones',
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
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
                            ? Colors.green
                            : isCurrent
                            ? Theme.of(context).primaryColor
                            : Colors.grey[300],
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
                                      : Colors.grey[600],
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
                        color: isCompleted ? Colors.green : Colors.grey[300],
                      ),
                  ],
                ),

                const SizedBox(width: 16),

                // Step content
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        step.title,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: isCurrent
                              ? FontWeight.bold
                              : FontWeight.normal,
                          color: isCompleted
                              ? Colors.green
                              : isCurrent
                              ? Theme.of(context).primaryColor
                              : Colors.grey[600],
                        ),
                      ),
                      if (step.description != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          step.description!,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: Colors.grey[600]),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            if (!isLast) const SizedBox(height: 8),
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
