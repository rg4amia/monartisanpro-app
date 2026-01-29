/// API Constants for ProSartisan Platform
class ApiConstants {
  // Base URL - should be configured per environment
  static const String baseUrl = 'https://prosartisan.net/api/v1';

  // Auth Endpoints
  static const String register = '/auth/register';
  static const String login = '/auth/login';
  static const String otpGenerate = '/auth/otp/generate';
  static const String otpVerify = '/auth/otp/verify';
  static const String kycUpload = '/users/{id}/kyc';

  // Headers
  static const String contentType = 'application/json';
  static const String accept = 'application/json';
  static const String authorization = 'Authorization';

  // Timeouts
  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
