import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Amount input field widget with validation
class AmountInputField extends StatelessWidget {
  final TextEditingController controller;
  final String labelText;
  final int maxAmount;
  final ValueChanged<String>? onChanged;

  const AmountInputField({
    super.key,
    required this.controller,
    required this.labelText,
    required this.maxAmount,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: AppSpacing.inputHeight,
          child: TextField(
            controller: controller,
            keyboardType: TextInputType.number,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              _AmountInputFormatter(),
            ],
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            decoration: InputDecoration(
              labelText: labelText,
              labelStyle: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
              hintText: '0',
              hintStyle: AppTypography.body.copyWith(
                color: AppColors.textMuted,
              ),
              filled: true,
              fillColor: AppColors.cardBg,
              border: OutlineInputBorder(
                borderRadius: AppRadius.inputRadius,
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: AppRadius.inputRadius,
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: AppRadius.inputRadius,
                borderSide: BorderSide(
                  color: AppColors.accentPrimary,
                  width: 2,
                ),
              ),
              prefixIcon: Icon(
                Icons.attach_money,
                color: AppColors.textSecondary,
              ),
              suffixText: 'FCFA',
              suffixStyle: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
              helperText: 'Maximum: ${_formatAmount(maxAmount)} FCFA',
              helperStyle: AppTypography.caption.copyWith(
                color: AppColors.textTertiary,
              ),
              contentPadding: AppSpacing.inputPaddingDefault,
            ),
            onChanged: onChanged,
          ),
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
