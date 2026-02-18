import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/models/escrow_model.dart';

class ArtisanPaymentHistoryScreen extends StatefulWidget {
  const ArtisanPaymentHistoryScreen({super.key});

  @override
  State<ArtisanPaymentHistoryScreen> createState() => _ArtisanPaymentHistoryScreenState();
}

class _ArtisanPaymentHistoryScreenState extends State<ArtisanPaymentHistoryScreen> {
  final List<Transaction> _transactions = [];
  bool _isLoading = true;
  String _filterType = 'all';

  @override
  void initState() {
    super.initState();
    _loadTransactions();
  }

  Future<void> _loadTransactions() async {
    setState(() => _isLoading = true);
    // TODO: Implement API call to fetch artisan's payment transactions
    await Future.delayed(const Duration(seconds: 1));
    setState(() => _isLoading = false);
  }

  List<Transaction> get _filteredTransactions {
    if (_filterType == 'all') return _transactions;
    return _transactions.where((t) => t.type == _filterType).toList();
  }

  double get _totalEarned {
    return _transactions
        .where((t) => t.status == 'completed' && t.type == 'labor_release')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  double get _totalPending {
    return _transactions
        .where((t) => t.status == 'pending' && t.type == 'labor_release')
        .fold(0.0, (sum, t) => sum + t.amount);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Historique des paiements'),
        actions: [
          IconButton(icon: const Icon(Icons.filter_list), onPressed: _showFilterDialog),
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
                Expanded(child: _SummaryCard(title: 'Gains totaux', amount: _totalEarned, color: AppColors.success, icon: Icons.account_balance_wallet)),
                const SizedBox(width: Spacing.md),
                Expanded(child: _SummaryCard(title: 'En attente', amount: _totalPending, color: AppColors.warning, icon: Icons.pending_actions)),
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
            Text('Aucun paiement', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.grey[600])),
            const SizedBox(height: Spacing.sm),
            Text('Vos paiements apparaîtront ici', style: TextStyle(fontSize: 14, color: Colors.grey[500])),
          ],
        ),
      ),
    );
  }

  void _showFilterDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Filtrer par type'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadioListTile<String>(title: const Text('Tous'), value: 'all', groupValue: _filterType, onChanged: (v) {
              setState(() => _filterType = v!);
              Navigator.pop(context);
            }),
            RadioListTile<String>(title: const Text('Main-d\'œuvre'), value: 'labor_release', groupValue: _filterType, onChanged: (v) {
              setState(() => _filterType = v!);
              Navigator.pop(context);
            }),
            RadioListTile<String>(title: const Text('Bonus'), value: 'bonus', groupValue: _filterType, onChanged: (v) {
              setState(() => _filterType = v!);
              Navigator.pop(context);
            }),
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

  const _SummaryCard({required this.title, required this.amount, required this.color, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Column(
          children: [
            Icon(icon, color: color, size: 32),
            const SizedBox(height: Spacing.sm),
            Text(title, style: const TextStyle(fontSize: 12, color: Colors.grey)),
            const SizedBox(height: Spacing.xs),
            Text('${amount.toStringAsFixed(0)} FCFA', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
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
        leading: _TypeIcon(type: transaction.type),
        title: Text(transaction.formattedAmount, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: Spacing.xs),
            Text(transaction.description),
            const SizedBox(height: Spacing.xs),
            Text(DateFormat('dd/MM/yyyy à HH:mm').format(transaction.createdAt), style: const TextStyle(fontSize: 12)),
          ],
        ),
        trailing: _StatusBadge(status: transaction.status),
        isThreeLine: true,
      ),
    );
  }
}

class _TypeIcon extends StatelessWidget {
  final String type;

  const _TypeIcon({required this.type});

  @override
  Widget build(BuildContext context) {
    IconData icon;
    Color color;

    switch (type) {
      case 'labor_release':
        icon = Icons.work;
        color = AppColors.success;
        break;
      case 'bonus':
        icon = Icons.star;
        color = AppColors.starRating;
        break;
      default:
        icon = Icons.payment;
        color = AppColors.info;
    }

    return CircleAvatar(backgroundColor: color.withOpacity(0.1), child: Icon(icon, color: color));
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
      padding: const EdgeInsets.symmetric(horizontal: Spacing.sm, vertical: Spacing.xs),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
      child: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
    );
  }
}
