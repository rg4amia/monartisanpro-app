import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../domain/entities/transaction.dart';

/// Transaction list item widget
class TransactionListItem extends StatelessWidget {
  final Transaction transaction;
  final VoidCallback? onTap;

  const TransactionListItem({super.key, required this.transaction, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: AppSpacing.sm),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: ListTile(
        leading: _buildTransactionIcon(),
        title: Text(
          _getTransactionTypeDisplayName(transaction.type),
          style: AppTypography.body.copyWith(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.w500,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: AppSpacing.xs),
            Text(
              transaction.amountFormatted,
              style: AppTypography.body.copyWith(
                fontWeight: FontWeight.bold,
                color: _getAmountColor(),
              ),
            ),
            SizedBox(height: AppSpacing.xs),
            Text(
              _formatDate(transaction.createdAt),
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
        trailing: _buildStatusChip(),
        onTap: onTap,
      ),
    );
  }

  Widget _buildTransactionIcon() {
    IconData icon;
    Color color;

    switch (transaction.type) {
      case 'ESCROW_BLOCK':
        icon = Icons.lock;
        color = AppColors.accentWarning;
        break;
      case 'MATERIAL_RELEASE':
        icon = Icons.build;
        color = AppColors.accentPrimary;
        break;
      case 'LABOR_RELEASE':
        icon = Icons.work;
        color = AppColors.accentSuccess;
        break;
      case 'REFUND':
        icon = Icons.undo;
        color = AppColors.accentDanger;
        break;
      default:
        icon = Icons.account_balance_wallet;
        color = AppColors.textSecondary;
    }

    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Icon(icon, color: color, size: 20),
    );
  }

  Widget _buildStatusChip() {
    Color color;
    String label;

    switch (transaction.status) {
      case 'PENDING':
        color = AppColors.accentWarning;
        label = 'En attente';
        break;
      case 'COMPLETED':
        color = AppColors.accentSuccess;
        label = 'Terminée';
        break;
      case 'FAILED':
        color = AppColors.accentDanger;
        label = 'Échouée';
        break;
      default:
        color = AppColors.textSecondary;
        label = transaction.status;
    }

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: AppSpacing.xs,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(
        label,
        style: AppTypography.bodySmall.copyWith(
          color: color,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }

  Color _getAmountColor() {
    switch (transaction.type) {
      case 'ESCROW_BLOCK':
        return AppColors.accentWarning;
      case 'MATERIAL_RELEASE':
      case 'LABOR_RELEASE':
        return AppColors.accentSuccess;
      case 'REFUND':
        return AppColors.accentPrimary;
      default:
        return AppColors.textPrimary;
    }
  }

  String _getTransactionTypeDisplayName(String type) {
    switch (type) {
      case 'ESCROW_BLOCK':
        return 'Blocage séquestre';
      case 'MATERIAL_RELEASE':
        return 'Libération matériaux';
      case 'LABOR_RELEASE':
        return 'Libération main d\'œuvre';
      case 'REFUND':
        return 'Remboursement';
      default:
        return type;
    }
  }

  String _formatDate(String dateString) {
    try {
      final date = DateTime.parse(dateString);
      return '${date.day.toString().padLeft(2, '0')}/'
          '${date.month.toString().padLeft(2, '0')}/'
          '${date.year} '
          '${date.hour.toString().padLeft(2, '0')}:'
          '${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateString;
    }
  }
}
