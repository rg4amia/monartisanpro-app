import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../features/auth/presentation/bindings/auth_binding.dart';
import '../../features/auth/presentation/pages/splash_page.dart';
import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/auth/presentation/pages/register_page.dart';
import '../../features/auth/presentation/pages/otp_verification_page.dart';
import '../../features/auth/presentation/pages/kyc_upload_page.dart';
import '../../features/auth/presentation/controllers/auth_controller.dart';
import '../../features/home/presentation/bindings/home_binding.dart';
import '../../features/home/presentation/pages/home_page.dart';
import '../../features/bookings/presentation/pages/bookings_page.dart';
import '../../features/categories/presentation/pages/categories_page.dart';
import '../../features/chat/presentation/pages/chat_page.dart';
import '../../features/profile/presentation/pages/profile_page.dart';
import '../../features/demo/presentation/pages/design_system_demo_page.dart';
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
      page: () => const HomePage(),
      binding: HomeBinding(),
      middlewares: [AuthMiddleware()],
    ),

    GetPage(
      name: AppRoutes.bookings,
      page: () => const BookingsPage(),
      middlewares: [AuthMiddleware()],
    ),

    GetPage(
      name: AppRoutes.categories,
      page: () => const CategoriesPage(),
      middlewares: [AuthMiddleware()],
    ),

    GetPage(
      name: AppRoutes.chat,
      page: () => const ChatPage(),
      middlewares: [AuthMiddleware()],
    ),

    GetPage(
      name: AppRoutes.profile,
      page: () => const ProfilePage(),
      middlewares: [AuthMiddleware()],
    ),

    // Demo page (for development)
    GetPage(name: AppRoutes.demo, page: () => const DesignSystemDemoPage()),
  ];
}
