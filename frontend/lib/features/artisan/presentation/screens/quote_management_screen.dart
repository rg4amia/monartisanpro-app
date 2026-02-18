import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/models/project_model.dart';

class QuoteManagementScreen extends StatefulWidget {
  const QuoteManagementScreen({super.key});

  @override
  State<QuoteManagementScreen> createState() => _QuoteManagementScreenState();
}

class _QuoteManagementScreenState extends State<QuoteManagementScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<Quote> _quotes = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _loadQuotes();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadQuotes() async {
    setState(() => _isLoading = true);
    // TODO: Implement API call to fetch artisan's quotes
    await Future.delayed(const Duration(seconds: 1));
    setState(() => _isLoading = false);
  }

  List<Quote> _getFilteredQuotes(String status) {
    if (status == 'all') return _quotes;
    return _quotes.where((q) => q.status == status).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes devis'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Tous'),
            Tab(text: 'En attente'),
            Tab(text: 'Acceptés'),
            Tab(text: 'Rejetés'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildQuoteList('all'),
          _buildQuoteList('pending'),
          _buildQuoteList('accepted'),
          _buildQuoteList('rejected'),
        ],
      ),
    );
  }

  Widget _buildQuoteList(String status) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final quotes = _getFilteredQuotes(status);

    if (quotes.isEmpty) {
      return _buildEmptyState(status);
    }

    return RefreshIndicator(
      onRefresh: _loadQuotes,
      child: ListView.builder(
        padding: const EdgeInsets.all(Spacing.md),
        itemCount: quotes.length,
        itemBuilder: (context, index) {
          final quote = quotes[index];
          return _QuoteCard(quote: quote);
        },
      ),
    );
  }

  Widget _buildEmptyState(String status) {
    String message;
    switch (status) {
      case 'pending':
        message = 'Aucun devis en attente';
        break;
      case 'accepted':
        message = 'Aucun devis accepté';
        break;
      case 'rejected':
        message = 'Aucun devis rejeté';
        break;
      default:
        message = 'Aucun devis';
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.description_outlined, size: 80, color: Colors.grey[400]),
            const SizedBox(height: Spacing.lg),
            Text(message, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.grey[600])),
            const SizedBox(height: Spacing.sm),
            Text('Vos devis apparaîtront ici', style: TextStyle(fontSize: 14, color: Colors.grey[500])),
          ],
        ),
      ),
    );
  }
}

class _QuoteCard extends StatelessWidget {
  final Quote quote;

  const _QuoteCard({required this.quote});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    'Projet #${quote.projectId}',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
                _StatusBadge(status: quote.status),
              ],
            ),
            const Divider(height: Spacing.lg),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Montant total', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    const SizedBox(height: Spacing.xs),
                    Text('${quote.totalAmount.toStringAsFixed(0)} FCFA', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const Text('Validité', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    const SizedBox(height: Spacing.xs),
                    Text('${quote.validDays} jours', style: const TextStyle(fontSize: 14)),
                  ],
                ),
              ],
            ),
            const SizedBox(height: Spacing.md),
            Row(
              children: [
                Icon(Icons.calendar_today, size: 16, color: Colors.grey[600]),
                const SizedBox(width: Spacing.xs),
                Text(
                  'Créé le ${DateFormat('dd/MM/yyyy').format(quote.createdAt)}',
                  style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                ),
              ],
            ),
            if (quote.notes != null && quote.notes!.isNotEmpty) ...[
              const SizedBox(height: Spacing.sm),
              Text(quote.notes!, style: const TextStyle(fontSize: 14, fontStyle: FontStyle.italic)),
            ],
          ],
        ),
      ),
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
      case 'pending':
        color = AppColors.warning;
        label = 'En attente';
        break;
      case 'accepted':
        color = AppColors.success;
        label = 'Accepté';
        break;
      case 'rejected':
        color = AppColors.error;
        label = 'Rejeté';
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
