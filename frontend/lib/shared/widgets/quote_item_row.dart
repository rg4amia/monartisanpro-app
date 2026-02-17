import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';
import 'status_badge.dart';
import 'custom_card.dart';

class QuoteItemRow extends StatelessWidget {
  final int id;
  final String artisanName;
  final String? artisanAvatar;
  final double totalAmount;
  final int materialPercentage;
  final int laborPercentage;
  final String status;
  final DateTime? validUntil;
  final VoidCallback onTap;

  const QuoteItemRow({
    super.key,
    required this.id,
    required this.artisanName,
    this.artisanAvatar,
    required this.totalAmount,
    required this.materialPercentage,
    required this.laborPercentage,
    required this.status,
    this.validUntil,
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

    final isExpired = validUntil != null && validUntil!.isBefore(DateTime.now());

    return CustomCard(
      onTap: onTap,
      padding: const EdgeInsets.all(Spacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: Artisan & Status
          Row(
            children: [
              // Avatar
              CircleAvatar(
                radius: 20,
                backgroundImage: artisanAvatar != null
                    ? NetworkImage(artisanAvatar!)
                    : null,
                child: artisanAvatar == null
                    ? const Icon(Icons.person, size: 20)
                    : null,
              ),
              const SizedBox(width: Spacing.sm),

              // Name
              Expanded(
                child: Text(
                  artisanName,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),

              const SizedBox(width: Spacing.sm),

              // Status
              StatusBadge(
                status: status,
                type: StatusType.quote,
              ),
            ],
          ),

          const SizedBox(height: Spacing.md),

          // Amount
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Montant Total',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
              ),
              Text(
                currencyFormat.format(totalAmount),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.lightAccentPrimary,
                ),
              ),
            ],
          ),

          const SizedBox(height: Spacing.sm),

          // Breakdown
          Row(
            children: [
              // Material
              Expanded(
                child: _BreakdownItem(
                  icon: Icons.handyman,
                  label: 'Matériel',
                  percentage: materialPercentage,
                  color: AppColors.lightAccentHighlight,
                ),
              ),
              const SizedBox(width: Spacing.sm),

              // Labor
              Expanded(
                child: _BreakdownItem(
                  icon: Icons.engineering,
                  label: 'Main d\'œuvre',
                  percentage: laborPercentage,
                  color: AppColors.lightAccentSecondary,
                ),
              ),
            ],
          ),

          // Valid until / Expired
          if (validUntil != null) ...[
            const SizedBox(height: Spacing.sm),
            Row(
              children: [
                Icon(
                  isExpired ? Icons.error_outline : Icons.schedule,
                  size: 14,
                  color: isExpired ? AppColors.error : AppColors.lightTextSecondary,
                ),
                const SizedBox(width: 4),
                Text(
                  isExpired
                      ? 'Expiré le ${DateFormat('dd/MM/yyyy').format(validUntil!)}'
                      : 'Valide jusqu\'au ${DateFormat('dd/MM/yyyy').format(validUntil!)}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: isExpired ? AppColors.error : AppColors.lightTextSecondary,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _BreakdownItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int percentage;
  final Color color;

  const _BreakdownItem({
    required this.icon,
    required this.label,
    required this.percentage,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(Spacing.sm),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
      ),
      child: Row(
        children: [
          Icon(
            icon,
            size: 16,
            color: color,
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 10,
                    color: AppColors.lightTextSecondary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  '$percentage%',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w600,
                    color: color,
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
