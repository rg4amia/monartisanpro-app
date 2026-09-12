import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

/// Badge de statut d'une commande e-commerce.
///
/// Traduit les statuts techniques du backend en libellés compréhensibles, avec
/// une couleur par étape. Partagé par l'espace fournisseur et l'espace client
/// pour que les deux parlent de la même chose au même moment.
class OrderStatusBadge extends StatelessWidget {
  const OrderStatusBadge({super.key, required this.status});

  final String status;

  static ({String label, Color color, Color background}) describe(
    String status,
  ) {
    switch (status) {
      case 'paid':
        return (
          label: 'À préparer',
          color: AppColors.accent,
          background: AppColors.artisanSoft,
        );
      case 'prepared':
        return (
          label: 'Prête',
          color: AppColors.success,
          background: AppColors.supplierSoft,
        );
      case 'searching_driver':
        return (
          label: 'Recherche livreur',
          color: AppColors.info,
          background: AppColors.clientSoft,
        );
      case 'driver_assigned':
        return (
          label: 'Livreur assigné',
          color: AppColors.driver,
          background: AppColors.driverSoft,
        );
      case 'driver_picked_up':
        return (
          label: 'En route',
          color: AppColors.client,
          background: AppColors.clientSoft,
        );
      case 'delivered':
        return (
          label: 'Livrée',
          color: AppColors.success,
          background: AppColors.supplierSoft,
        );
      case 'disputed':
        return (
          label: 'Litige',
          color: AppColors.danger,
          background: AppColors.dangerSoft,
        );
      case 'cancelled':
        return (
          label: 'Annulée',
          color: AppColors.textSecondary,
          background: AppColors.secondary,
        );
      default:
        return (
          label: status,
          color: AppColors.textSecondary,
          background: AppColors.secondary,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final tone = describe(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: tone.background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        tone.label,
        style: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: tone.color,
        ),
      ),
    );
  }
}
