import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// Moyen de remboursement choisi par le client : opérateur et numéro.
typedef RefundDestination = ({String provider, String phone});

/// Opérateurs acceptés par le serveur pour un remboursement après litige
/// (`MobileMoneyPayoutService::REFUND_PROVIDERS`).
const Map<String, String> kRefundProviders = {
  'wave': 'Wave',
  'orange_money': 'Orange Money',
};

/// Ramène un numéro saisi au format du serveur, `+225` suivi de 10 chiffres
/// (espaces, tirets et indicatif `225` tolérés). Renvoie `null` si le numéro
/// ne peut pas être un numéro ivoirien.
String? normalizeIvorianPhone(String input) {
  final digits = input.replaceAll(RegExp(r'\D'), '');
  final local = digits.length == 13 && digits.startsWith('225')
      ? digits.substring(3)
      : digits;

  return local.length == 10 ? '+225$local' : null;
}

/// Demande au client l'opérateur et le numéro qui recevront son
/// remboursement. Renvoie `null` s'il annule.
Future<RefundDestination?> showRefundDestinationDialog(
  BuildContext context, {
  String? initialProvider,
  String? initialPhone,
}) {
  return showDialog<RefundDestination>(
    context: context,
    builder: (_) => RefundDestinationDialog(
      initialProvider: initialProvider,
      initialPhone: initialPhone,
    ),
  );
}

class RefundDestinationDialog extends StatefulWidget {
  const RefundDestinationDialog({
    super.key,
    this.initialProvider,
    this.initialPhone,
  });

  final String? initialProvider;
  final String? initialPhone;

  @override
  State<RefundDestinationDialog> createState() =>
      _RefundDestinationDialogState();
}

class _RefundDestinationDialogState extends State<RefundDestinationDialog> {
  late String _provider;
  late final TextEditingController _phone;
  String? _error;

  @override
  void initState() {
    super.initState();
    _provider = kRefundProviders.containsKey(widget.initialProvider)
        ? widget.initialProvider!
        : 'wave';
    _phone = TextEditingController(text: widget.initialPhone ?? '');
  }

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  void _submit() {
    final phone = normalizeIvorianPhone(_phone.text);
    if (phone == null) {
      setState(() => _error = 'Saisissez un numéro à 10 chiffres.');
      return;
    }
    Navigator.of(context).pop((provider: _provider, phone: phone));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      title: const Text(
        'Moyen de remboursement',
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Indiquez le compte Mobile Money sur lequel vous souhaitez être remboursé.',
              style: TextStyle(
                fontSize: 13,
                height: 1.4,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              children: [
                for (final entry in kRefundProviders.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _provider == entry.key,
                    onSelected: (_) => setState(() => _provider = entry.key),
                  ),
              ],
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Numéro Mobile Money',
                hintText: 'Ex : 0701020304',
                errorText: _error,
                prefixIcon: const Icon(Icons.phone_android_rounded, size: 20),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              onChanged: (_) {
                if (_error != null) setState(() => _error = null);
              },
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: _submit,
          child: const Text('Enregistrer'),
        ),
      ],
    );
  }
}
