import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/delivery_fare_model.dart';

/// Révélation du montant de la course au livreur, à la livraison (modèle
/// « à la Yango ») : tarif, bonus d'attente, et état du paiement du client.
class DeliveryFareDialog extends StatelessWidget {
  const DeliveryFareDialog({super.key, required this.fare});

  final DeliveryFare fare;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Row(
        children: [
          Icon(Icons.check_circle_rounded, color: AppColors.success),
          SizedBox(width: 10),
          Expanded(child: Text('Livraison confirmée')),
        ],
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Montant de la course',
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 4),
          Text(
            Formatters.fcfa(fare.total),
            style: const TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w900,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          _line('Course', fare.base),
          if (fare.waitingBonus > 0)
            _line(
              'Bonus d\'attente (${fare.waitingMinutes} min)',
              fare.waitingBonus,
            ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: (fare.isPaid ? AppColors.success : AppColors.warning)
                  .withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              fare.isPaid
                  ? 'Vos gains ont été crédités sur votre portefeuille (net de la commission ProsArtisan).'
                  : 'Le client a reçu la demande de paiement. Vos gains seront crédités dès son règlement.',
              style: const TextStyle(fontSize: 12.5, height: 1.35),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Compris'),
        ),
      ],
    );
  }

  Widget _line(String label, int amount) => Padding(
        padding: const EdgeInsets.only(bottom: 4),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
            Text(
              Formatters.fcfa(amount),
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
      );
}
