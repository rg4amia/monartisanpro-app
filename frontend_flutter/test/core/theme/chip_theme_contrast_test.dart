import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/theme/app_colors.dart';
import 'package:frontend_flutter/core/theme/app_theme.dart';

/// Les pastilles de filtre doivent rester lisibles : un libellé non
/// sélectionné sortait blanc sur fond clair (« Tous » invisible).
void main() {
  Color labelColor(WidgetTester tester, String text) {
    final paragraph = tester.renderObject<RenderParagraph>(find.text(text));
    return paragraph.text.style!.color!;
  }

  testWidgets('libellé bleu nuit non sélectionné, blanc sélectionné',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: Row(
            children: [
              ChoiceChip(
                label: const Text('Tous'),
                selected: false,
                onSelected: (_) {},
              ),
              ChoiceChip(
                label: const Text('Secteur'),
                selected: true,
                onSelected: (_) {},
              ),
            ],
          ),
        ),
      ),
    );

    expect(labelColor(tester, 'Tous'), AppColors.textPrimary);
    expect(labelColor(tester, 'Secteur'), AppColors.textLight);
  });
}
