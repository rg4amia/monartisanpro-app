import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/payout_model.dart';

/// Virements Mobile Money non aboutis de l'utilisateur (Chantier 10).
///
/// Un virement échoué ne débite pas le portefeuille : les fonds restent
/// disponibles, le virement est relancé automatiquement, et l'utilisateur
/// peut le relancer lui-même après avoir corrigé son numéro de paiement.
class PendingPayoutsSection extends StatelessWidget {
  const PendingPayoutsSection({
    super.key,
    required this.payouts,
    required this.onRetry,
    this.retryingPayoutId,
  });

  final List<PayoutModel> payouts;
  final Future<void> Function(PayoutModel payout) onRetry;
  final int? retryingPayoutId;

  @override
  Widget build(BuildContext context) {
    if (payouts.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Virements en attente',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Ces montants n\'ont pas encore été reçus sur votre Mobile Money. '
          'Ils restent sur votre portefeuille et sont relancés automatiquement.',
          style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
        ),
        const SizedBox(height: 12),
        for (final payout in payouts)
          _PayoutCard(
            payout: payout,
            busy: retryingPayoutId == payout.id,
            onRetry: () => onRetry(payout),
          ),
      ],
    );
  }
}

class _PayoutCard extends StatelessWidget {
  const _PayoutCard({
    required this.payout,
    required this.busy,
    required this.onRetry,
  });

  final PayoutModel payout;
  final bool busy;
  final VoidCallback onRetry;

  String _date(DateTime? value) {
    if (value == null) return '';
    try {
      return DateFormat("dd MMM 'à' HH:mm", 'fr_FR').format(value.toLocal());
    } catch (_) {
      return DateFormat('dd/MM HH:mm').format(value.toLocal());
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = payout.isFailed ? AppColors.danger : Colors.orange.shade800;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
          title: Text(
            payout.contextLabel,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${Formatters.fcfa(payout.montant)} · ${payout.statutLabel}',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: color,
                  ),
                ),
                if (payout.lastError != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    payout.lastError!,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                if (payout.nextRetryAt != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    'Prochaine relance automatique : ${_date(payout.nextRetryAt)}',
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ],
            ),
          ),
          children: [
            if (payout.phone != null)
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Numéro de réception : ${payout.phone}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            const SizedBox(height: 8),
            const Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Historique',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
            ),
            const SizedBox(height: 6),
            for (final event in payout.events)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 92,
                      child: Text(
                        _date(event.createdAt),
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        event.message != null
                            ? '${event.actionLabel} — ${event.message}'
                            : event.actionLabel,
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (payout.canRetry) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: busy ? null : onRetry,
                  icon: busy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded, size: 18),
                  label: Text(busy ? 'Relance…' : 'Relancer le virement'),
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Numéro erroné ? Corrigez-le dans Paramètres › Reversement Mobile Money avant de relancer.',
                style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
