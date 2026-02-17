import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';
import 'status_badge.dart';
import 'custom_card.dart';

class ProjectCard extends StatelessWidget {
  final int id;
  final String title;
  final String? description;
  final String status;
  final String? artisanName;
  final double? finalAmount;
  final DateTime? createdAt;
  final DateTime? deadline;
  final VoidCallback onTap;

  const ProjectCard({
    super.key,
    required this.id,
    required this.title,
    this.description,
    required this.status,
    this.artisanName,
    this.finalAmount,
    this.createdAt,
    this.deadline,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final currencyFormat = NumberFormat.currency(
      symbol: 'XOF',
      decimalDigits: 0,
      locale: 'fr_FR',
    );

    return CustomCard(
      onTap: onTap,
      padding: const EdgeInsets.all(Spacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: Title & Status
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (description != null && description!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        description!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: Spacing.sm),
              StatusBadge(
                status: status,
                type: StatusType.project,
              ),
            ],
          ),

          const SizedBox(height: Spacing.md),

          // Details
          Row(
            children: [
              // Artisan info
              if (artisanName != null) ...[
                Expanded(
                  child: Row(
                    children: [
                      const Icon(
                        Icons.person_outline,
                        size: 16,
                        color: AppColors.lightTextSecondary,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          artisanName!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.lightTextSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ] else ...[
                Expanded(
                  child: Row(
                    children: [
                      const Icon(
                        Icons.calendar_today,
                        size: 16,
                        color: AppColors.lightTextSecondary,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        createdAt != null
                            ? DateFormat('dd/MM/yyyy').format(createdAt!)
                            : '-',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // Amount
              if (finalAmount != null) ...[
                const SizedBox(width: Spacing.sm),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.sm,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.lightAccentPrimary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                  ),
                  child: Text(
                    currencyFormat.format(finalAmount),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.lightAccentPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ],
          ),

          // Deadline warning
          if (deadline != null && _isDeadlineNear(deadline!)) ...[
            const SizedBox(height: Spacing.sm),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.sm,
                vertical: 4,
              ),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Spacing.radiusSm),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.access_time,
                    size: 14,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    'Deadline: ${DateFormat('dd/MM/yyyy').format(deadline!)}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.warning,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  bool _isDeadlineNear(DateTime deadline) {
    final now = DateTime.now();
    final daysUntilDeadline = deadline.difference(now).inDays;
    return daysUntilDeadline <= 7 && daysUntilDeadline >= 0;
  }
}
