import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/delivery_fare_model.dart';

/// Course livrée à régler par le client (modèle « à la Yango ») : le montant
/// final (course + bonus d'attente du livreur) n'est connu qu'à la livraison.
class DeliveryFarePaymentCard extends StatelessWidget {
  const DeliveryFarePaymentCard({
    super.key,
    required this.fare,
    required this.onPay,
    this.busy = false,
  });

  final DeliveryFare fare;
  final void Function(String provider) onPay;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.clientSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.client.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Course de livraison à régler',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            Formatters.fcfa(fare.due),
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: AppColors.textPrimary,
            ),
          ),
          if (fare.waitingBonus > 0)
            Text(
              'Dont ${Formatters.fcfa(fare.waitingBonus)} de temps d\'attente '
              '(${fare.waitingMinutes} min)',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
          const SizedBox(height: 4),
          const Text(
            'Le livreur est payé dès votre règlement.',
            style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 12),
          if (busy)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(6),
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          else
            Row(
              children: [
                Expanded(
                  child: FilledButton(
                    onPressed: () => onPay('wave'),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF00A3FF),
                    ),
                    child: const Text('Payer avec Wave'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () => onPay('orange_money'),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFFFF7900),
                    ),
                    child: const Text('Orange Money'),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}
