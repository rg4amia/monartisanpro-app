import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../features/auth/presentation/controllers/auth_controller.dart';
import '../routes/app_routes.dart';

/// Middleware to protect authenticated routes
class AuthMiddleware extends GetMiddleware {
  @override
  int? get priority => 1;

  @override
  RouteSettings? redirect(String? route) {
    try {
      final authController = Get.find<AuthController>();

      // Prevent redirect loops - don't redirect if already on login
      if (route == AppRoutes.login) {
        return null;
      }

      // If user is not authenticated, redirect to login
      if (!authController.isAuthenticated.value) {
        return const RouteSettings(name: AppRoutes.login);
      }

      // If user needs KYC verification, redirect to KYC upload
      if (authController.needsKYCVerification) {
        return RouteSettings(
          name: AppRoutes.kycUpload,
          arguments: authController.currentUser.value?.id,
        );
      }

      // If account is not active, redirect to login with message
      if (!authController.isAccountActive) {
        authController.errorMessage.value =
            'Votre compte est suspendu. Contactez le support.';
        return const RouteSettings(name: AppRoutes.login);
      }

      return null; // Allow access to the route
    } catch (e) {
      // If AuthController is not found, redirect to splash for initialization
      return const RouteSettings(name: AppRoutes.splash);
    }
  }
}

/// Middleware to redirect authenticated users away from auth pages
class GuestMiddleware extends GetMiddleware {
  @override
  int? get priority => 1;

  @override
  RouteSettings? redirect(String? route) {
    try {
      final authController = Get.find<AuthController>();

      // Prevent redirect loops - don't redirect if already on home
      if (route == AppRoutes.home) {
        return null;
      }

      // If user is authenticated, redirect to home
      if (authController.isAuthenticated.value) {
        return const RouteSettings(name: AppRoutes.home);
      }

      return null; // Allow access to the route
    } catch (e) {
      // If AuthController is not found, allow access
      return null;
    }
  }
}
