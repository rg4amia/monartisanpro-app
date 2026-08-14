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
      appBar: AppBar(
        title: const Text('Confirmation de Transaction'),
        centerTitle: true,
      ),
      body: result == null
          ? const Center(child: Text('Aucune donnée'))
          : SingleChildScrollView(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Success icon with background
                    Center(
                      child: Container(
                        width: 100,
                        height: 100,
                        decoration: BoxDecoration(
                          color: AppColors.success.withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                        ),
                        child: Center(
                          child: Container(
                            width: 64,
                            height: 64,
                            decoration: const BoxDecoration(
                              color: AppColors.success,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.check,
                              color: Colors.white,
                              size: 40,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Success message with checkmark
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: AppColors.success,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Icon(
                            Icons.check,
                            color: Colors.white,
                            size: 16,
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'Transaction Autorisée',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: AppColors.success,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Subtitle
                    Text(
                      'Les matériaux ont été approuvés. Le paiement\nest en cours de traitement.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 14,
                        color: AppColors.textSecondary,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 32),

                    // Material image placeholder
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        height: 180,
                        decoration: BoxDecoration(
                          color: Colors.grey[300],
                          image: const DecorationImage(
                            image: AssetImage(
                                'assets/images/materials_placeholder.jpg'),
                            fit: BoxFit.cover,
                          ),
                        ),
                        child: Container(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Colors.transparent,
                                Colors.black.withValues(alpha: 0.3),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Details card
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 10,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Détails de l\'autorisation',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 20),
                          _DetailRow(
                            label: 'ID Mission',
                            value: result['mission']?.toString() ?? '#MS-8829',
                          ),
                          const SizedBox(height: 16),
                          _DetailRow(
                            label: 'Artisan',
                            value: result['artisan']?['name']?.toString() ??
                                result['artisan']?.toString() ??
                                'Mamadou Diop',
                          ),
                          const SizedBox(height: 16),
                          _DetailRow(
                            label: 'Montant Total',
                            value: result['montant'] != null
                                ? Formatters.fcfa(
                                    (result['montant'] as num).toInt())
                                : '45.000 FCFA',
                            valueColor: AppColors.primary,
                            isBold: true,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Payment guarantee info
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(6),
                            decoration: const BoxDecoration(
                              color: AppColors.primary,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.info_outline,
                              color: Colors.white,
                              size: 16,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Garantie de paiement',
                                  style: TextStyle(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                RichText(
                                  text: const TextSpan(
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                      height: 1.4,
                                    ),
                                    children: [
                                      TextSpan(
                                          text:
                                              'Virement J+1 garanti vers votre compte '),
                                      TextSpan(
                                        text: 'Wave',
                                        style: TextStyle(
                                            fontWeight: FontWeight.w600),
                                      ),
                                      TextSpan(text: ' ou '),
                                      TextSpan(
                                        text: 'Orange Money',
                                        style: TextStyle(
                                            fontWeight: FontWeight.w600),
                                      ),
                                      TextSpan(text: '.'),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 32),

                    // Share button
                    ElevatedButton.icon(
                      onPressed: () {
                        Get.snackbar(
                          'Impression / Partage',
                          'Génération du reçu de transaction au format PDF...',
                          snackPosition: SnackPosition.TOP,
                        );
                      },
                      icon: const Icon(Icons.share_outlined, size: 20),
                      label: const Text(
                        'Imprimer / Partager le reçu',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Return to dashboard button
                    OutlinedButton.icon(
                      onPressed: () => Get.offAllNamed(Routes.mainTab),
                      icon: const Icon(Icons.dashboard_outlined, size: 20),
                      label: const Text(
                        'Retour au Tableau de Bord',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.textPrimary,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        side: BorderSide(color: AppColors.border),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  final bool isBold;

  const _DetailRow({
    required this.label,
    required this.value,
    this.valueColor,
    this.isBold = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: AppColors.textSecondary,
            fontSize: 14,
          ),
        ),
        const SizedBox(width: 16),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: valueColor ?? AppColors.textPrimary,
              fontSize: isBold ? 16 : 14,
            ),
          ),
        ),
      ],
    );
  }
}
