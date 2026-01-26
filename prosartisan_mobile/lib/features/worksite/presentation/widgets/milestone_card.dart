import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../domain/models/jalon.dart';

/// Card widget for displaying milestone information
///
/// Shows milestone status, description, and progress indicators
/// Requirements: 6.1, 6.2
class MilestoneCard extends StatelessWidget {
  final Jalon jalon;
  final VoidCallback? onTap;

  const MilestoneCard({super.key, required this.jalon, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Padding(
          padding: EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildHeader(context),
              SizedBox(height: AppSpacing.sm),
              _buildDescription(context),
              SizedBox(height: AppSpacing.sm),
              _buildFooter(context),
              if (jalon.needsUrgentAction) ...[
                SizedBox(height: AppSpacing.sm),
                _buildUrgentBanner(context),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Row(
      children: [
        // Sequence number badge
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: _getStatusColor(jalon.status),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              '${jalon.sequenceNumber}',
              style: AppTypography.badge.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
        SizedBox(width: AppSpacing.md),

        // Status and title
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Jalon ${jalon.sequenceNumber}',
                style: AppTypography.sectionTitle.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              SizedBox(height: AppSpacing.xs),
              Row(
                children: [
                  Container(
                    padding: EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: AppSpacing.xs,
                    ),
                    decoration: BoxDecoration(
                      color: _getStatusColor(
                        jalon.status,
                      ).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(AppRadius.badge),
                      border: Border.all(
                        color: _getStatusColor(
                          jalon.status,
                        ).withValues(alpha: 0.3),
                      ),
                    ),
                    child: Text(
                      jalon.statusLabel,
                      style: AppTypography.badge.copyWith(
                        color: _getStatusColor(jalon.status),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  if (jalon.hasProof) ...[
                    SizedBox(width: AppSpacing.sm),
                    Icon(
                      Icons.photo_camera,
                      size: 16,
                      color: AppColors.accentSuccess,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),

        // Status icon
        Icon(
          _getStatusIcon(jalon.status),
          color: _getStatusColor(jalon.status),
          size: 24,
        ),
      ],
    );
  }

  Widget _buildDescription(BuildContext context) {
    return Text(
      jalon.description,
      style: AppTypography.body.copyWith(color: AppColors.textPrimary),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _buildFooter(BuildContext context) {
    return Row(
      children: [
        // Amount
        Icon(Icons.payments, size: 16, color: AppColors.textSecondary),
        SizedBox(width: AppSpacing.xs),
        Text(
          jalon.laborAmount.formatted,
          style: AppTypography.bodySmall.copyWith(
            fontWeight: FontWeight.w500,
            color: AppColors.textSecondary,
          ),
        ),

        const Spacer(),

        // Time info
        if (jalon.autoValidationDeadline != null && !jalon.isCompleted) ...[
          Icon(
            Icons.timer,
            size: 16,
            color: jalon.isAutoValidationDue
                ? AppColors.accentDanger
                : AppColors.accentWarning,
          ),
          SizedBox(width: AppSpacing.xs),
          Text(
            jalon.isAutoValidationDue
                ? 'Échéance dépassée'
                : '${jalon.hoursUntilAutoValidation?.toStringAsFixed(0)}h restantes',
            style: AppTypography.bodySmall.copyWith(
              color: jalon.isAutoValidationDue
                  ? AppColors.accentDanger
                  : AppColors.accentWarning,
              fontWeight: FontWeight.w500,
            ),
          ),
        ] else if (jalon.validatedAt != null) ...[
          Icon(Icons.check_circle, size: 16, color: AppColors.accentSuccess),
          SizedBox(width: AppSpacing.xs),
          Text(
            'Validé le ${_formatDate(jalon.validatedAt!)}',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.accentSuccess,
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildUrgentBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: AppColors.accentDanger.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(
          color: AppColors.accentDanger.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        children: [
          Icon(Icons.warning, size: 16, color: AppColors.accentDanger),
          SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              jalon.isAutoValidationDue
                  ? 'Validation automatique imminente'
                  : 'Action urgente requise',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.accentDanger,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _getStatusIcon(String status) {
    switch (status) {
      case 'PENDING':
        return Icons.hourglass_empty;
      case 'SUBMITTED':
        return Icons.upload_file;
      case 'VALIDATED':
        return Icons.check_circle;
      case 'CONTESTED':
        return Icons.warning;
      default:
        return Icons.info;
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PENDING':
        return AppColors.textSecondary;
      case 'SUBMITTED':
        return AppColors.accentWarning;
      case 'VALIDATED':
        return AppColors.accentSuccess;
      case 'CONTESTED':
        return AppColors.accentDanger;
      default:
        return AppColors.textSecondary;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}';
  }
}
