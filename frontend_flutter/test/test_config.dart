/// Configuration pour les tests avec le backend Laravel Herd
class TestConfig {
  static const String baseUrl = 'http://backend-proartisan.test/api/v1';

  // Données de test
  static const String testPhone = '+2250700000001';
  static const String testOtp = '123456';
  static const String testName = 'Test User';
  static const String testRole = 'client';

  // Timeouts
  static const Duration timeout = Duration(seconds: 30);
}
