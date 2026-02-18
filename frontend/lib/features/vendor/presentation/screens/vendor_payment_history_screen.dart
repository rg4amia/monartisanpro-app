import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/models/escrow_model.dart';

class VendorPaymentHistoryScreen extends StatefulWidget {
  const VendorPaymentHistoryScreen({super.key});

  @override
  State<VendorPaymentHistoryScreen> createState() =>
      _VendorPaymentHistoryScreenState();
}

class _VendorPaymentHistoryScreenState
    extends State<VendorPaymentHistoryScreen> {
  final List<Transaction> _transactions = [];
  bool _isLoading = true;
  String _filterStatus = 'all';

  @override
  void initState() {
    super.initState();
    _loadTransactions();
  }

  Future<void> _loadTransactions() async {
    setState(() => _isLoading = true);
    // TODO: Implement API call to fetch vendor's payment transactions
    await Future.delayed(const Duration(seconds: 1));
    setState(() => _isLoading = false);
  }

  List<Transaction> get _filteredTransactions {
    if (_filterStatus == 'all') return _transactions;
    return _transactions.where((t) => t.status == _filterStatus).toList();
  }

  double get _totalPending {
    return _transactions
        .where((t) => t.status == 'pending')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  double get _totalCompleted {
    return _transactions
        .where((t) => t.status == 'completed')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Historique des paiements'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: _showFilterDialog,
          ),
        ],
      ),
      body: Column(
        children: [
          // Summary Cards
          Container(
            padding: const EdgeInsets.all(Spacing.md),
            color: Colors.grey[100],
            child: Row(
              children: [
                Expanded(
                  child: _SummaryCard(
                    title: 'En attente',
                    amount: _totalPending,
                    color: AppColors.warning,
                    icon: Icons.pending_actions,
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: _SummaryCard(
                    title: 'Reçus',
                    amount: _totalCompleted,
                    color: AppColors.success,
                    icon: Icons.check_circle,
                  ),
                ),
              ],
            ),
          ),
          // Transactions List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _transactions.isEmpty
                ? _buildEmptyState()
                : RefreshIndicator(
                    onRefresh: _loadTransactions,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(Spacing.md),
                      itemCount: _filteredTransactions.length,
                      itemBuilder: (context, index) {
                        final transaction = _filteredTransactions[index];
                        return _TransactionCard(transaction: transaction);
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.payments_outlined, size: 80, color: Colors.grey[400]),
            const SizedBox(height: Spacing.lg),
            Text(
              'Aucun paiement',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              'Vos paiements apparaîtront ici',
              style: TextStyle(fontSize: 14, color: Colors.grey[500]),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Filtrer par statut'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _FilterOption(
              label: 'Tous',
              value: 'all',
              groupValue: _filterStatus,
              onChanged: (v) {
                setState(() => _filterStatus = v!);
                Navigator.pop(context);
              },
            ),
            _FilterOption(
              label: 'En attente',
              value: 'pending',
              groupValue: _filterStatus,
              onChanged: (v) {
                setState(() => _filterStatus = v!);
                Navigator.pop(context);
              },
            ),
            _FilterOption(
              label: 'Complété',
              value: 'completed',
              groupValue: _filterStatus,
              onChanged: (v) {
                setState(() => _filterStatus = v!);
                Navigator.pop(context);
              },
            ),
            _FilterOption(
              label: 'Échoué',
              value: 'failed',
              groupValue: _filterStatus,
              onChanged: (v) {
                setState(() => _filterStatus = v!);
                Navigator.pop(context);
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String title;
  final double amount;
  final Color color;
  final IconData icon;

  const _SummaryCard({
    required this.title,
    required this.amount,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Column(
          children: [
            Icon(icon, color: color, size: 32),
            const SizedBox(height: Spacing.sm),
            Text(
              title,
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: Spacing.xs),
            Text(
              '${amount.toStringAsFixed(0)} FCFA',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TransactionCard extends StatelessWidget {
  final Transaction transaction;

  const _TransactionCard({required this.transaction});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: ListTile(
        leading: _StatusIcon(status: transaction.status),
        title: Text(
          transaction.formattedAmount,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: Spacing.xs),
            Text(transaction.description),
            const SizedBox(height: Spacing.xs),
            Text(
              DateFormat('dd/MM/yyyy à HH:mm').format(transaction.createdAt),
              style: const TextStyle(fontSize: 12),
            ),
          ],
        ),
        trailing: _StatusBadge(status: transaction.status),
        isThreeLine: true,
      ),
    );
  }
}

class _StatusIcon extends StatelessWidget {
  final String status;

  const _StatusIcon({required this.status});

  @override
  Widget build(BuildContext context) {
    IconData icon;
    Color color;

    switch (status) {
      case 'completed':
        icon = Icons.check_circle;
        color = AppColors.success;
        break;
      case 'pending':
        icon = Icons.pending;
        color = AppColors.warning;
        break;
      case 'failed':
        icon = Icons.error;
        color = AppColors.error;
        break;
      default:
        icon = Icons.help;
        color = Colors.grey;
    }

    return CircleAvatar(
      backgroundColor: color.withOpacity(0.1),
      child: Icon(icon, color: color),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;

    switch (status) {
      case 'completed':
        color = AppColors.success;
        label = 'Reçu';
        break;
      case 'pending':
        color = AppColors.warning;
        label = 'En attente';
        break;
      case 'failed':
        color = AppColors.error;
        label = 'Échoué';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.xs,
      ),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}

class _FilterOption extends StatelessWidget {
  final String label;
  final String value;
  final String groupValue;
  final ValueChanged<String?> onChanged;

  const _FilterOption({
    required this.label,
    required this.value,
    required this.groupValue,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return RadioListTile<String>(
      title: Text(label),
      value: value,
      groupValue: groupValue,
      onChanged: onChanged,
    );
  }
}
