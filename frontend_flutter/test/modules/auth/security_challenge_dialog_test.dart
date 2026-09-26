import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/theme/app_colors.dart';
import 'package:frontend_flutter/modules/auth/widgets/login/login_tokens.dart';

Widget buildSecurityChallengeDialog({
  required String question,
  required Color roleColor,
  required VoidCallback onRefresh,
  required ValueChanged<String> onSubmit,
  required VoidCallback onCancel,
}) {
  final answerCtrl = TextEditingController();

  return Dialog(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(22),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: roleColor.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.shield_outlined,
              color: roleColor,
              size: 28,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Contrôle de sécurité',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: LoginTokens.ink,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Pour protéger vos envois de SMS, confirmez que vous êtes bien un utilisateur réel :',
            style: TextStyle(
              fontSize: 12.5,
              color: LoginTokens.muted,
              height: 1.4,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 10,
            ),
            decoration: BoxDecoration(
              color: AppColors.secondary.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: roleColor.withValues(alpha: 0.3),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    question,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: roleColor,
                    ),
                    softWrap: true,
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  onPressed: onRefresh,
                  icon: const Icon(Icons.refresh_rounded, size: 20),
                  tooltip: 'Changer le calcul',
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: answerCtrl,
            autofocus: true,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              letterSpacing: 2,
            ),
            decoration: InputDecoration(
              hintText: 'Votre réponse',
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 12,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: roleColor, width: 2),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: TextButton(
                  onPressed: onCancel,
                  child: const Text(
                    'Annuler',
                    style: TextStyle(color: LoginTokens.muted),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    if (answerCtrl.text.trim().isEmpty) return;
                    onSubmit(answerCtrl.text.trim());
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: roleColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                  child: const Text(
                    'Valider',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

void main() {
  testWidgets('Security challenge dialog renders cleanly on small screen without overflow', (tester) async {
    // Set viewport to compact mobile screen (320px width x 480px height - small Android)
    tester.view.physicalSize = const Size(320 * 2, 480 * 2);
    tester.view.devicePixelRatio = 2.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Center(
            child: buildSecurityChallengeDialog(
              question: 'Sécurité : Combien font 6847 + 3921 ?',
              roleColor: AppColors.primary,
              onRefresh: () {},
              onSubmit: (_) {},
              onCancel: () {},
            ),
          ),
        ),
      ),
    );

    // Verify no exception / overflow
    expect(tester.takeException(), isNull);
    expect(find.text('Contrôle de sécurité'), findsOneWidget);
    expect(find.text('Sécurité : Combien font 6847 + 3921 ?'), findsOneWidget);
    expect(find.text('Annuler'), findsOneWidget);
    expect(find.text('Valider'), findsOneWidget);
  });
}
