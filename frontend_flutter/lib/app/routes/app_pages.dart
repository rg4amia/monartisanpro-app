import 'package:get/get.dart';

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
import '../../modules/jcode/views/transaction_confirm_screen.dart';
import '../../modules/litige/bindings/litige_binding.dart';
import '../../modules/litige/views/litige_screen.dart';
import '../../modules/main_tab/bindings/main_tab_binding.dart';
import '../../modules/main_tab/views/main_tab_screen.dart';
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
import 'app_routes.dart';

class AppPages {
  static final pages = [
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

    // Carte artisans
    GetPage(
      name: Routes.artisanMap,
      page: () => const ArtisanMapScreen(),
      binding: HomeBinding(),
    ),
  ];
}
