import 'package:frontend_flutter/core/config/env_config.dart';

class ApiEndpoints {
  // Utilise la configuration d'environnement automatique
  static String get baseUrl => EnvConfig.baseUrl;

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
  static String updateCnmci(int id) => '/users/$id/cnmci';

  // Dashboard
  static const String dashboard = '/dashboard';

  // Artisans
  static const String artisans = '/artisans';
  static String artisan(int id) => '/artisans/$id';
  static String artisanScore(int id) => '/artisans/$id/score';

  // Fournisseurs & Commandes
  static const String fournisseurs = '/fournisseurs';
  static String fournisseurArticles(int id) => '/fournisseurs/$id/articles';
  static const String supplierProducts = '/supplier-products';
  static String supplierProduct(int id) => '/supplier-products/$id';
  static const String orders = '/orders';
  static String order(int id) => '/orders/$id';

  // Sectors
  static const String sectors = '/sectors';
  static String sectorTrades(int id) => '/sectors/$id/trades';

  // Missions
  static const String missions = '/missions';
  static String mission(int id) => '/missions/$id';
  static const String missionEstimate = '/missions/estimate';
  static String missionStatus(int id) => '/missions/$id/status';
  static String missionDevis(int id) => '/missions/$id/devis';
  static String missionDevisSuggest(int id) => '/missions/$id/devis/suggest';
  static String missionJalons(int id) => '/missions/$id/jalons';
  static String missionReferentValidate(int id) =>
      '/missions/$id/referent-validate';

  // Devis
  static String devis(int id) => '/devis/$id';
  static String acceptDevis(int id) => '/devis/$id/accept';
  static String refuseDevis(int id) => '/devis/$id/refuse';

  // Paiements
  static const String paymentsInitiate = '/payments/initiate';
  static String paymentStatus(int id) => '/payments/$id/status';

  // Micro-crédit
  static const String microCreditEligibility = '/micro-credit/eligibility';
  static const String microCreditApply = '/micro-credit/apply';

  // Jalons
  static String submitJalon(int id) => '/jalons/$id/submit';
  static String requestOtp(int id) => '/jalons/$id/request-otp';
  static String validateOtp(int id) => '/jalons/$id/validate-otp';
  static String uploadJalonPhotos(int id) => '/jalons/$id/photos';
  static String acceptJalonProofs(int id) => '/jalons/$id/accept-proofs';

  // J-Codes
  static const String jcodes = '/jcodes';
  static const String jcodesActive = '/jcodes/active';
  static String jcode(Object identifier) => '/jcodes/$identifier';
  static String scanJcode(Object identifier) => '/jcodes/$identifier/scan';

  // Wallet & Transactions
  static const String transactions = '/transactions';
  static const String walletBalance = '/wallets/balance';

  // Litiges
  static const String litiges = '/litiges';
  static String litige(int id) => '/litiges/$id';
  static String litigeEvidence(int id) => '/litiges/$id/preuves';
  static String litigeEvaluateSla(int id) => '/litiges/$id/evaluate-sla';

  // Evaluations
  static const String evaluations = '/evaluations';

  // Notifications
  static const String notifications = '/notifications';
  static String markNotificationRead(int id) => '/notifications/$id/read';
  static const String markAllRead = '/notifications/mark-all-read';

  // Communications
  static const String communicationsActive = '/communications/active';

  // Livraisons & Courses
  static const String deliveriesAvailable = '/deliveries/available';
  static String acceptDelivery(int orderId) => '/deliveries/$orderId/accept';
  static String orderVerifyPickup(int orderId) => '/orders/$orderId/verify-pickup';
  static String orderVerifyDelivery(int orderId) => '/orders/$orderId/verify-delivery';
}
