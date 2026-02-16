import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/models/escrow_model.dart';
import '../../../../shared/models/project_model.dart';
import 'material_token_screen.dart';

class EscrowDashboardScreen extends StatefulWidget {
  final int projectId;

  const EscrowDashboardScreen({super.key, required this.projectId});

  @override
  State<EscrowDashboardScreen> createState() => _EscrowDashboardScreenState();
}

class _EscrowDashboardScreenState extends State<EscrowDashboardScreen> {
  final _projectController = Get.find<ProjectController>();
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadEscrowData();
  }

  Future<void> _loadEscrowData() async {
    setState(() => _isLoading = true);
    await _projectController.fetchEscrowWallet(widget.projectId);
    await _projectController.fetchProjectTransactions(widget.projectId);
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(() {
        final wallet = _projectController.escrowWallet.value;
        final transactions = _projectController.projectTransactions;

        if (_isLoading && wallet == null) {
          return const Center(child: CircularProgressIndicator());
        }

        if (wallet == null) {
          return const Center(child: Text('Portefeuille non disponible'));
        }

        return RefreshIndicator(
          onRefresh: _loadEscrowData,
          child: CustomScrollView(
            slivers: [
              // App Bar
              SliverAppBar(
                expandedHeight: 120,
                pinned: true,
                flexibleSpace: FlexibleSpaceBar(
                  title: const Text(
                    'Portefeuille Séquestre',
                    style: TextStyle(
                      shadows: [
                        Shadow(
                          color: Colors.black45,
                          blurRadius: 10,
                        ),
                      ],
                    ),
                  ),
                  background: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: AppColors.primaryGradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                  ),
                ),
              ),

              // Content
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.screenPadding),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Total Balance Card
                      Container(
                        padding: const EdgeInsets.all(Spacing.lg),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: AppColors.primaryGradient,
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          boxShadow: [
                            BoxShadow(
                              color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.3),
                              blurRadius: 15,
                              offset: const Offset(0, 5),
                            ),
                          ],
                        ),
                        child: Column(
                          children: [
                            Text(
                              'Solde Total',
                              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.9),
                                  ),
                            ),
                            const SizedBox(height: Spacing.sm),
                            Text(
                              '${wallet.totalAmount.toStringAsFixed(0)} FCFA',
                              style: Theme.of(context).textTheme.displaySmall?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            const SizedBox(height: Spacing.md),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.lock_outline,
                                  color: Colors.white.withValues(alpha: 0.9),
                                  size: 16,
                                ),
                                const SizedBox(width: Spacing.xs),
                                Text(
                                  'Fonds sécurisés',
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: Colors.white.withValues(alpha: 0.9),
                                      ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: Spacing.xl),

                      // Wallet Breakdown
                      Row(
                        children: [
                          Expanded(
                            child: _buildWalletCard(
                              context,
                              'Matériaux',
                              wallet.materialWallet,
                              wallet.materialRemaining,
                              wallet.materialSpent,
                              Icons.inventory_2_outlined,
                              AppColors.warning,
                            ),
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: _buildWalletCard(
                              context,
                              'Main d\'œuvre',
                              wallet.laborWallet,
                              wallet.laborRemaining,
                              wallet.laborReleased,
                              Icons.construction_outlined,
                              AppColors.info,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.xl),

                      // Progress Chart
                      _buildProgressSection(context, wallet),
                      const SizedBox(height: Spacing.xl),

                      // Material Token Button
                      ElevatedButton.icon(
                        onPressed: () async {
                          final result = await Get.to(() => MaterialTokenScreen(
                                projectId: widget.projectId,
                              ));
                          if (result == true) {
                            _loadEscrowData();
                          }
                        },
                        icon: const Icon(Icons.qr_code_2),
                        label: const Text('Voir jeton matériaux'),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                        ),
                      ),
                      const SizedBox(height: Spacing.xl),

                      // Transactions Section
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Transactions',
                            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                          Text(
                            '${transactions.length} opération${transactions.length > 1 ? 's' : ''}',
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: AppColors.lightTextSecondary,
                                ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.md),

                      if (transactions.isEmpty)
                        Container(
                          padding: const EdgeInsets.all(Spacing.xl),
                          decoration: BoxDecoration(
                            color: AppColors.lightBackground,
                            borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          ),
                          child: Column(
                            children: [
                              Icon(
                                Icons.receipt_long_outlined,
                                size: 48,
                                color: AppColors.lightTextTertiary,
                              ),
                              const SizedBox(height: Spacing.md),
                              Text(
                                'Aucune transaction',
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      color: AppColors.lightTextSecondary,
                                    ),
                              ),
                            ],
                          ),
                        )
                      else
                        ...transactions.map((transaction) => _buildTransactionCard(
                              context,
                              transaction,
                            )),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildWalletCard(
    BuildContext context,
    String title,
    double total,
    double remaining,
    double used,
    IconData icon,
    Color color,
  ) {
    final percentage = total > 0 ? (used / total) * 100 : 0;

    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 20),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: color,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.md),
          Text(
            '${remaining.toStringAsFixed(0)} FCFA',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
          Text(
            'sur ${total.toStringAsFixed(0)} FCFA',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
          ),
          const SizedBox(height: Spacing.md),
          ClipRRect(
            borderRadius: BorderRadius.circular(Spacing.radiusSm),
            child: LinearProgressIndicator(
              value: percentage / 100,
              backgroundColor: Colors.grey[300],
              valueColor: AlwaysStoppedAnimation<Color>(color),
              minHeight: 6,
            ),
          ),
          const SizedBox(height: Spacing.xs),
          Text(
            '${percentage.toStringAsFixed(0)}% utilisé',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
          ),
        ],
      ),
    );
  }

  Widget _buildProgressSection(BuildContext context, EscrowWallet wallet) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Répartition des fonds',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
        const SizedBox(height: Spacing.md),
        SizedBox(
          height: 200,
          child: Row(
            children: [
              Expanded(
                child: PieChart(
                  PieChartData(
                    sections: [
                      PieChartSectionData(
                        value: wallet.materialRemaining,
                        title: wallet.materialRemaining > 0
                            ? '${((wallet.materialRemaining / wallet.totalAmount) * 100).toStringAsFixed(0)}%'
                            : '',
                        color: AppColors.warning,
                        radius: 70,
                        titleStyle: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      PieChartSectionData(
                        value: wallet.laborRemaining,
                        title: wallet.laborRemaining > 0
                            ? '${((wallet.laborRemaining / wallet.totalAmount) * 100).toStringAsFixed(0)}%'
                            : '',
                        color: AppColors.info,
                        radius: 70,
                        titleStyle: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      PieChartSectionData(
                        value: wallet.materialSpent + wallet.laborReleased,
                        title: (wallet.materialSpent + wallet.laborReleased) > 0
                            ? '${(((wallet.materialSpent + wallet.laborReleased) / wallet.totalAmount) * 100).toStringAsFixed(0)}%'
                            : '',
                        color: AppColors.lightTextTertiary,
                        radius: 70,
                        titleStyle: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ],
                    sectionsSpace: 2,
                    centerSpaceRadius: 0,
                  ),
                ),
              ),
              const SizedBox(width: Spacing.md),
              Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildLegendItem('Mat. restant', AppColors.warning),
                  const SizedBox(height: Spacing.sm),
                  _buildLegendItem('MO restante', AppColors.info),
                  const SizedBox(height: Spacing.sm),
                  _buildLegendItem('Utilisé', AppColors.lightTextTertiary),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLegendItem(String label, Color color) {
    return Row(
      children: [
        Container(
          width: 16,
          height: 16,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: Spacing.sm),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }

  Widget _buildTransactionCard(BuildContext context, Transaction transaction) {
    final isDebit = transaction.type == 'debit' || transaction.type == 'token_redemption';
    final icon = isDebit
        ? Icons.arrow_upward
        : Icons.arrow_downward;
    final color = isDebit ? AppColors.error : AppColors.success;

    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.1),
          child: Icon(icon, color: color, size: 20),
        ),
        title: Text(
          transaction.description,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
        ),
        subtitle: Text(
          _formatDateTime(transaction.createdAt),
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '${isDebit ? '-' : '+'}${transaction.amount.toStringAsFixed(0)} FCFA',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.xs,
                vertical: 2,
              ),
              decoration: BoxDecoration(
                color: _getTransactionStatusColor(transaction.status).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Spacing.radiusSm),
              ),
              child: Text(
                transaction.statusLabel,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  color: _getTransactionStatusColor(transaction.status),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _getTransactionStatusColor(String status) {
    switch (status) {
      case 'completed':
      case 'success':
        return AppColors.success;
      case 'pending':
        return AppColors.warning;
      case 'failed':
      case 'cancelled':
        return AppColors.error;
      default:
        return AppColors.lightTextSecondary;
    }
  }

  String _formatDateTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inDays == 0) {
      final hour = dateTime.hour.toString().padLeft(2, '0');
      final minute = dateTime.minute.toString().padLeft(2, '0');
      return 'Aujourd\'hui à $hour:$minute';
    } else if (difference.inDays == 1) {
      final hour = dateTime.hour.toString().padLeft(2, '0');
      final minute = dateTime.minute.toString().padLeft(2, '0');
      return 'Hier à $hour:$minute';
    } else if (difference.inDays < 7) {
      return 'Il y a ${difference.inDays} jours';
    } else {
      final day = dateTime.day.toString().padLeft(2, '0');
      final month = dateTime.month.toString().padLeft(2, '0');
      return '$day/$month/${dateTime.year}';
    }
  }
}
