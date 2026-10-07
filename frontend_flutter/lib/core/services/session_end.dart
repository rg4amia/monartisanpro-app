import 'package:flutter/foundation.dart';
import 'package:get/get.dart';

import '../cache/cache_store.dart';
import '../network/sync_service.dart';
import '../storage/storage_service.dart';
import 'push_identity.dart';

/// Fin de session : ce qui doit quitter le téléphone quand un compte s'en va.
///
/// Trois voies y mènent — déconnexion volontaire, session expirée ou révoquée
/// (401), suppression du compte — et toutes passent par [wipeLocalData]. Seule
/// la déconnexion volontaire faisait le nettoyage complet : après un 401, le
/// nom, le téléphone et tous les caches du compte restaient en place, et le
/// compte suivant pouvait se les voir servir hors connexion.
class SessionEnd {
  SessionEnd._();

  static Future<void> _last = Future<void>.value();

  /// Efface le jeton, l'identité du compte et ses caches, détache l'appareil
  /// des notifications, et retire de l'écran la file hors connexion.
  ///
  /// Les actions en file restent, chiffrées, pour leur propriétaire : une
  /// session expirée ne doit pas faire perdre une livraison validée hors
  /// connexion. Elles ne sont supprimées qu'avec le compte ([deletedUserId]).
  ///
  /// Les nettoyages s'exécutent l'un après l'autre, jamais en même temps
  /// (plusieurs requêtes peuvent recevoir 401 ensemble). Chaque appel fait
  /// son propre passage : se joindre à un nettoyage déjà avancé laisserait en
  /// place ce qui a été écrit depuis son début.
  static Future<void> wipeLocalData({int? deletedUserId}) {
    return _last = _last.then((_) => _wipe(deletedUserId));
  }

  static Future<void> _wipe(int? deletedUserId) async {
    // Chaque étape est tentée même si la précédente échoue : un stockage
    // indisponible ne doit pas laisser le reste en place.
    await _attempt('jeton et identité', StorageService.clearSession);
    await _attempt('notifications', PushIdentity.unlink);
    await _attempt('caches', CacheStore.wipeAll);
    await _attempt('file hors connexion', () async {
      if (!Get.isRegistered<SyncService>()) return;

      final sync = Get.find<SyncService>();
      if (deletedUserId != null) await sync.purgeAccount(deletedUserId);
      sync.onSessionEnded();
    });
  }

  static Future<void> _attempt(
    String step,
    Future<void> Function() action,
  ) async {
    try {
      await action();
    } catch (e) {
      debugPrint('SessionEnd ($step) : $e');
    }
  }
}
