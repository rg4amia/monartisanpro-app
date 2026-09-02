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

  // ── Telegram Logger Configuration ──────────────────────────────────────────
  // Passez ces valeurs via dart-define au build :
  //   flutter run --dart-define=TELEGRAM_BOT_TOKEN=xxx --dart-define=TELEGRAM_CHAT_ID=yyy
  static const String telegramBotToken = String.fromEnvironment(
    'TELEGRAM_BOT_TOKEN',
    defaultValue: '',
  );

  static const String telegramChatId = String.fromEnvironment(
    'TELEGRAM_CHAT_ID',
    defaultValue: '',
  );

  // Active les logs Telegram en debug/release.
  // En production le logger est DÉSACTIVÉ par défaut : il expose des données
  // potentiellement personnelles (URLs, payloads d'erreur, téléphones) à un
  // service tiers, ce qui est incompatible avec le RGPD / la Loi CI n° 2013-450.
  // L'activer explicitement au build si un canal d'audit dédié est en place :
  //   --dart-define=TELEGRAM_LOGGER_RELEASE=true
  static const bool telegramLoggerDebug = bool.fromEnvironment(
    'TELEGRAM_LOGGER_DEBUG',
    defaultValue: true,
  );

  static const bool telegramLoggerRelease = bool.fromEnvironment(
    'TELEGRAM_LOGGER_RELEASE',
    defaultValue: false,
  );

  // ── Clés de services tiers (surcharge au build via --dart-define) ──────────
  // Valeurs par défaut = clés de développement ; à surcharger en CI/CD prod.
  static const String yandexMapKitApiKey = String.fromEnvironment(
    'YANDEX_MAPKIT_API_KEY',
    defaultValue: 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d',
  );

  static const String oneSignalAppId = String.fromEnvironment(
    'ONESIGNAL_APP_ID',
    defaultValue: '00d061c8-977b-405a-a207-e2d87846670b',
  );
}
