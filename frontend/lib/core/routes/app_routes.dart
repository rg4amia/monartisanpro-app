import 'package:get/get.dart';
import '../../features/auth/presentation/screens/login_screen.dart';
import '../../features/auth/presentation/screens/onboarding_screen.dart';
import '../../features/auth/presentation/screens/role_selection_screen.dart';
import '../../features/home/presentation/screens/home_screen.dart';
import '../../features/artisan/presentation/screens/artisan_dashboard_screen.dart';
import '../../features/vendor/presentation/screens/vendor_dashboard_screen.dart';

class AppRoutes {
  static const String onboarding = '/onboarding';
  static const String login = '/login';
  static const String roleSelection = '/role-selection';
  static const String home = '/home';
  static const String artisanDashboard = '/artisan-dashboard';
  static const String vendorDashboard = '/vendor-dashboard';

  static List<GetPage> routes = [
    GetPage(name: onboarding, page: () => const OnboardingScreen()),
    GetPage(name: login, page: () => const LoginScreen()),
    GetPage(name: roleSelection, page: () => const RoleSelectionScreen()),
    GetPage(name: home, page: () => const HomeScreen()),
    GetPage(name: artisanDashboard, page: () => const ArtisanDashboardScreen()),
    GetPage(name: vendorDashboard, page: () => const VendorDashboardScreen()),
  ];

  /// Navigate to appropriate home screen based on user role
  static void navigateToRoleHome(String role) {
    switch (role.toLowerCase()) {
      case 'client':
        Get.offAllNamed(home);
        break;
      case 'artisan':
        Get.offAllNamed(artisanDashboard);
        break;
      case 'fournisseur':
        Get.offAllNamed(vendorDashboard);
        break;
      default:
        Get.offAllNamed(home);
    }
  }
}
