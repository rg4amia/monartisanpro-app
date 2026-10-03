import 'package:flutter/foundation.dart';
import 'package:smart_auth/smart_auth.dart';

import '../utils/otp_code_extractor.dart';

/// Attend le SMS du code de vérification pour préremplir l'écran OTP.
///
/// Un remplissage impossible n'est jamais une erreur pour l'utilisateur :
/// [waitForCode] renvoie `null` et la saisie manuelle reste disponible.
abstract class OtpSmsListener {
  /// Code lu dans le prochain SMS reçu, ou `null` (refus de l'utilisateur,
  /// délai dépassé, service indisponible, SMS sans code).
  Future<String?> waitForCode();

  Future<void> cancel();
}

/// Lecture par l'API « SMS User Consent » de Google Play Services : Android
/// affiche le SMS reçu et l'utilisateur autorise sa lecture d'un appui.
/// Aucune permission SMS n'est requise et le texte du SMS reste libre.
///
/// L'écoute doit démarrer **avant** l'envoi du SMS : un message arrivé avant
/// elle n'est pas remis. Android ignore aussi un expéditeur enregistré dans
/// les contacts du téléphone.
class SmartAuthOtpSmsListener implements OtpSmsListener {
  bool get _supported =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  @override
  Future<String?> waitForCode() async {
    if (!_supported) return null;

    try {
      final result = await SmartAuth.instance.getSmsWithUserConsentApi();
      return extractOtpCode(result.data?.sms);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> cancel() async {
    if (!_supported) return;

    try {
      await SmartAuth.instance.removeUserConsentApiListener();
    } catch (_) {
      // Rien à annuler : l'écoute n'avait pas démarré.
    }
  }
}
