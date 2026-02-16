/// Application-wide constants for ProsArtisan
class AppConstants {
  // App Info
  static const String appName = 'ProsArtisan';
  static const String appVersion = '1.0.0';

  // GPS & Location
  static const double gpsValidationDistance = 100.0; // meters
  static const double nearbyArtisanDistance = 2000.0; // meters (2km for golden marker)
  static const double defaultSearchRadius = 10000.0; // meters (10km)
  static const double maxSearchRadius = 50000.0; // meters (50km)

  // Map
  static const double defaultMapZoom = 14.0;
  static const double clusterMapZoom = 12.0;

  // Token
  static const int tokenValidityDays = 7;

  // File Upload
  static const int maxImageSize = 5 * 1024 * 1024; // 5MB
  static const List<String> allowedImageFormats = ['jpg', 'jpeg', 'png'];

  // Pagination
  static const int defaultPageSize = 20;

  // Session
  static const Duration sessionTimeout = Duration(hours: 24);

  // Rating
  static const int minRating = 1;
  static const int maxRating = 5;

  // Currency
  static const String currency = 'FCFA';
  static const String currencySymbol = 'XOF';

  // Phone
  static const String countryCode = '+225';
  static const int phoneLength = 10;

  // OTP
  static const int otpLength = 6;
  static const Duration otpResendDelay = Duration(seconds: 60);

  // Project Thresholds
  static const double referentRequiredAmount = 2000000.0; // 2M FCFA for auto-assignment of Référent
}
