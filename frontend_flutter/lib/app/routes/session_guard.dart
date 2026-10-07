import 'package:flutter/widgets.dart';
import 'package:get/get.dart';

import '../../core/storage/storage_service.dart';
import 'app_routes.dart';

/// Renvoie à la connexion quiconque ouvre un écran de l'application sans
/// compte connecté.
///
/// Aucune route n'était gardée : un écran s'ouvrait par son seul nom. Les
/// appels à l'API restaient protégés par le jeton, mais l'écran s'affichait,
/// en erreur.
class SessionGuard extends GetMiddleware {
  SessionGuard({bool Function()? hasSession})
      : _hasSession = hasSession ?? _storedSession;

  final bool Function() _hasSession;

  /// Écrans ouverts sans compte : démarrage, accueil, connexion, inscription,
  /// textes légaux.
  static const Set<String> publicRoutes = {
    Routes.splash,
    Routes.onboarding,
    Routes.login,
    Routes.otpVerification,
    Routes.register,
    Routes.cgu,
    Routes.privacyPolicy,
  };

  static bool _storedSession() => StorageService.getUserId() != null;

  /// Ajoute la garde à toutes les routes qui ne sont pas publiques.
  static List<GetPage<dynamic>> protect(List<GetPage<dynamic>> pages) => pages
      .map(
        (page) => publicRoutes.contains(page.name)
            ? page
            : page.copy(middlewares: [SessionGuard(), ...?page.middlewares]),
      )
      .toList();

  @override
  RouteSettings? redirect(String? route) =>
      _hasSession() ? null : const RouteSettings(name: Routes.login);
}
