import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../features/auth/presentation/bindings/auth_binding.dart';
import '../../features/auth/presentation/pages/splash_page.dart';
import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/auth/presentation/pages/register_page.dart';
import '../../features/auth/presentation/pages/otp_verification_page.dart';
import '../../features/auth/presentation/pages/kyc_upload_page.dart';
import '../../features/auth/presentation/controllers/auth_controller.dart';
import '../middleware/auth_middleware.dart';
import 'app_routes.dart';

/// App pages configuration
class AppPages {
  static final routes = [
    // Splash screen (initial route)
    GetPage(
      name: AppRoutes.splash,
      page: () => const SplashPage(),
      binding: AuthBinding(),
    ),

    // Authentication pages (guest only)
    GetPage(
      name: AppRoutes.login,
      page: () => const LoginPage(),
      binding: AuthBinding(),
      middlewares: [GuestMiddleware()],
    ),
    GetPage(
      name: AppRoutes.register,
      page: () => const RegisterPage(),
      binding: AuthBinding(),
      middlewares: [GuestMiddleware()],
    ),
    GetPage(
      name: AppRoutes.otpVerification,
      page: () => OtpVerificationPage(phoneNumber: Get.arguments as String),
      binding: AuthBinding(),
      middlewares: [GuestMiddleware()],
    ),

    // KYC upload (authenticated but needs verification)
    GetPage(
      name: AppRoutes.kycUpload,
      page: () => KycUploadPage(userId: Get.arguments as String),
      binding: AuthBinding(),
    ),

    // Protected pages (authenticated only)
    GetPage(
      name: AppRoutes.home,
      page: () => const _PlaceholderHomePage(),
      binding: AuthBinding(),
      middlewares: [AuthMiddleware()],
    ),
  ];
}

/// Placeholder home page
class _PlaceholderHomePage extends StatelessWidget {
  const _PlaceholderHomePage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('ProSartisan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              final authController = Get.find<AuthController>();
              await authController.logout();
              Get.offAllNamed(AppRoutes.login);
            },
          ),
        ],
      ),
      body: const Center(child: Text('Bienvenue sur ProSartisan!')),
    );
  }
}
