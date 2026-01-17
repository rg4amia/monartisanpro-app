import 'package:flutter/material.dart';
import '../../domain/entities/jeton.dart';

/// Jeton information card widget
class JetonInfoCard extends StatelessWidget {
  final Jeton jeton;

  const JetonInfoCard({Key? key, required this.jeton}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Informations du jeton',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),

            _buildInfoRow(
              context,
              'Montant total',
              jeton.totalAmountFormatted,
              Icons.account_balance_wallet,
            ),

            _buildInfoRow(
              context,
              'Montant utilisé',
              jeton.usedAmountFormatted,
              Icons.shopping_cart,
            ),

            _buildInfoRow(
              context,
              'Montant restant',
              jeton.remainingAmountFormatted,
              Icons.savings,
              valueColor: Colors.green.shade600,
            ),

            const Divider(height: 24),

            _buildInfoRow(
              context,
              'Créé le',
              _formatDate(jeton.createdAt),
              Icons.calendar_today,
            ),

            _buildInfoRow(
              context,
              'Expire le',
              _formatDate(jeton.expiresAt),
              Icons.schedule,
              valueColor: jeton.isExpired ? Colors.red : null,
            ),

            if (jeton.authorizedSuppliers.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                'Fournisseurs autorisés: ${jeton.authorizedSuppliers.length}',
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(color: Colors.grey.shade600),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(
    BuildContext context,
    String label,
    String value,
    IconData icon, {
    Color? valueColor,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Colors.grey.shade600),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: Colors.grey.shade700),
            ),
          ),
          Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w500,
              color: valueColor,
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
