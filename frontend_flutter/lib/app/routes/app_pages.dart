import 'package:get/get.dart';

import '../../modules/onboarding/bindings/onboarding_binding.dart';
import '../../modules/onboarding/views/splash_screen.dart';
import '../../modules/onboarding/views/onboarding_screen.dart';
import '../../modules/auth/bindings/auth_binding.dart';
import '../../modules/auth/views/kyc_cni_capture_screen.dart';
import '../../modules/auth/views/kyc_selfie_liveness_screen.dart';
import '../../modules/auth/views/login_screen.dart';
import '../../modules/auth/views/otp_verification_screen.dart';
import '../../modules/auth/views/register_screen.dart';
import '../../modules/artisans/bindings/artisans_binding.dart';
import '../../modules/artisans/views/artisan_profile_screen.dart';
import '../../modules/devis/bindings/devis_binding.dart';
import '../../modules/devis/views/quote_builder_screen.dart';
import '../../modules/devis/views/quote_screen.dart';
import '../../modules/jcode/bindings/jcode_binding.dart';
import '../../modules/jcode/views/jcode_screen.dart';
import '../../modules/jcode/views/scanner_screen.dart';
import '../../modules/jcode/views/supplier_catalog_screen.dart';
import '../../modules/jcode/views/transaction_confirm_screen.dart';
import '../../modules/jcode/views/jcode_serve_screen.dart';
import '../../modules/litige/bindings/litige_binding.dart';
import '../../modules/litige/bindings/litige_detail_binding.dart';
import '../../modules/litige/views/litige_screen.dart';
import '../../modules/litige/views/litige_detail_screen.dart';
import '../../modules/main_tab/bindings/main_tab_binding.dart';
import '../../modules/main_tab/views/main_tab_screen.dart';
import '../../modules/missions/bindings/location_picker_binding.dart';
import '../../modules/missions/bindings/missions_binding.dart';
import '../../modules/missions/views/mission_request_screen.dart';
import '../../modules/missions/views/mission_tracking_screen.dart';
import '../../modules/missions/views/missions_screen.dart';
import '../../modules/notifications/bindings/notifications_binding.dart';
import '../../modules/notifications/views/notifications_screen.dart';
import '../../modules/rating/bindings/rating_binding.dart';
import '../../modules/rating/views/rating_screen.dart';
import '../../modules/score/bindings/score_binding.dart';
import '../../modules/score/views/score_screen.dart';
import '../../modules/settings/bindings/settings_binding.dart';
import '../../modules/settings/views/settings_screen.dart';
import '../../modules/settings/views/update_profile_screen.dart';
import '../../modules/home/bindings/home_binding.dart';
import '../../modules/home/views/artisan_map_screen.dart';
import '../../modules/services/views/services_screen.dart';
import '../../modules/missions/views/location_picker_screen.dart';
import '../../modules/missions/views/artisan_selection_screen.dart';
import '../../modules/missions/views/devis_creation_screen.dart';
import '../../modules/missions/views/devis_review_screen.dart';
import '../../modules/missions/views/referent_validation_screen.dart';
import '../../modules/artisans/views/parrainage_screen.dart';
import '../../modules/orders/views/order_checkout_screen.dart';
import '../../modules/orders/views/client_suppliers_list_screen.dart';
import '../../modules/orders/views/client_catalog_screen.dart';
import '../../modules/wallet/bindings/wallet_binding.dart';
import '../../modules/wallet/views/wallet_screen.dart';
import '../../modules/settings/views/legal_terms_screen.dart';
import 'app_routes.dart';

