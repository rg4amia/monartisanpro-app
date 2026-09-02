import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/devis_model.dart';

class ReviewStatusCard extends StatelessWidget {
  final DevisModel devis;
  final bool hasPendingPayment;

  const ReviewStatusCard({
    super.key,
    required this.devis,
    required this.hasPendingPayment,
  });

  @override
  Widget build(BuildContext context) {
    final config = _getStatusConfig(devis.statut);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: config['bg'] as Color,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: config['borderColor'] as Color),
      ),
      child: Row(
        children: [
          Icon(
            config['icon'] as IconData,
            color: config['color'] as Color,
            size: 24,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  config['title'] as String,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: config['color'] as Color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  config['subtitle'] as String,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _getStatusConfig(String statut) {
    if (hasPendingPayment) {
      return {
        'icon': Icons.payment,
        'title': 'Paiement de l\'acompte en attente',
        'subtitle':
            'Validez le paiement sur votre Mobile Money puis revenez vérifier le statut.',
        'color': AppColors.primary,
        'bg': AppColors.secondary,
        'borderColor': AppColors.primary.withValues(alpha: 0.2),
      };
    }

    switch (statut) {
      case 'soumis':
        return {
          'icon': Icons.schedule,
          'title': 'Devis en attente de votre validation',
          'subtitle': 'Consultez les détails et décidez d\'accepter ou refuser',
          'color': AppColors.accent,
          'bg': AppColors.artisanSoft,
          'borderColor': AppColors.accent.withValues(alpha: 0.2),
        };
      case 'accepte':
        return {
          'icon': Icons.check_circle,
          'title': 'Mission financée',
          'subtitle': 'Le devis a été accepté et le séquestre est en place',
          'color': AppColors.success,
          'bg': AppColors.supplierSoft,
          'borderColor': AppColors.success.withValues(alpha: 0.2),
        };
      case 'refuse':
        return {
          'icon': Icons.cancel,
          'title': 'Devis refusé',
          'subtitle': 'Vous avez refusé ce devis',
          'color': AppColors.danger,
          'bg': AppColors.dangerSoft,
          'borderColor': AppColors.danger.withValues(alpha: 0.2),
        };
      default:
        return {
          'icon': Icons.info,
          'title': 'Devis en brouillon',
          'subtitle': 'L\'artisan prépare le devis',
          'color': AppColors.textSecondary,
          'bg': AppColors.border,
          'borderColor': AppColors.textSecondary.withValues(alpha: 0.2),
        };
    }
  }
}
