import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../app/routes/app_routes.dart';

class TransactionConfirmScreen extends StatelessWidget {
  const TransactionConfirmScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final result = Get.arguments as Map<String, dynamic>?;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Confirmer la transaction')),
      body: result == null
          ? const Center(child: Text('Aucune donnée'))
          : Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  const Icon(
                    Icons.check_circle,
                    color: AppColors.success,
                    size: 72,
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'J-Code scanné avec succès',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Vérification GPS validée',
                    style: TextStyle(color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 32),

                  // Récap transaction
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        children: [
                          _ResultRow(
                            label: 'Code',
                            value: result['code']?.toString() ?? '-',
                            isBold: true,
                          ),
                          const Divider(height: 24),
                          _ResultRow(
                            label: 'Montant',
                            value: result['montant'] != null
                                ? Formatters.fcfa(
                                    (result['montant'] as num).toInt())
                                : '-',
                            valueColor: AppColors.success,
                            isBold: true,
                          ),
                          const Divider(height: 24),
                          _ResultRow(
                            label: 'Artisan',
                            value: result['artisan']?.toString() ?? '-',
                          ),
                          const Divider(height: 24),
                          _ResultRow(
                            label: 'Mission',
                            value: result['mission']?.toString() ?? '-',
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.info.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline, color: AppColors.info),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Text(
                            'Le paiement sera effectué sous 24h (J+1 garanti)',
                            style: TextStyle(color: AppColors.info),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),

                  ElevatedButton(
                    onPressed: () => Get.offAllNamed(Routes.mainTab),
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size.fromHeight(54),
                    ),
                    child: const Text('Retour à l\'accueil',
                        style: TextStyle(fontSize: 16)),
                  ),
                ],
              ),
            ),
    );
  }
}

class _ResultRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  final bool isBold;
  const _ResultRow({
    required this.label,
    required this.value,
    this.valueColor,
    this.isBold = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary)),
        Text(
          value,
          style: TextStyle(
            fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
            color: valueColor ?? AppColors.textPrimary,
            fontSize: isBold ? 16 : 14,
          ),
        ),
      ],
    );
  }
}
