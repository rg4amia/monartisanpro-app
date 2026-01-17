import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Amount input field widget with validation
class AmountInputField extends StatelessWidget {
  final TextEditingController controller;
  final String labelText;
  final int maxAmount;
  final ValueChanged<String>? onChanged;

  const AmountInputField({
    Key? key,
    required this.controller,
    required this.labelText,
    required this.maxAmount,
    this.onChanged,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          controller: controller,
          keyboardType: TextInputType.number,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
            _AmountInputFormatter(),
          ],
          decoration: InputDecoration(
            labelText: labelText,
            hintText: '0',
            border: const OutlineInputBorder(),
            prefixIcon: const Icon(Icons.attach_money),
            suffixText: 'FCFA',
            helperText: 'Maximum: ${_formatAmount(maxAmount)} FCFA',
          ),
          onChanged: onChanged,
        ),
      ],
    );
  }

  String _formatAmount(int centimes) {
    final francs = centimes / 100;
    return francs
        .toStringAsFixed(0)
        .replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        );
  }
}

/// Custom input formatter for amount fields
class _AmountInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    // Remove any non-digit characters
    final digitsOnly = newValue.text.replaceAll(RegExp(r'[^\d]'), '');

    if (digitsOnly.isEmpty) {
      return const TextEditingValue(
        text: '',
        selection: TextSelection.collapsed(offset: 0),
      );
    }

    // Format with thousand separators
    final formatted = _addThousandSeparators(digitsOnly);

    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
  }

  String _addThousandSeparators(String value) {
    return value.replaceAllMapped(
      RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
      (Match m) => '${m[1]} ',
    );
  }
}
