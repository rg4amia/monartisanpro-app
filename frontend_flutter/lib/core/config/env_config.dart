class EnvConfig {
  // Configuration pour différents environnements

  // Pour émulateur Android
  //static const String emulatorBaseUrl = 'http://10.0.2.2:8000/api/v1';
  static const String emulatorBaseUrl = 'https://prosartisan.net/api/v1';

  // Pour appareil physique (remplacez par votre IP locale)
  // Trouvez votre IP avec: ifconfig (Mac/Linux) ou ipconfig (Windows)
  static const String deviceBaseUrl = 'http://192.168.1.x:8000/api/v1';

  // Pour iOS Simulator
  static const String iosSimulatorBaseUrl = 'http://localhost:8000/api/v1';

  // Pour production
  static const String productionBaseUrl = 'https://prosartisan.net/api/v1';

  // Détection automatique de l'environnement
  static String get baseUrl {
    // En développement, utilisez l'URL de l'émulateur
    // En production, utilisez l'URL de production
    const bool isProduction = bool.fromEnvironment('dart.vm.product');

    if (isProduction) {
      return productionBaseUrl;
    }

    // Pour le développement, utilisez l'émulateur par défaut
    return emulatorBaseUrl;
  }

  // ── Telegram Logger Configuration ──────────────────────────────────────────
  // 1. Créez un bot via @BotFather sur Telegram
  // 2. Récupérez le token du bot
  // 3. Envoyez un message à votre bot
  // 4. Récupérez votre chat_id via: https://api.telegram.org/bot<TOKEN>/getUpdates
  //8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE
  static const String telegramBotToken = String.fromEnvironment(
    '8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE',
    defaultValue: '8715763356:AAFPM6f1DALdYxn5gU6_DLX_-wZl6ZRtEJE',
  );
  
  static const String telegramChatId = String.fromEnvironment(
    '422674168',
    defaultValue: '422674168',
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
