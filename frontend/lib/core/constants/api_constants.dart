/// API configuration constants for ProsArtisan backend
class ApiConstants {
  // Base URLs
  static const String baseUrl = 'https://prosartisan.net/api/v1';
  static const String socketUrl = 'ws://localhost:6001';

  // API Endpoints - Auth
  static const String register = '/auth/register';
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String me = '/auth/me';
  static const String sendOtp = '/auth/send-otp';
  static const String verifyOtp = '/auth/verify-otp';
  static const String updateProfile = '/auth/profile';

  // API Endpoints - KYC
  static const String uploadKyc = '/kyc/upload';
  static const String kycStatus = '/kyc/status';

  // API Endpoints - Search
  static const String searchArtisans = '/search/artisans';
  static const String artisanProfile = '/search/artisan';
  static const String nearbyArtisans = '/search/nearby';

  // API Endpoints - Trades & Sectors
  static const String sectors = '/sectors';
  static const String trades = '/trades';

  // API Endpoints - Disputes
  static const String disputes = '/disputes';
  static String getDispute(int id) => '/disputes/$id';
  static String sendDisputeMessage(int disputeId) =>
      '/disputes/$disputeId/messages';

  // API Endpoints - Messages / Chat
  static const String getConversations = '/messages/conversations';
  static String getProjectMessages(int projectId) =>
      '/projects/$projectId/messages';
  static String sendMessage(int projectId) => '/projects/$projectId/messages';
  static String markMessageAsRead(int messageId) => '/messages/$messageId/read';

  // Connection timeouts
  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);
  static const Duration sendTimeout = Duration(seconds: 30);

  // Storage keys
  static const String authTokenKey = 'auth_token';
  static const String userDataKey = 'user_data';
  static const String onboardingKey = 'onboarding_completed';
}
