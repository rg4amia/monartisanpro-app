import 'package:flutter/material.dart';
import '../../domain/entities/transaction.dart';

/// Transaction list item widget
class TransactionListItem extends StatelessWidget {
  final Transaction transaction;
  final VoidCallback? onTap;

  const TransactionListItem({Key? key, required this.transaction, this.onTap})
    : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: _buildTransactionIcon(),
        title: Text(
          _getTransactionTypeDisplayName(transaction.type),
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              transaction.amountFormatted,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: _getAmountColor(),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              _formatDate(transaction.createdAt),
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
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
        color = Colors.orange;
        break;
      case 'MATERIAL_RELEASE':
        icon = Icons.build;
        color = Colors.blue;
        break;
      case 'LABOR_RELEASE':
        icon = Icons.work;
        color = Colors.green;
        break;
      case 'REFUND':
        icon = Icons.undo;
        color = Colors.red;
        break;
      default:
        icon = Icons.account_balance_wallet;
        color = Colors.grey;
    }

    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, color: color, size: 20),
    );
  }

  Widget _buildStatusChip() {
    Color color;
    String label;

    switch (transaction.status) {
      case 'PENDING':
        color = Colors.orange;
        label = 'En attente';
        break;
      case 'COMPLETED':
        color = Colors.green;
        label = 'Terminée';
        break;
      case 'FAILED':
        color = Colors.red;
        label = 'Échouée';
        break;
      default:
        color = Colors.grey;
        label = transaction.status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }

  Color _getAmountColor() {
    switch (transaction.type) {
      case 'ESCROW_BLOCK':
        return Colors.orange.shade700;
      case 'MATERIAL_RELEASE':
      case 'LABOR_RELEASE':
        return Colors.green.shade700;
      case 'REFUND':
        return Colors.blue.shade700;
      default:
        return Colors.black87;
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
