/// API Constants for ProSartisan Platform
class ApiConstants {
  // Base URL - should be configured per environment
  // Note: Using /api/v1 prefix since Laravel API routes are prefixed
  static const String baseUrl = 'https://prosartisan.net/api/v1';

  // Auth Endpoints (relative to baseUrl)
  static const String register = '/auth/register';
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String refresh = '/auth/refresh';
  static const String otpGenerate = '/auth/otp/generate';
  static const String otpVerify = '/auth/otp/verify';
  static const String kycUpload = '/users/{id}/kyc';

  // Reference Data Endpoints
  static const String trades = '/reference/trades';
  static const String sectors = '/reference/sectors';
  static const String tradesBySector = '/reference/sectors/{sectorId}/trades';
  static const String allTrades = '/reference/trades/all';

  // Headers
  static const String contentType = 'application/json';
  static const String accept = 'application/json';
  static const String authorization = 'Authorization';

  // Timeouts
  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
