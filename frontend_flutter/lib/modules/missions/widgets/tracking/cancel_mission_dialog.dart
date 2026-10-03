import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/cancellation_preview.dart';

/// Confirmation de l'annulation d'une mission. Les montants affichés sont
/// ceux que le serveur a calculés ; la fenêtre renvoie le motif saisi (chaîne
/// vide s'il n'y en a pas) quand le client confirme, `null` sinon.
class CancelMissionDialog extends StatefulWidget {
  const CancelMissionDialog({required this.preview, super.key});

  final CancellationPreview preview;

  static Future<String?> show(CancellationPreview preview) {
    return Get.dialog<String>(CancelMissionDialog(preview: preview));
  }

  @override
  State<CancelMissionDialog> createState() => _CancelMissionDialogState();
}

class _CancelMissionDialogState extends State<CancelMissionDialog> {
  final _reason = TextEditingController();

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final preview = widget.preview;

    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: Text(
        preview.allowed ? 'Annuler la mission ?' : 'Annulation impossible',
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!preview.allowed)
              Text(
                preview.reason ??
                    'Cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.',
              )
            else if (preview.funded) ...[
              const Text(
                'Le chantier n\'a pas commencé : le montant en séquestre vous est rendu, après une pénalité d\'annulation.',
              ),
              const SizedBox(height: 14),
              _AmountLine(
                label: 'Montant en séquestre',
                amount: preview.escrow,
              ),
              _AmountLine(
                label: 'Pénalité (${_rate(preview.penaltyRate)} %)',
                amount: -preview.penalty,
                color: AppColors.danger,
              ),
              const Divider(height: 18),
              _AmountLine(
                label: 'Remboursé',
                amount: preview.refund,
                bold: true,
                color: AppColors.success,
              ),
              const SizedBox(height: 8),
              const Text(
                'Le remboursement est versé sur le moyen de paiement utilisé pour la mission.',
                style: TextStyle(fontSize: 12),
              ),
            ] else
              const Text(
                'Aucun paiement n\'a été effectué : l\'annulation est sans frais. L\'artisan sollicité sera prévenu.',
              ),
            if (preview.allowed) ...[
              const SizedBox(height: 14),
              TextField(
                controller: _reason,
                maxLength: 255,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Motif (facultatif)',
                  border: OutlineInputBorder(),
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Get.back<String>(),
          child: Text(preview.allowed ? 'Garder la mission' : 'Fermer'),
        ),
        if (preview.allowed)
          ElevatedButton(
            onPressed: () => Get.back<String>(result: _reason.text.trim()),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger,
              foregroundColor: Colors.white,
            ),
            child: const Text('Annuler la mission'),
          ),
      ],
    );
  }

  /// 7.0 → « 7 », 12.5 → « 12,5 ».
  static String _rate(double rate) {
    final text = rate == rate.roundToDouble()
        ? rate.toStringAsFixed(0)
        : rate.toString();
    return text.replaceAll('.', ',');
  }
}

class _AmountLine extends StatelessWidget {
  const _AmountLine({
    required this.label,
    required this.amount,
    this.color,
    this.bold = false,
  });

  final String label;
  final int amount;
  final Color? color;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
      color: color,
    );

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(child: Text(label, style: style)),
          Text(
            '${amount < 0 ? '− ' : ''}${Formatters.fcfa(amount.abs())}',
            style: style,
          ),
        ],
      ),
    );
  }
}
