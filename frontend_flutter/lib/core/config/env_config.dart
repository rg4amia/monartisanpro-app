import 'dart:io';
import 'package:flutter/foundation.dart';

class EnvConfig {
  // Configuration pour différents environnements

  // Pour émulateur Android (10.0.2.2 = localhost de la machine hôte)
  static const String emulatorBaseUrl = 'http://10.0.2.2:8000/api/v1';

  // Pour appareil physique : remplacez par l'IP de votre machine sur le réseau local.
  // Trouvez votre IP avec: ifconfig (Mac/Linux) ou ipconfig (Windows)
  // Exemple: 'http://192.168.1.42:8000/api/v1'
  static const String deviceBaseUrl = 'http://192.168.100.5:8000/api/v1';

  // Pour iOS Simulator
  static const String iosSimulatorBaseUrl = 'http://localhost:8000/api/v1';

  // Pour production
  static const String productionBaseUrl = 'https://prosartisan.net/api/v1';

  // Détection automatique de l'environnement
  static String get baseUrl {
    // Si on est dans un environnement de test unitaire/d'intégration
    if (!kIsWeb && Platform.environment.containsKey('FLUTTER_TEST')) {
      return 'http://127.0.0.1:8000/api/v1';
    }

    // En production réelle, décommentez la ligne suivante :
    const bool isProduction = bool.fromEnvironment('dart.vm.product');
    if (isProduction) return productionBaseUrl;

    // Pour le développement local (détection dynamique) :
    if (kIsWeb) {
      return 'http://localhost:8000/api/v1';
    }
    if (Platform.isAndroid) {
      // Retourner deviceBaseUrl pour tester avec un APK sur le réseau local
      return deviceBaseUrl;
    }
    if (Platform.isIOS) {
      return iosSimulatorBaseUrl;
    }

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
  
  // Active les logs Telegram en debug/release
  static const bool telegramLoggerDebug = bool.fromEnvironment(
    'TELEGRAM_LOGGER_DEBUG',
    defaultValue: true,
  );

  static const bool telegramLoggerRelease = bool.fromEnvironment(
    'TELEGRAM_LOGGER_RELEASE',
    defaultValue: true,
  );
}
