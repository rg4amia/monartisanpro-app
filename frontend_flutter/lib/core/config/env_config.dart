import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:frontend_flutter/core/network/network_discovery_service.dart';

class EnvConfig {
  // ── URLs statiques (fallbacks) ──────────────────────────────────────────────

  /// Émulateur Android (10.0.2.2 = localhost de la machine hôte)
  static const String emulatorBaseUrl = 'http://10.0.2.2:8000/api/v1';

  /// iOS Simulator
  static const String iosSimulatorBaseUrl = 'http://localhost:8000/api/v1';

  /// Domaine de production. Surchargeable au build :
  ///   flutter build apk --dart-define=API_HOST=prosartisan.net
  static const String productionHost = String.fromEnvironment(
    'API_HOST',
    defaultValue: 'prosartisan.net',
  );

  /// Production
  static const String productionBaseUrl = 'https://$productionHost/api/v1';

  // ── État interne ────────────────────────────────────────────────────────────

  static bool _initialized = false;

  /// Mode courant : "production", "local", "emulator", "unknown"
  static String get currentMode => NetworkDiscoveryService.mode;

  // ── Initialisation asynchrone (appeler dans main()) ─────────────────────────

  /// Découvre automatiquement le serveur backend.
  ///
  /// Ordre de priorité :
  /// 1. Build release → production forcée
  /// 2. Serveur de production joignable → production
  /// 3. Émulateur Android (10.0.2.2:8000) → émulateur
  /// 4. Scan du sous-réseau local (port 8000) → local
  /// 5. Fallback → production
  static Future<void> init() async {
    if (_initialized) return;
    await NetworkDiscoveryService.discover();
    _initialized = true;
    debugPrint('[EnvConfig] Initialisé → mode=$currentMode url=$baseUrl');
  }

  /// Force une re-découverte (ex: après changement de réseau WiFi).
  static Future<void> rediscover() async {
    _initialized = false;
    await NetworkDiscoveryService.rediscover();
    _initialized = true;
    debugPrint('[EnvConfig] Re-découverte → mode=$currentMode url=$baseUrl');
  }

  // ── Getter synchrone (compatible avec le code existant) ─────────────────────

  /// URL de base du serveur API.
  ///
  /// Si [init] n'a pas encore été appelé, retourne un fallback synchrone
  /// identique à l'ancien comportement (pour la rétro-compatibilité).
  static String get baseUrl {
    if (_initialized) {
      return NetworkDiscoveryService.resolvedUrl;
    }

    // Fallback synchrone (avant init) — rétro-compatible.
    return _syncFallback();
  }

  // ── Fallback synchrone (rétro-compatibilité) ───────────────────────────────

  static String _syncFallback() {
    if (!kIsWeb && Platform.environment.containsKey('FLUTTER_TEST')) {
      return 'http://127.0.0.1:8000/api/v1';
    }

    const bool isProduction = bool.fromEnvironment('dart.vm.product');
    if (isProduction) return productionBaseUrl;

    if (kIsWeb) return 'http://localhost:8000/api/v1';

    if (Platform.isAndroid) return emulatorBaseUrl;
    if (Platform.isIOS) return iosSimulatorBaseUrl;

    return 'http://localhost:8000/api/v1';
  }

  // ── Clés de services tiers ────────────────────────────────────────────────
  // AUCUNE clé n'est stockée en dur : elles sont injectées au build via
  //   flutter run --dart-define-from-file=env.json
  // (fichier gitignoré, gabarit dans env.example.json). En CI, `env.json` est
  // généré depuis les secrets GitHub.

  /// Clé Yandex MapKit SDK (rendu de la carte).
  static const String yandexMapKitApiKey = String.fromEnvironment(
    'YANDEX_MAPKIT_API_KEY',
  );

  /// Clé Yandex Distance Matrix API (distances/durées — non utilisée côté mobile
  /// pour l'instant, le calcul reste serveur ; exposée pour usage futur).
  static const String yandexDistanceMatrixApiKey = String.fromEnvironment(
    'YANDEX_DISTANCE_MATRIX_API_KEY',
  );

  /// Clé Yandex Geolocation API (position approximative wifi/cellulaire).
  static const String yandexGeolocationApiKey = String.fromEnvironment(
    'YANDEX_GEOLOCATION_API_KEY',
  );

  /// `true` si la clé MapKit est absente du build (carte non fonctionnelle).
  static bool get isYandexMapKitConfigured => yandexMapKitApiKey.isNotEmpty;

  static const String oneSignalAppId = String.fromEnvironment(
    'ONESIGNAL_APP_ID',
    defaultValue: '00d061c8-977b-405a-a207-e2d87846670b',
  );
}
