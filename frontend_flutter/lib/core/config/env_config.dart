class EnvConfig {
  // Configuration pour différents environnements

  // Pour émulateur Android
  static const String emulatorBaseUrl = 'http://10.0.2.2:8000/api/v1';

  // Pour appareil physique (remplacez par votre IP locale)
  // Trouvez votre IP avec: ifconfig (Mac/Linux) ou ipconfig (Windows)
  static const String deviceBaseUrl = 'http://192.168.1.x:8000/api/v1';

  // Pour iOS Simulator
  static const String iosSimulatorBaseUrl = 'http://localhost:8000/api/v1';

  // Pour production
  static const String productionBaseUrl = 'https://api.prosartisan.com/api/v1';

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
}