class AppPages {
  static final pages = [
    // Onboarding
    GetPage(
      name: Routes.splash,
      page: () => const SplashScreen(),
      binding: OnboardingBinding(),
    ),
    GetPage(
      name: Routes.onboarding,
      page: () => const OnboardingScreen(),
      binding: OnboardingBinding(),
    ),

    // Auth
    GetPage(
      name: Routes.login,
      page: () => const LoginScreen(),
      binding: AuthBinding(),
    ),
    GetPage(
      name: Routes.otpVerification,
      page: () => const OtpVerificationScreen(),
      binding: AuthBinding(),
    ),
    GetPage(
      name: Routes.register,
      page: () => const RegisterScreen(),
      binding: AuthBinding(),
    ),
    GetPage(
      name: Routes.kycCni,
      page: () => const KycCniCaptureScreen(),
      binding: AuthBinding(),
    ),
    GetPage(
      name: Routes.kycSelfie,
      page: () => const KycSelfieLivenessScreen(),
      binding: AuthBinding(),
    ),

    // Main tab
    GetPage(
      name: Routes.mainTab,
      page: () => const MainTabScreen(),
      binding: MainTabBinding(),
    ),

    // Missions
    GetPage(
      name: Routes.missions,
      page: () => const MissionsScreen(),
      binding: MissionsBinding(),
    ),
    GetPage(
      name: Routes.missionRequest,
      page: () => const MissionRequestScreen(),
      binding: MissionsBinding(),
    ),
    GetPage(
      name: Routes.missionTracking,
      page: () => const MissionTrackingScreen(),
      binding: MissionsBinding(),
    ),

    // Artisan
    GetPage(
      name: Routes.artisanProfile,
      page: () => const ArtisanProfileScreen(),
      binding: ArtisansBinding(),
    ),

    // Devis
    GetPage(
      name: Routes.quote,
      page: () => const QuoteScreen(),
      binding: DevisBinding(),
    ),
    GetPage(
      name: Routes.quoteBuilder,
      page: () => const QuoteBuilderScreen(),
      binding: DevisBinding(),
    ),

    // J-Code
    GetPage(
      name: Routes.jcode,
      page: () => const JcodeScreen(),
      binding: JcodeBinding(),
    ),
    GetPage(
      name: Routes.scanner,
      page: () => const ScannerScreen(),
      binding: JcodeBinding(),
    ),
    GetPage(
      name: Routes.jcodeServe,
      page: () => const JcodeServeScreen(),
      binding: JcodeBinding(),
    ),
    GetPage(
      name: Routes.supplierCatalog,
      page: () => const SupplierCatalogScreen(),
      binding: JcodeBinding(),
    ),
    GetPage(
      name: Routes.transactionConfirm,
      page: () => const TransactionConfirmScreen(),
      binding: JcodeBinding(),
    ),

    // Score
    GetPage(
      name: Routes.score,
      page: () => const ScoreScreen(),
      binding: ScoreBinding(),
    ),

    // Notifications
    GetPage(
      name: Routes.notifications,
      page: () => const NotificationsScreen(),
      binding: NotificationsBinding(),
    ),

    // Settings
    GetPage(
      name: Routes.settings,
      page: () => const SettingsScreen(),
      binding: SettingsBinding(),
    ),
    GetPage(
      name: Routes.updateProfile,
      page: () => const UpdateProfileScreen(),
      binding: SettingsBinding(),
    ),

    // Rating modal
    GetPage(
      name: Routes.rating,
      page: () => const RatingScreen(),
      binding: RatingBinding(),
    ),

    // Litige modal
    GetPage(
      name: Routes.litige,
      page: () => const LitigeScreen(),
      binding: LitigeBinding(),
    ),
    GetPage(
      name: Routes.litigeDetail,
      page: () => const LitigeDetailScreen(),
      binding: LitigeDetailBinding(),
    ),

    // Carte artisans
    GetPage(
      name: Routes.artisanMap,
      page: () => const ArtisanMapScreen(),
      binding: HomeBinding(),
    ),

    // Services
    GetPage(
      name: Routes.services,
      page: () => const ServicesScreen(),
    ),

    // Location Picker
    GetPage(
      name: Routes.locationPicker,
      page: () => const LocationPickerScreen(),
      binding: LocationPickerBinding(),
    ),

    // Artisan Selection
    GetPage(
      name: Routes.artisanSelection,
      page: () => const ArtisanSelectionScreen(),
      binding: MissionsBinding(),
    ),

    // Devis - Nouveau workflow
    GetPage(
      name: Routes.devisCreation,
      page: () => const DevisCreationScreen(),
      binding: MissionsBinding(),
    ),
    GetPage(
      name: Routes.devisReview,
      page: () => const DevisReviewScreen(),
      binding: MissionsBinding(),
    ),
    GetPage(
      name: Routes.referentValidation,
      page: () => const ReferentValidationScreen(),
      binding: BindingsBuilder.put(() => ReferentValidationController()),
    ),
    GetPage(
      name: Routes.parrainage,
      page: () => ParrainageScreen(),
    ),
    GetPage(
      name: Routes.orderCheckout,
      page: () => const OrderCheckoutScreen(),
    ),
    GetPage(
      name: Routes.clientSuppliers,
      page: () => const ClientSuppliersListScreen(),
    ),
    GetPage(
      name: Routes.clientCatalog,
      page: () => const ClientCatalogScreen(),
    ),
    GetPage(
      name: Routes.wallet,
      page: () => const WalletScreen(),
      binding: WalletBinding(),
    ),
    GetPage(
      name: Routes.legalTerms,
      page: () => const LegalTermsScreen(),
    ),
    GetPage(
      name: Routes.cgu,
      page: () => const LegalTermsScreen(initialTab: 0),
    ),
    GetPage(
      name: Routes.privacyPolicy,
      page: () => const LegalTermsScreen(initialTab: 1),
    ),
  ];
}

