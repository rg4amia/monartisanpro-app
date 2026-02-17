import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

enum StatusType { project, quote, payment, token, milestone }

class StatusBadge extends StatelessWidget {
  final String status;
  final StatusType type;

  const StatusBadge({
    super.key,
    required this.status,
    required this.type,
  });

  @override
  Widget build(BuildContext context) {
    final config = _getStatusConfig();

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: 4,
      ),
      decoration: BoxDecoration(
        color: config.color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
        border: Border.all(
          color: config.color.withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            config.icon,
            size: 14,
            color: config.color,
          ),
          const SizedBox(width: 4),
          Text(
            config.label,
            style: TextStyle(
              color: config.color,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  ({Color color, IconData icon, String label}) _getStatusConfig() {
    switch (type) {
      case StatusType.project:
        return _getProjectStatusConfig();
      case StatusType.quote:
        return _getQuoteStatusConfig();
      case StatusType.payment:
        return _getPaymentStatusConfig();
      case StatusType.token:
        return _getTokenStatusConfig();
      case StatusType.milestone:
        return _getMilestoneStatusConfig();
    }
  }

  ({Color color, IconData icon, String label}) _getProjectStatusConfig() {
    switch (status.toLowerCase()) {
      case 'pending':
        return (
          color: AppColors.warning,
          icon: Icons.pending,
          label: 'En attente'
        );
      case 'awaiting_quotes':
        return (
          color: AppColors.info,
          icon: Icons.description,
          label: 'Attente devis'
        );
      case 'quote_received':
        return (
          color: AppColors.lightAccentPrimary,
          icon: Icons.check_circle_outline,
          label: 'Devis reçu'
        );
      case 'quote_accepted':
        return (
          color: AppColors.success,
          icon: Icons.check_circle,
          label: 'Devis accepté'
        );
      case 'payment_pending':
        return (
          color: AppColors.warning,
          icon: Icons.payment,
          label: 'Paiement en attente'
        );
      case 'in_progress':
        return (
          color: AppColors.lightAccentPrimary,
          icon: Icons.autorenew,
          label: 'En cours'
        );
      case 'completed':
        return (
          color: AppColors.success,
          icon: Icons.check_circle,
          label: 'Terminé'
        );
      case 'cancelled':
        return (color: AppColors.error, icon: Icons.cancel, label: 'Annulé');
      case 'disputed':
        return (
          color: AppColors.error,
          icon: Icons.warning,
          label: 'En litige'
        );
      default:
        return (color: AppColors.info, icon: Icons.info, label: status);
    }
  }

  ({Color color, IconData icon, String label}) _getQuoteStatusConfig() {
    switch (status.toLowerCase()) {
      case 'draft':
        return (color: AppColors.lightTextSecondary, icon: Icons.edit, label: 'Brouillon');
      case 'sent':
        return (color: AppColors.info, icon: Icons.send, label: 'Envoyé');
      case 'accepted':
        return (color: AppColors.success, icon: Icons.check_circle, label: 'Accepté');
      case 'rejected':
        return (color: AppColors.error, icon: Icons.cancel, label: 'Refusé');
      case 'expired':
        return (color: AppColors.warning, icon: Icons.access_time, label: 'Expiré');
      default:
        return (color: AppColors.info, icon: Icons.info, label: status);
    }
  }

  ({Color color, IconData icon, String label}) _getPaymentStatusConfig() {
    switch (status.toLowerCase()) {
      case 'pending':
        return (color: AppColors.warning, icon: Icons.pending, label: 'En attente');
      case 'processing':
        return (color: AppColors.info, icon: Icons.sync, label: 'Traitement');
      case 'completed':
        return (color: AppColors.success, icon: Icons.check_circle, label: 'Complété');
      case 'failed':
        return (color: AppColors.error, icon: Icons.error, label: 'Échoué');
      case 'refunded':
        return (color: AppColors.warning, icon: Icons.undo, label: 'Remboursé');
      default:
        return (color: AppColors.info, icon: Icons.info, label: status);
    }
  }

  ({Color color, IconData icon, String label}) _getTokenStatusConfig() {
    switch (status.toLowerCase()) {
      case 'active':
        return (color: AppColors.success, icon: Icons.check_circle, label: 'Actif');
      case 'partially_used':
        return (color: AppColors.info, icon: Icons.pie_chart, label: 'Partiellement utilisé');
      case 'fully_used':
        return (color: AppColors.lightTextSecondary, icon: Icons.check_circle_outline, label: 'Utilisé');
      case 'expired':
        return (color: AppColors.error, icon: Icons.access_time, label: 'Expiré');
      default:
        return (color: AppColors.info, icon: Icons.info, label: status);
    }
  }

  ({Color color, IconData icon, String label}) _getMilestoneStatusConfig() {
    switch (status.toLowerCase()) {
      case 'pending':
        return (color: AppColors.warning, icon: Icons.pending, label: 'En attente');
      case 'in_progress':
        return (color: AppColors.info, icon: Icons.autorenew, label: 'En cours');
      case 'validation_pending':
        return (color: AppColors.warning, icon: Icons.check, label: 'À valider');
      case 'validated':
        return (color: AppColors.success, icon: Icons.check_circle, label: 'Validé');
      case 'paid':
        return (color: AppColors.success, icon: Icons.payments, label: 'Payé');
      default:
        return (color: AppColors.info, icon: Icons.info, label: status);
    }
  }
}
