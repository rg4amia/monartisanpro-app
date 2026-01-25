import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_theme.dart';
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
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: AppColors.border),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
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
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),

        // Status and title
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Jalon ${jalon.sequenceNumber}',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 2),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: _getStatusColor(jalon.status).withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: _getStatusColor(jalon.status).withOpacity(0.3),
                      ),
                    ),
                    child: Text(
                      jalon.statusLabel,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: _getStatusColor(jalon.status),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  if (jalon.hasProof) ...[
                    const SizedBox(width: 8),
                    Icon(
                      Icons.photo_camera,
                      size: 16,
                      color: Colors.green[600],
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
      style: Theme.of(context).textTheme.bodyMedium,
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _buildFooter(BuildContext context) {
    return Row(
      children: [
        // Amount
        Icon(Icons.payments, size: 16, color: Colors.grey[600]),
        const SizedBox(width: 4),
        Text(
          jalon.laborAmount.formatted,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w500,
            color: Colors.grey[700],
          ),
        ),

        const Spacer(),

        // Time info
        if (jalon.autoValidationDeadline != null && !jalon.isCompleted) ...[
          Icon(
            Icons.timer,
            size: 16,
            color: jalon.isAutoValidationDue ? Colors.red : Colors.orange,
          ),
          const SizedBox(width: 4),
          Text(
            jalon.isAutoValidationDue
                ? 'Échéance dépassée'
                : '${jalon.hoursUntilAutoValidation?.toStringAsFixed(0)}h restantes',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: jalon.isAutoValidationDue ? Colors.red : Colors.orange,
              fontWeight: FontWeight.w500,
            ),
          ),
        ] else if (jalon.validatedAt != null) ...[
          Icon(Icons.check_circle, size: 16, color: Colors.green[600]),
          const SizedBox(width: 4),
          Text(
            'Validé le ${_formatDate(jalon.validatedAt!)}',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: Colors.green[600]),
          ),
        ],
      ],
    );
  }

  Widget _buildUrgentBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.red[50],
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: Colors.red[200]!),
      ),
      child: Row(
        children: [
          Icon(Icons.warning, size: 16, color: Colors.red[700]),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              jalon.isAutoValidationDue
                  ? 'Validation automatique imminente'
                  : 'Action urgente requise',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Colors.red[700],
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
        return AppColors.warning;
      case 'VALIDATED':
        return AppColors.success;
      case 'CONTESTED':
        return AppColors.error;
      default:
        return AppColors.textSecondary;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}';
  }
}
