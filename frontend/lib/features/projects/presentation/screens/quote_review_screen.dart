import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/models/project_model.dart';
import 'payment_screen.dart';

class QuoteReviewScreen extends StatefulWidget {
  final Quote quote;
  final int projectId;

  const QuoteReviewScreen({
    super.key,
    required this.quote,
    required this.projectId,
  });

  @override
  State<QuoteReviewScreen> createState() => _QuoteReviewScreenState();
}

class _QuoteReviewScreenState extends State<QuoteReviewScreen> {
  final _projectController = Get.find<ProjectController>();
  final _rejectionController = TextEditingController();

  @override
  void dispose() {
    _rejectionController.dispose();
    super.dispose();
  }

  Future<void> _acceptQuote() async {
    final confirm = await Get.dialog<bool>(
      AlertDialog(
        title: const Text('Accepter le devis'),
        content: const Text(
          'En acceptant ce devis, vous vous engagez à procéder au paiement. Voulez-vous continuer?',
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () => Get.back(result: true),
            child: const Text('Accepter'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      final success = await _projectController.acceptQuote(widget.quote.id);
      if (success) {
        // Navigate to payment screen
        final paymentResult = await Get.to(() => PaymentScreen(
              projectId: widget.projectId,
              quoteId: widget.quote.id,
              amount: widget.quote.totalAmount,
            ));

        if (paymentResult == true) {
          // Payment successful
          Get.back(result: true);
          Get.back(result: true); // Go back to project details
        } else {
          // Payment failed or cancelled
          Get.snackbar(
            'Paiement non effectué',
            'Le devis a été accepté mais le paiement n\'a pas été complété',
            backgroundColor: AppColors.warning,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
          );
        }
      }
    }
  }

  Future<void> _rejectQuote() async {
    await Get.dialog(
      AlertDialog(
        title: const Text('Rejeter le devis'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Pourquoi rejetez-vous ce devis?'),
            const SizedBox(height: Spacing.md),
            TextField(
              controller: _rejectionController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Raison du rejet (optionnel)',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () async {
              Get.back();
              final success = await _projectController.rejectQuote(
                widget.quote.id,
                _rejectionController.text.isNotEmpty
                    ? _rejectionController.text
                    : null,
              );
              if (success) {
                Get.back(result: true);
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
            ),
            child: const Text('Rejeter'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final daysUntilExpiry = widget.quote.validUntil.difference(DateTime.now()).inDays;
    final isExpired = daysUntilExpiry < 0;

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          // App Bar
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            flexibleSpace: FlexibleSpaceBar(
              title: const Text(
                'Détails du devis',
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
                child: Center(
                  child: Icon(
                    Icons.receipt_long,
                    size: 80,
                    color: Colors.white.withValues(alpha: 0.3),
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
                  // Artisan Info
                  if (widget.quote.artisan != null) ...[
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(Spacing.base),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 24,
                              backgroundImage: widget.quote.artisan!.avatar != null
                                  ? NetworkImage(widget.quote.artisan!.avatar!)
                                  : null,
                              child: widget.quote.artisan!.avatar == null
                                  ? const Icon(Icons.person)
                                  : null,
                            ),
                            const SizedBox(width: Spacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    widget.quote.artisan!.name,
                                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                  if (widget.quote.artisan!.phone != null)
                                    Text(
                                      widget.quote.artisan!.phone!,
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                            color: AppColors.lightTextSecondary,
                                          ),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Total Amount Card
                  Container(
                    padding: const EdgeInsets.all(Spacing.lg),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: AppColors.primaryGradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    ),
                    child: Column(
                      children: [
                        Text(
                          'Montant total',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Colors.white.withValues(alpha: 0.9),
                              ),
                        ),
                        const SizedBox(height: Spacing.sm),
                        Text(
                          widget.quote.formattedTotal,
                          style: Theme.of(context).textTheme.displaySmall?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: Spacing.xl),

                  // Breakdown Pie Chart
                  Text(
                    'Répartition',
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
                                  value: widget.quote.materialPercentage,
                                  title: '${widget.quote.materialPercentage.toStringAsFixed(0)}%',
                                  color: AppColors.warning,
                                  radius: 80,
                                  titleStyle: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.white,
                                  ),
                                ),
                                PieChartSectionData(
                                  value: widget.quote.laborPercentage,
                                  title: '${widget.quote.laborPercentage.toStringAsFixed(0)}%',
                                  color: AppColors.info,
                                  radius: 80,
                                  titleStyle: const TextStyle(
                                    fontSize: 14,
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
                        const SizedBox(width: Spacing.lg),
                        Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _buildLegendItem(
                              context,
                              'Matériaux',
                              widget.quote.formattedMaterial,
                              AppColors.warning,
                            ),
                            const SizedBox(height: Spacing.md),
                            _buildLegendItem(
                              context,
                              'Main d\'œuvre',
                              widget.quote.formattedLabor,
                              AppColors.info,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: Spacing.xl),

                  // Material Items
                  if (widget.quote.items != null &&
                      widget.quote.items!.any((item) => item.isMaterial)) ...[
                    _buildSectionHeader(context, 'Matériaux', Icons.inventory_2_outlined),
                    const SizedBox(height: Spacing.md),
                    ...widget.quote.items!
                        .where((item) => item.isMaterial)
                        .map((item) => _buildQuoteItemCard(context, item)),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Labor Items
                  if (widget.quote.items != null &&
                      widget.quote.items!.any((item) => item.isLabor)) ...[
                    _buildSectionHeader(context, 'Main d\'œuvre', Icons.construction_outlined),
                    const SizedBox(height: Spacing.md),
                    ...widget.quote.items!
                        .where((item) => item.isLabor)
                        .map((item) => _buildQuoteItemCard(context, item)),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Validity Info
                  Container(
                    padding: const EdgeInsets.all(Spacing.base),
                    decoration: BoxDecoration(
                      color: isExpired
                          ? AppColors.error.withValues(alpha: 0.1)
                          : daysUntilExpiry <= 3
                              ? AppColors.warning.withValues(alpha: 0.1)
                              : AppColors.lightBackground,
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      border: Border.all(
                        color: isExpired
                            ? AppColors.error
                            : daysUntilExpiry <= 3
                                ? AppColors.warning
                                : AppColors.lightTextTertiary,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isExpired ? Icons.error_outline : Icons.access_time,
                          color: isExpired
                              ? AppColors.error
                              : daysUntilExpiry <= 3
                                  ? AppColors.warning
                                  : AppColors.lightTextSecondary,
                        ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: Text(
                            isExpired
                                ? 'Ce devis a expiré le ${_formatDate(widget.quote.validUntil)}'
                                : daysUntilExpiry == 0
                                    ? 'Ce devis expire aujourd\'hui'
                                    : 'Valide encore $daysUntilExpiry jour${daysUntilExpiry > 1 ? 's' : ''}',
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: isExpired
                                      ? AppColors.error
                                      : daysUntilExpiry <= 3
                                          ? AppColors.warning
                                          : AppColors.lightTextPrimary,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  // Notes
                  if (widget.quote.notes != null && widget.quote.notes!.isNotEmpty) ...[
                    const SizedBox(height: Spacing.xl),
                    _buildSectionHeader(context, 'Notes', Icons.notes),
                    const SizedBox(height: Spacing.md),
                    Container(
                      padding: const EdgeInsets.all(Spacing.base),
                      decoration: BoxDecoration(
                        color: AppColors.lightBackground,
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                      child: Text(
                        widget.quote.notes!,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                  ],

                  const SizedBox(height: Spacing.xxxl),

                  // Action Buttons
                  if (widget.quote.isSent && !isExpired) ...[
                    ElevatedButton.icon(
                      onPressed: _acceptQuote,
                      icon: const Icon(Icons.check_circle_outline),
                      label: const Text('Accepter le devis'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.success,
                        padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                      ),
                    ),
                    const SizedBox(height: Spacing.md),
                    OutlinedButton.icon(
                      onPressed: _rejectQuote,
                      icon: const Icon(Icons.cancel_outlined),
                      label: const Text('Rejeter le devis'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.error,
                        side: BorderSide(color: AppColors.error),
                        padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                      ),
                    ),
                  ],

                  if (isExpired)
                    Container(
                      padding: const EdgeInsets.all(Spacing.base),
                      decoration: BoxDecoration(
                        color: AppColors.error.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                      child: Text(
                        'Ce devis a expiré. Contactez l\'artisan pour un nouveau devis.',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: AppColors.error,
                            ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context, String title, IconData icon) {
    return Row(
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: Spacing.sm),
        Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
      ],
    );
  }

  Widget _buildLegendItem(
    BuildContext context,
    String label,
    String value,
    Color color,
  ) {
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
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.lightTextSecondary,
                  ),
            ),
            Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildQuoteItemCard(BuildContext context, QuoteItem item) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.base),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.description,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  const SizedBox(height: Spacing.xs),
                  Text(
                    '${item.quantity} ${item.unit ?? 'unité${item.quantity > 1 ? 's' : ''}'} × ${item.unitPrice.toStringAsFixed(0)} FCFA',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                  ),
                ],
              ),
            ),
            Text(
              '${item.total.toStringAsFixed(0)} FCFA',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: Theme.of(context).colorScheme.primary,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    final months = [
      'jan', 'fév', 'mar', 'avr', 'mai', 'juin',
      'juil', 'aoû', 'sep', 'oct', 'nov', 'déc'
    ];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }
}
