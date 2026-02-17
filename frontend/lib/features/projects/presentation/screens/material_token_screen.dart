import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import 'redemption_history_screen.dart';

class MaterialTokenScreen extends StatefulWidget {
  final int projectId;

  const MaterialTokenScreen({super.key, required this.projectId});

  @override
  State<MaterialTokenScreen> createState() => _MaterialTokenScreenState();
}

class _MaterialTokenScreenState extends State<MaterialTokenScreen> {
  final _projectController = Get.find<ProjectController>();
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadToken();
  }

  Future<void> _loadToken() async {
    setState(() => _isLoading = true);
    await _projectController.fetchMaterialToken(widget.projectId);
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Jeton Matériaux'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadToken,
          ),
        ],
      ),
      body: Obx(() {
        final token = _projectController.materialToken.value;

        if (_isLoading && token == null) {
          return const Center(child: CircularProgressIndicator());
        }

        if (token == null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(Spacing.screenPadding),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.qr_code_2_outlined,
                    size: 80,
                    color: AppColors.lightTextTertiary,
                  ),
                  const SizedBox(height: Spacing.lg),
                  Text(
                    'Aucun jeton disponible',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  const SizedBox(height: Spacing.md),
                  Text(
                    'Le jeton matériaux sera généré après le paiement du projet',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          );
        }

        final isExpired = token.isExpired;
        final isFullyUsed = token.isFullyUsed;
        final isValid = token.isValid;

        return RefreshIndicator(
          onRefresh: _loadToken,
          child: ListView(
            padding: const EdgeInsets.all(Spacing.screenPadding),
            children: [
              // Status Banner
              if (!isValid)
                Container(
                  padding: const EdgeInsets.all(Spacing.base),
                  margin: const EdgeInsets.only(bottom: Spacing.xl),
                  decoration: BoxDecoration(
                    color: isExpired
                        ? AppColors.error.withValues(alpha: 0.1)
                        : AppColors.warning.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    border: Border.all(
                      color: isExpired ? AppColors.error : AppColors.warning,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        isExpired ? Icons.error_outline : Icons.info_outline,
                        color: isExpired ? AppColors.error : AppColors.warning,
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Text(
                          isExpired
                              ? 'Ce jeton a expiré'
                              : 'Ce jeton a été entièrement utilisé',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                fontWeight: FontWeight.w600,
                                color: isExpired ? AppColors.error : AppColors.warning,
                              ),
                        ),
                      ),
                    ],
                  ),
                ),

              // QR Code Card
              Card(
                elevation: 8,
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.xl),
                  child: Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(Spacing.base),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          border: Border.all(
                            color: AppColors.lightTextTertiary.withValues(alpha: 0.3),
                            width: 2,
                          ),
                        ),
                        child: QrImageView(
                          data: token.code,
                          version: QrVersions.auto,
                          size: 250,
                          backgroundColor: Colors.white,
                          foregroundColor: Colors.black,
                          gapless: true,
                          errorStateBuilder: (context, error) {
                            return Center(
                              child: Text(
                                'Erreur QR Code',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: AppColors.error,
                                    ),
                              ),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: Spacing.lg),
                      Text(
                        token.formattedCode,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontFamily: 'monospace',
                              fontWeight: FontWeight.bold,
                              letterSpacing: 2,
                            ),
                      ),
                      const SizedBox(height: Spacing.sm),
                      Text(
                        'Présentez ce code au fournisseur',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.lightTextSecondary,
                            ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: Spacing.xl),

              // Value Info
              Container(
                padding: const EdgeInsets.all(Spacing.lg),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: isValid
                        ? AppColors.primaryGradient
                        : [AppColors.lightTextTertiary, AppColors.lightTextSecondary],
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
                        Text(
                          'Valeur totale',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Colors.white.withValues(alpha: 0.9),
                              ),
                        ),
                        Text(
                          token.formattedTotal,
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w600,
                              ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Spacing.md),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Restant',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        Text(
                          token.formattedRemaining,
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Spacing.md),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(Spacing.radiusSm),
                      child: LinearProgressIndicator(
                        value: token.percentageUsed / 100,
                        backgroundColor: Colors.white.withValues(alpha: 0.3),
                        valueColor: AlwaysStoppedAnimation<Color>(
                          Colors.white.withValues(alpha: 0.9),
                        ),
                        minHeight: 8,
                      ),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        '${token.percentageUsed.toStringAsFixed(0)}% utilisé',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.9),
                            ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: Spacing.xl),

              // Expiry Info
              Container(
                padding: const EdgeInsets.all(Spacing.base),
                decoration: BoxDecoration(
                  color: isExpired
                      ? AppColors.error.withValues(alpha: 0.1)
                      : token.timeUntilExpiry.inDays < 7
                          ? AppColors.warning.withValues(alpha: 0.1)
                          : AppColors.lightBackground,
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  border: Border.all(
                    color: isExpired
                        ? AppColors.error
                        : token.timeUntilExpiry.inDays < 7
                            ? AppColors.warning
                            : AppColors.lightTextTertiary,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      isExpired
                          ? Icons.error_outline
                          : token.timeUntilExpiry.inDays < 7
                              ? Icons.warning_amber_outlined
                              : Icons.schedule_outlined,
                      color: isExpired
                          ? AppColors.error
                          : token.timeUntilExpiry.inDays < 7
                              ? AppColors.warning
                              : AppColors.lightTextSecondary,
                    ),
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isExpired
                                ? 'Expiré'
                                : 'Expire dans ${token.timeUntilExpiry.inDays} jour${token.timeUntilExpiry.inDays > 1 ? 's' : ''}',
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: isExpired
                                      ? AppColors.error
                                      : token.timeUntilExpiry.inDays < 7
                                          ? AppColors.warning
                                          : AppColors.lightTextPrimary,
                                ),
                          ),
                          Text(
                            _formatDateTime(token.expiresAt),
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
              const SizedBox(height: Spacing.xl),

              // Instructions
              Text(
                'Instructions',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: Spacing.md),
              _buildInstructionCard(
                context,
                Icons.store_outlined,
                'Chez le fournisseur',
                'Présentez ce code QR au fournisseur lors de votre achat',
              ),
              const SizedBox(height: Spacing.md),
              _buildInstructionCard(
                context,
                Icons.location_on_outlined,
                'Validation GPS',
                'Vous devez être à moins de 100m du fournisseur pour valider',
              ),
              const SizedBox(height: Spacing.md),
              _buildInstructionCard(
                context,
                Icons.receipt_long_outlined,
                'Facture requise',
                'Le fournisseur peut demander une photo de la facture',
              ),
              const SizedBox(height: Spacing.md),
              _buildInstructionCard(
                context,
                Icons.security_outlined,
                'Sécurisé',
                'Chaque utilisation est tracée et validée par GPS',
              ),
              const SizedBox(height: Spacing.xl),

              // View History Button
              OutlinedButton.icon(
                onPressed: () {
                  Get.to(() => RedemptionHistoryScreen(
                        tokenId: token.id,
                        token: token,
                      ));
                },
                icon: const Icon(Icons.history),
                label: const Text('Voir l\'historique des utilisations'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: Spacing.md),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildInstructionCard(
    BuildContext context,
    IconData icon,
    String title,
    String description,
  ) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.sm),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(Spacing.radiusSm),
            ),
            child: Icon(
              icon,
              color: Theme.of(context).colorScheme.primary,
              size: 24,
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                ),
                const SizedBox(height: Spacing.xs),
                Text(
                  description,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatDateTime(DateTime dateTime) {
    final day = dateTime.day.toString().padLeft(2, '0');
    final month = dateTime.month.toString().padLeft(2, '0');
    final year = dateTime.year;
    final hour = dateTime.hour.toString().padLeft(2, '0');
    final minute = dateTime.minute.toString().padLeft(2, '0');
    return '$day/$month/$year à $hour:$minute';
  }
}
