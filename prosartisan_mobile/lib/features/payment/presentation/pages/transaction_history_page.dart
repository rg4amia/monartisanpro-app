import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
import '../controllers/transaction_history_controller.dart';
import '../widgets/transaction_list_item.dart';
import '../widgets/transaction_filter_chips.dart';

/// Transaction history screen
///
/// Requirements: 4.6, 13.6
class TransactionHistoryPage extends StatelessWidget {
  const TransactionHistoryPage({super.key});

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
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Historique des transactions',
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(Icons.refresh, color: AppColors.textLight),
            onPressed: () => controller.refreshTransactions(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Filter chips
          Container(
            padding: EdgeInsets.symmetric(
              horizontal: AppSpacing.md,
              vertical: AppSpacing.sm,
            ),
            decoration: BoxDecoration(
              color: AppColors.surface,
              border: Border(
                bottom: BorderSide(color: AppColors.border, width: 1),
              ),
            ),
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
                return Center(
                  child: CircularProgressIndicator(color: AppColors.primary),
                );
              }

              if (controller.transactions.isEmpty) {
                return EmptyStateCard(
                  icon: Icons.receipt_long,
                  title: 'Aucune transaction trouvée',
                  subtitle: 'Vos transactions apparaîtront ici',
                );
              }

              return RefreshIndicator(
                onRefresh: () => controller.refreshTransactions(),
                color: AppColors.primary,
                child: ListView.builder(
                  padding: EdgeInsets.all(AppSpacing.md),
                  itemCount:
                      controller.transactions.length +
                      (controller.hasMorePages.value ? 1 : 0),
                  itemBuilder: (context, index) {
                    // Load more indicator
                    if (index == controller.transactions.length) {
                      if (controller.isLoadingMore.value) {
                        return Padding(
                          padding: EdgeInsets.all(AppSpacing.md),
                          child: Center(
                            child: CircularProgressIndicator(
                              color: AppColors.primary,
                            ),
                          ),
                        );
                      } else {
                        // Load more button
                        return Padding(
                          padding: EdgeInsets.all(AppSpacing.md),
                          child: PrimaryButton(
                            onPressed: () => controller.loadMoreTransactions(),
                            text: 'Charger plus',
                            isFullWidth: true,
                          ),
                        );
                      }
                    }

                    final transaction = controller.transactions[index];
                    return Padding(
                      padding: EdgeInsets.only(bottom: AppSpacing.sm),
                      child: TransactionListItem(
                        transaction: transaction,
                        onTap: () =>
                            _showTransactionDetails(context, transaction),
                      ),
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
      backgroundColor: AppColors.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(AppTheme.radiusLg),
        ),
      ),
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.7,
        maxChildSize: 0.9,
        minChildSize: 0.5,
        expand: false,
        builder: (context, scrollController) => Container(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),

              SizedBox(height: AppSpacing.lg),

              // Title
              Text(
                'Détails de la transaction',
                style: AppTypography.headingMedium.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),

              SizedBox(height: AppSpacing.lg),

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
              PrimaryButton(
                onPressed: () => Navigator.of(context).pop(),
                text: 'Fermer',
                isFullWidth: true,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: AppTypography.bodyMedium.copyWith(
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.bodyMedium.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
