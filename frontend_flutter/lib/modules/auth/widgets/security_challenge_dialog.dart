import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/auth_controller.dart';
import 'login/login_tokens.dart';

/// Défi anti-robot posé avant tout envoi de code : à la connexion, et à
/// chaque « Renvoyer le code ». Un défi ne sert qu'une fois.
///
/// Rend `true` quand l'utilisateur a saisi une réponse, enregistrée dans
/// `controller.botAnswer`.
Future<bool> showSecurityChallengeDialog(
  AuthController controller, {
  Color color = AppColors.primary,
}) async {
  final answerCtrl = TextEditingController();
  final roleColor = color;

  final result = await Get.dialog<bool>(
    Dialog(
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
            Obx(
              () => Container(
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
                        controller.challengeQuestion.value ??
                            'Addition requise',
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
                      onPressed: () => controller.fetchSecurityChallenge(),
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
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
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
                    onPressed: () => Get.back(result: false),
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
                      controller.botAnswer.value = answerCtrl.text.trim();
                      Get.back(result: true);
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
    ),
  );

  return result == true;
}
