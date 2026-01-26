import 'package:flutter/material.dart';
import '../../domain/entities/jeton.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Jeton information card widget
class JetonInfoCard extends StatelessWidget {
  final Jeton jeton;

  const JetonInfoCard({super.key, required this.jeton});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayLight),
      ),
      padding: EdgeInsets.all(AppSpacing.base),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Informations du jeton',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.base),

          _buildInfoRow(
            'Montant total',
            jeton.totalAmountFormatted,
            Icons.account_balance_wallet,
          ),

          _buildInfoRow(
            'Montant utilisé',
            jeton.usedAmountFormatted,
            Icons.shopping_cart,
          ),

          _buildInfoRow(
            'Montant restant',
            jeton.remainingAmountFormatted,
            Icons.savings,
            valueColor: AppColors.accentSuccess,
          ),

          Container(
            height: 1,
            margin: EdgeInsets.symmetric(vertical: AppSpacing.md),
            color: AppColors.overlayMedium,
          ),

          _buildInfoRow(
            'Créé le',
            _formatDate(jeton.createdAt),
            Icons.calendar_today,
          ),

          _buildInfoRow(
            'Expire le',
            _formatDate(jeton.expiresAt),
            Icons.schedule,
            valueColor: jeton.isExpired ? AppColors.accentDanger : null,
          ),

          if (jeton.authorizedSuppliers.isNotEmpty) ...[
            SizedBox(height: AppSpacing.md),
            Text(
              'Fournisseurs autorisés: ${jeton.authorizedSuppliers.length}',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildInfoRow(
    String label,
    String value,
    IconData icon, {
    Color? valueColor,
  }) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        children: [
          Icon(icon, size: 20, color: AppColors.textSecondary),
          SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              label,
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Text(
            value,
            style: AppTypography.body.copyWith(
              fontWeight: FontWeight.w500,
              color: valueColor ?? AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/'
        '${date.month.toString().padLeft(2, '0')}/'
        '${date.year} '
        '${date.hour.toString().padLeft(2, '0')}:'
        '${date.minute.toString().padLeft(2, '0')}';
  }
}
