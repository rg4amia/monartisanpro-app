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

  // Static Data Endpoints (Cached)
  static const String staticTradeCategories = '/static/trade-categories';
  static const String staticMissionStatuses = '/static/mission-statuses';
  static const String staticDevisStatuses = '/static/devis-statuses';
  static const String staticAll = '/static/all';

  // Marketplace Endpoints
  static const String missions = '/missions';
  static const String missionById = '/missions/{id}';
  static const String missionsNearby = '/missions/nearby';
  static const String missionQuotes = '/missions/{missionId}/quotes';
  static const String quotes = '/quotes';
  static const String quoteById = '/quotes/{id}';
  static const String quoteAccept = '/quotes/{id}/accept';
  static const String artisansSearch = '/artisans/search';
  static const String artisanById = '/artisans/{id}';

  // Worksite Endpoints
  static const String chantiers = '/chantiers';
  static const String chantierById = '/chantiers/{id}';
  static const String jalons = '/jalons';
  static const String jalonById = '/jalons/{id}';
  static const String jalonSubmitProof = '/jalons/{id}/submit-proof';
  static const String jalonValidate = '/jalons/{id}/validate';
  static const String jalonContest = '/jalons/{id}/contest';

  // Financial Endpoints
  static const String escrowBlock = '/escrow/block';
  static const String jetonGenerate = '/jetons/generate';
  static const String jetonValidate = '/jetons/validate';
  static const String jetonById = '/jetons/{id}';
  static const String jetonByCode = '/jetons/code/{code}';
  static const String transactions = '/transactions';
  static const String transactionById = '/transactions/{id}';

  // Reputation Endpoints
  static const String artisanReputation = '/artisans/{id}/reputation';
  static const String artisanScoreHistory = '/artisans/{id}/score-history';
  static const String artisanRatings = '/artisans/{id}/ratings';
  static const String missionRate = '/missions/{id}/rate';

  // Dispute Endpoints
  static const String disputes = '/disputes';
  static const String disputeById = '/disputes/{id}';
  static const String disputeMediationStart = '/disputes/{id}/mediation/start';
  static const String disputeMediationMessage = '/disputes/{id}/mediation/message';
  static const String disputeArbitrationRender = '/disputes/{id}/arbitration/render';
  static const String adminDisputes = '/admin/disputes';

  // GPS Validation Endpoints
  static const String gpsValidateProximity = '/gps/validate-proximity';
  static const String gpsVerifyOtp = '/gps/verify-otp';
  static const String gpsCalculateDistance = '/gps/calculate-distance';
  static const String gpsGenerateOtp = '/gps/generate-otp';

  // Health & Documentation Endpoints
  static const String health = '/health';
  static const String healthDetailed = '/health/detailed';
  static const String healthMetrics = '/health/metrics';
  static const String docsSpec = '/docs/spec';

  // Upload Endpoints
  static const String uploadEvidence = '/upload/evidence';

  // Headers
  static const String contentType = 'application/json';
  static const String accept = 'application/json';
  static const String authorization = 'Authorization';

  // Timeouts
  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);
}
