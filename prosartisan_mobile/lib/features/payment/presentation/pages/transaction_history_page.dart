import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../controllers/transaction_history_controller.dart';
import '../widgets/transaction_list_item.dart';
import '../widgets/transaction_filter_chips.dart';

/// Transaction history screen
///
/// Requirements: 4.6, 13.6
class TransactionHistoryPage extends StatelessWidget {
  const TransactionHistoryPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final TransactionHistoryController controller = Get.put(
      TransactionHistoryController(),
    );

    // Load transactions when page opens
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.loadTransactions();
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Historique des transactions'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => controller.refreshTransactions(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Filter chips
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: TransactionFilterChips(
              selectedFilter: controller.selectedFilter,
              onFilterChanged: (filter) => controller.setFilter(filter),
            ),
          ),

          // Transaction list
          Expanded(
            child: Obx(() {
              if (controller.isLoading.value &&
                  controller.transactions.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }

              if (controller.transactions.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.receipt_long,
                        size: 64,
                        color: Colors.grey.shade400,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Aucune transaction trouvée',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(color: Colors.grey.shade600),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Vos transactions apparaîtront ici',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.grey.shade500,
                        ),
                      ),
                    ],
                  ),
                );
              }

              return RefreshIndicator(
                onRefresh: () => controller.refreshTransactions(),
                child: ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount:
                      controller.transactions.length +
                      (controller.hasMorePages.value ? 1 : 0),
                  itemBuilder: (context, index) {
                    // Load more indicator
                    if (index == controller.transactions.length) {
                      if (controller.isLoadingMore.value) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(child: CircularProgressIndicator()),
                        );
                      } else {
                        // Load more button
                        return Padding(
                          padding: const EdgeInsets.all(16),
                          child: ElevatedButton(
                            onPressed: () => controller.loadMoreTransactions(),
                            child: const Text('Charger plus'),
                          ),
                        );
                      }
                    }

                    final transaction = controller.transactions[index];
                    return TransactionListItem(
                      transaction: transaction,
                      onTap: () =>
                          _showTransactionDetails(context, transaction),
                    );
                  },
                ),
              );
            }),
          ),
        ],
      ),
    );
  }

  void _showTransactionDetails(BuildContext context, dynamic transaction) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.7,
        maxChildSize: 0.9,
        minChildSize: 0.5,
        expand: false,
        builder: (context, scrollController) => Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // Title
              Text(
                'Détails de la transaction',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 20),

              // Transaction details
              Expanded(
                child: ListView(
                  controller: scrollController,
                  children: [
                    _buildDetailRow('ID', transaction.id),
                    _buildDetailRow('Type', transaction.type),
                    _buildDetailRow('Montant', transaction.amountFormatted),
                    _buildDetailRow('Statut', transaction.status),
                    _buildDetailRow('Date', transaction.createdAt),
                    if (transaction.completedAt != null)
                      _buildDetailRow('Complétée le', transaction.completedAt!),
                    if (transaction.mobileMoneyReference != null)
                      _buildDetailRow(
                        'Référence',
                        transaction.mobileMoneyReference!,
                      ),
                  ],
                ),
              ),

              // Close button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Fermer'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: const TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}
