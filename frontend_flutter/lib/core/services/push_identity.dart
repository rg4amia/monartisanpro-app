import 'package:flutter/foundation.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

/// Rattachement de l'appareil au compte connecté pour les notifications push
/// (OneSignal, `external_id` = identifiant utilisateur, cf. backend
/// `OneSignalService::sendToUser`).
///
/// Toute fin de session doit appeler [unlink] : sans cela, l'appareil
/// continuait de recevoir les notifications (paiements, litiges) du compte
/// précédent après une déconnexion, une suppression de compte ou une session
/// expirée (Chantier 14, lot A).
///
/// Les erreurs du plugin sont absorbées : une déconnexion ne doit jamais
/// échouer parce que le SDK push est indisponible.
class PushIdentity {
  PushIdentity._();

  /// Associe l'appareil au compte [userId].
  static Future<void> link(int userId) async {
    try {
      await OneSignal.login(userId.toString());
    } catch (e) {
      debugPrint('PushIdentity.link : $e');
    }
  }

  /// Détache l'appareil de tout compte.
  static Future<void> unlink() async {
    try {
      await OneSignal.logout();
    } catch (e) {
      debugPrint('PushIdentity.unlink : $e');
    }
  }
}
