import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/token_controller.dart';
import '../../../../shared/models/escrow_model.dart';

class RedemptionHistoryScreen extends StatefulWidget {
  final int tokenId;
  final MaterialToken token;

  const RedemptionHistoryScreen({
    super.key,
    required this.tokenId,
    required this.token,
  });

  @override
  State<RedemptionHistoryScreen> createState() => _RedemptionHistoryScreenState();
}

class _RedemptionHistoryScreenState extends State<RedemptionHistoryScreen> {
  final _tokenController = Get.find<TokenController>();
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    setState(() => _isLoading = true);
    await _tokenController.fetchRedemptionHistory(widget.tokenId);
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Historique des utilisations'),
      ),
      body: Obx(() {
        final redemptions = _tokenController.redemptionHistory;

        if (_isLoading) {
          return const Center(child: CircularProgressIndicator());
        }

        return RefreshIndicator(
          onRefresh: _loadHistory,
          child: CustomScrollView(
            slivers: [
              // Token Summary
              SliverToBoxAdapter(
                child: Container(
                  margin: const EdgeInsets.all(Spacing.screenPadding),
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
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Jeton',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Colors.white.withValues(alpha: 0.9),
                                    ),
                              ),
                              Text(
                                widget.token.formattedCode,
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      color: Colors.white,
                                      fontFamily: 'monospace',
                                      fontWeight: FontWeight.bold,
                                    ),
                              ),
                            ],
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: Spacing.md,
                              vertical: Spacing.sm,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(Spacing.radiusMd),
                            ),
                            child: Column(
                              children: [
                                Text(
                                  'Solde',
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: Colors.white.withValues(alpha: 0.9),
                                      ),
                                ),
                                Text(
                                  widget.token.formattedRemaining,
                                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold,
                                      ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.md),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(Spacing.radiusSm),
                        child: LinearProgressIndicator(
                          value: widget.token.percentageUsed / 100,
                          backgroundColor: Colors.white.withValues(alpha: 0.3),
                          valueColor: AlwaysStoppedAnimation<Color>(
                            Colors.white.withValues(alpha: 0.9),
                          ),
                          minHeight: 6,
                        ),
                      ),
                      const SizedBox(height: Spacing.xs),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            '${widget.token.percentageUsed.toStringAsFixed(0)}% utilisé',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.9),
                                ),
                          ),
                          Text(
                            'Total: ${widget.token.formattedTotal}',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.9),
                                ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              // Stats Row
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: Spacing.screenPadding),
                  child: Row(
                    children: [
                      Expanded(
                        child: _buildStatCard(
                          context,
                          '${redemptions.length}',
                          'Utilisation${redemptions.length > 1 ? 's' : ''}',
                          Icons.shopping_cart_outlined,
                          AppColors.info,
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: _buildStatCard(
                          context,
                          widget.token.formattedTotal,
                          'Montant total',
                          Icons.payments_outlined,
                          AppColors.warning,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.screenPadding),
                  child: Text(
                    'Historique',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ),
              ),

              // Redemption List
              if (redemptions.isEmpty)
                SliverFillRemaining(
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.history_outlined,
                          size: 64,
                          color: AppColors.lightTextTertiary,
                        ),
                        const SizedBox(height: Spacing.lg),
                        Text(
                          'Aucune utilisation',
                          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                color: AppColors.lightTextSecondary,
                              ),
                        ),
                      ],
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: Spacing.screenPadding),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final redemption = redemptions[index];
                        return _buildRedemptionCard(context, redemption, index);
                      },
                      childCount: redemptions.length,
                    ),
                  ),
                ),

              const SliverToBoxAdapter(
                child: SizedBox(height: Spacing.screenPadding),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildStatCard(
    BuildContext context,
    String value,
    String label,
    IconData icon,
    Color color,
  ) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(height: Spacing.sm),
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: color,
                ),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildRedemptionCard(
    BuildContext context,
    TokenRedemption redemption,
    int index,
  ) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.base),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      '${index + 1}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: AppColors.success,
                          ),
                    ),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        redemption.formattedAmount,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      Text(
                        _formatDateTime(redemption.redeemedAt),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.lightTextSecondary,
                            ),
                      ),
                    ],
                  ),
                ),
                _buildValidationBadge(context, redemption),
              ],
            ),
            const SizedBox(height: Spacing.md),
            Divider(color: AppColors.lightTextTertiary.withValues(alpha: 0.2)),
            const SizedBox(height: Spacing.md),
            Row(
              children: [
                Icon(
                  Icons.location_on_outlined,
                  size: 16,
                  color: AppColors.lightTextSecondary,
                ),
                const SizedBox(width: Spacing.xs),
                Text(
                  'Distance: ${redemption.distanceText}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
                const Spacer(),
                Icon(
                  _getValidationIcon(redemption),
                  size: 16,
                  color: AppColors.lightTextSecondary,
                ),
                const SizedBox(width: Spacing.xs),
                Text(
                  _getValidationMethodLabel(redemption),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
              ],
            ),
            if (redemption.receiptPhoto != null) ...[
              const SizedBox(height: Spacing.md),
              Row(
                children: [
                  Icon(
                    Icons.receipt_long,
                    size: 16,
                    color: AppColors.success,
                  ),
                  const SizedBox(width: Spacing.xs),
                  Text(
                    'Facture fournie',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.success,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildValidationBadge(BuildContext context, TokenRedemption redemption) {
    Color color;
    IconData icon;
    String label;

    if (redemption.isGpsValidated) {
      color = AppColors.success;
      icon = Icons.check_circle;
      label = 'GPS';
    } else if (redemption.isOtpValidated) {
      color = AppColors.info;
      icon = Icons.sms;
      label = 'OTP';
    } else {
      color = AppColors.warning;
      icon = Icons.admin_panel_settings;
      label = 'Admin';
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.xs,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 14),
          const SizedBox(width: Spacing.xs),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  IconData _getValidationIcon(TokenRedemption redemption) {
    if (redemption.isGpsValidated) return Icons.my_location;
    if (redemption.isOtpValidated) return Icons.sms;
    return Icons.admin_panel_settings;
  }

  String _getValidationMethodLabel(TokenRedemption redemption) {
    if (redemption.isGpsValidated) return 'Validation GPS';
    if (redemption.isOtpValidated) return 'Validation OTP';
    return 'Validation Admin';
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
