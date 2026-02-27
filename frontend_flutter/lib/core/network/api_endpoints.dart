class ApiEndpoints {
  static const String baseUrl = 'http://localhost:8000/api/v1';

  // Auth
  static const String sendOtp = '/auth/send-otp';
  static const String verifyOtp = '/auth/verify-otp';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';

  // KYC
  static const String kycUploadCni = '/kyc/upload-cni';
  static const String kycUploadSelfie = '/kyc/upload-selfie';
  static const String kycStatus = '/kyc/status';

  // Users
  static String updateUser(int id) => '/users/$id';
  static String updateLocation(int id) => '/users/$id/location';
  static String setRole(int id) => '/users/$id/role';

  // Artisans
  static const String artisans = '/artisans';
  static String artisan(int id) => '/artisans/$id';
  static String artisanScore(int id) => '/artisans/$id/score';

  // Sectors
  static const String sectors = '/sectors';
  static String sectorTrades(int id) => '/sectors/$id/trades';

  // Missions
  static const String missions = '/missions';
  static String mission(int id) => '/missions/$id';
  static const String missionEstimate = '/missions/estimate';
  static String missionStatus(int id) => '/missions/$id/status';
  static String missionDevis(int id) => '/missions/$id/devis';
  static String missionJalons(int id) => '/missions/$id/jalons';

  // Devis
  static String devis(int id) => '/devis/$id';
  static String acceptDevis(int id) => '/devis/$id/accept';
  static String refuseDevis(int id) => '/devis/$id/refuse';

  // Jalons
  static String submitJalon(int id) => '/jalons/$id/submit';
  static String requestOtp(int id) => '/jalons/$id/request-otp';
  static String validateOtp(int id) => '/jalons/$id/validate-otp';

  // J-Codes
  static const String jcodes = '/jcodes';
  static const String jcodesActive = '/jcodes/active';
  static String jcode(int id) => '/jcodes/$id';
  static String scanJcode(int id) => '/jcodes/$id/scan';

  // Wallet & Transactions
  static const String transactions = '/transactions';
  static const String walletBalance = '/wallets/balance';

  // Litiges
  static const String litiges = '/litiges';
  static String litige(int id) => '/litiges/$id';

  // Evaluations
  static const String evaluations = '/evaluations';

  // Notifications
  static const String notifications = '/notifications';
  static String markNotificationRead(int id) => '/notifications/$id/read';
  static const String markAllRead = '/notifications/mark-all-read';
}
