import 'package:frontend_flutter/core/config/env_config.dart';

class ApiEndpoints {
  // Utilise la configuration d'environnement automatique
  static String get baseUrl => EnvConfig.baseUrl;

  // Auth
  static const String sendOtp = '/auth/send-otp';
  static const String verifyOtp = '/auth/verify-otp';
  static const String register = '/auth/register';
  static const String securityChallenge = '/auth/security-challenge';
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

  // Carnet d'adresses
  static const String addresses = '/addresses';
  static String address(int id) => '/addresses/$id';
  static String addressSetDefault(int id) => '/addresses/$id/default';

  // Artisans
  static const String artisans = '/artisans';
  static String artisan(int id) => '/artisans/$id';
  static String artisanScore(int id) => '/artisans/$id/score';
  static String artisanReport(int id) => '/artisans/$id/report';

  // Stock artisan (matériaux personnels)
  static const String artisanStock = '/artisan-stock';
  static String artisanStockItem(int id) => '/artisan-stock/$id';

  // Fournisseurs & Commandes
  static const String fournisseurs = '/fournisseurs';
  static String fournisseurArticles(int id) => '/fournisseurs/$id/articles';
  static const String supplierProducts = '/supplier-products';
  static String supplierProduct(int id) => '/supplier-products/$id';
  static const String orders = '/orders';
  static const String ordersMultiEstimate = '/orders/multi-estimate';
  static const String ordersMultiStore = '/orders/multi-store';
  static String order(int id) => '/orders/$id';
  static const String supplierOrders = '/supplier/orders';
  static const String supplierDashboard = '/supplier/dashboard';
  static const String supplierLitiges = '/supplier/litiges';
  static String orderPrepared(int id) => '/orders/$id/prepared';
  static String orderDispute(int id) => '/orders/$id/dispute';
  static String orderWaitingSurge(int id) => '/orders/$id/waiting-surge';

  // Sectors
  static const String sectors = '/sectors';
  static String sectorTrades(int id) => '/sectors/$id/trades';

  // Types d'intervention
  static const String interventionTypes = '/intervention-types';

  // Recrutement BTP & Métiers
  static const String recruitmentOffers = '/recruitment-offers';
  static String recruitmentOffer(int id) => '/recruitment-offers/$id';
  static const String myRecruitmentOffers = '/recruitment-offers/mine';
  static String recruitmentOfferApplications(int id) =>
      '/recruitment-offers/$id/applications';
  static String recruitmentOfferApply(int id) =>
      '/recruitment-offers/$id/apply';
  static String recruitmentApplicationStatus(int offerId, int applicationId) =>
      '/recruitment-offers/$offerId/applications/$applicationId/status';
  static const String myRecruitmentApplications =
      '/recruitment-applications/mine';
  static String recruitmentEngage(int applicationId) =>
      '/recruitment-applications/$applicationId/engage';
  static const String myRecruitmentEngagements =
      '/recruitment-engagements/mine';
  static String recruitmentEngagement(int id) => '/recruitment-engagements/$id';
  static String recruitmentEngagementAccept(int id) =>
      '/recruitment-engagements/$id/accept';
  static String recruitmentEngagementDecline(int id) =>
      '/recruitment-engagements/$id/decline';
  static String recruitmentEngagementExtend(int id) =>
      '/recruitment-engagements/$id/extend';
  static String recruitmentEngagementPay(int id) =>
      '/recruitment-engagements/$id/pay';
  static String recruitmentEngagementActivate(int id) =>
      '/recruitment-engagements/$id/activate';
  static String recruitmentWorkdayValidate(int engagementId, int workdayId) =>
      '/recruitment-engagements/$engagementId/workdays/$workdayId/validate';
  static String recruitmentOfferUnlockApplicants(int offerId) =>
      '/recruitment-offers/$offerId/unlock-applicants';
  static String recruitmentOfferActivateApplicantsUnlock(int offerId) =>
      '/recruitment-offers/$offerId/activate-applicants-unlock';
  static String recruitmentRequestCallback(int applicationId) =>
      '/recruitment-applications/$applicationId/request-callback';

  // Missions
  static const String missions = '/missions';
  static String mission(int id) => '/missions/$id';
  static String missionSiteMap(int id) => '/missions/$id/site-map';
  static const String missionEstimate = '/missions/estimate';
  static const String preDiagnostic = '/missions/pre-diagnostic';
  static String missionStatus(int id) => '/missions/$id/status';
  static String missionDevis(int id) => '/missions/$id/devis';
  static String missionDevisSuggest(int id) => '/missions/$id/devis/suggest';
  static String missionDevisVoiceQuote(int id) =>
      '/missions/$id/devis/voice-quote';
  static String missionMessages(int id) => '/missions/$id/messages';
  static String missionMessageRead(int missionId, int messageId) =>
      '/missions/$missionId/messages/$messageId/read';
  static String missionStream(int id) => '/missions/$id/stream';
  static String missionJalons(int id) => '/missions/$id/jalons';
  static String missionReferentValidate(int id) =>
      '/missions/$id/referent-validate';
  static const String referentMissions = '/referent/missions';

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
  static const String microCreditCurrent = '/micro-credit/current';
  static const String microCreditRepay = '/micro-credit/repay';
  static const String microCreditReport = '/micro-credit/report';

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
  static String jcodePhotoMateriaux(Object identifier) =>
      '/jcodes/$identifier/photo-materiaux';

  // Wallet & Transactions
  static const String transactions = '/transactions';
  static const String walletBalance = '/wallets/balance';

  // Litiges
  static const String litiges = '/litiges';
  static String litige(int id) => '/litiges/$id';
  static String litigeEvidence(int id) => '/litiges/$id/preuves';
  static String litigeEvaluateSla(int id) => '/litiges/$id/evaluate-sla';
  static String litigeJuryVote(int id) => '/litiges/$id/jury/vote';

  // Evaluations
  static const String evaluations = '/evaluations';
  static const String myEvaluations = '/evaluations/my';
  static String missionEvaluationsStatus(int id) =>
      '/missions/$id/evaluations-status';
  static String orderEvaluationsStatus(int id) =>
      '/orders/$id/evaluations-status';

  // Notifications
  static const String notifications = '/notifications';
  static String markNotificationRead(int id) => '/notifications/$id/read';
  static const String markAllRead = '/notifications/mark-all-read';

  // Communications
  static const String communicationsActive = '/communications/active';

  // Aide & support
  static const String faqs = '/faqs';
  static const String publicSettings = '/vitrine/settings';

  // Livraisons & Courses
  static const String deliveriesEstimate = '/deliveries/estimate';
  static const String ordersEstimateDelivery = '/orders/estimate-delivery';
  static const String deliveriesAvailable = '/deliveries/available';
  static const String deliveryBatches = '/deliveries/batches';
  static const String deliveryBatchAccept = '/deliveries/batch-accept';
  static const String deliveryActiveTour = '/deliveries/active-tour';
  static String acceptDelivery(int orderId) => '/deliveries/$orderId/accept';
  static String orderVerifyPickup(int orderId) =>
      '/orders/$orderId/verify-pickup';
  static String orderVerifyDelivery(int orderId) =>
      '/orders/$orderId/verify-delivery';
  static String orderLocation(int orderId) => '/orders/$orderId/location';
  static String orderTracking(int orderId) => '/orders/$orderId/tracking';

  // Fournisseur & Cash-out
  static const String supplierCashouts = '/supplier/cashouts';
  static String supplierCashoutReceipt(int id) =>
      '/supplier/cashouts/$id/receipt';
}
