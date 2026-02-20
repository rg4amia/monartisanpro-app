import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/storage/preferences_manager.dart';
import '../../../../core/navigation/main_navigation_screen.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../artisan/presentation/screens/artisan_dashboard_screen.dart';
import '../../../vendor/presentation/screens/vendor_dashboard_screen.dart';
import 'onboarding_screen.dart';
import 'login_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;
  late Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();

    // Setup animations
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    );

    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _animationController,
        curve: const Interval(0.0, 0.5, curve: Curves.easeIn),
      ),
    );

    _scaleAnimation = Tween<double>(begin: 0.5, end: 1.0).animate(
      CurvedAnimation(
        parent: _animationController,
        curve: const Interval(0.0, 0.5, curve: Curves.easeOutBack),
      ),
    );

    _animationController.forward();

    // Check authentication status
    _checkAuthStatus();
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  Future<void> _checkAuthStatus() async {
    // Wait for animation to complete
    await Future.delayed(const Duration(milliseconds: 2000));

    final authController = Get.find<AuthController>();

    // Check if user is authenticated
    await authController.checkAuthStatus();

    if (authController.isAuthenticated.value &&
        authController.currentUser.value != null) {
      // User is authenticated, navigate to appropriate home screen
      final user = authController.currentUser.value!;
      print('Auto-login successful for: ${user.name}');
      _navigateToHome(user.role);
    } else {
      // User is not authenticated, check if they've seen onboarding
      final prefsManager = PreferencesManager();
      final hasSeenOnboarding = await prefsManager.hasSeenOnboarding();

      if (hasSeenOnboarding) {
        // Skip onboarding, go directly to login
        Get.offAll(() => const LoginScreen());
      } else {
        // Show onboarding for first-time users
        Get.offAll(() => const OnboardingScreen());
      }
    }
  }

  void _navigateToHome(String role) {
    final roleKey = role.toLowerCase().trim();

    switch (roleKey) {
      case 'client':
        Get.offAll(() => const MainNavigationScreen());
        break;
      case 'artisan':
        Get.offAll(() => const ArtisanDashboardScreen());
        break;
      case 'fournisseur':
      case 'vendor':
        Get.offAll(() => const VendorDashboardScreen());
        break;
      default:
        Get.offAll(() => const MainNavigationScreen());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: AppColors.primaryGradient,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Center(
          child: FadeTransition(
            opacity: _fadeAnimation,
            child: ScaleTransition(
              scale: _scaleAnimation,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Logo
                  Container(
                    width: 120,
                    height: 120,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.2),
                          blurRadius: 20,
                          spreadRadius: 5,
                        ),
                      ],
                    ),
                    child: Icon(
                      Icons.build_circle,
                      size: 70,
                      color: AppColors.lightAccentPrimary,
                    ),
                  ),
                  const SizedBox(height: 30),

                  // App Name
                  Text(
                    'ProsArtisan',
                    style: Theme.of(context).textTheme.headlineLarge?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 32,
                    ),
                  ),
                  const SizedBox(height: 10),

                  // Tagline
                  Text(
                    'Votre artisan de confiance',
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: Colors.white.withValues(alpha: 0.9),
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 50),

                  // Loading indicator
                  SizedBox(
                    width: 40,
                    height: 40,
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(
                        Colors.white.withValues(alpha: 0.8),
                      ),
                      strokeWidth: 3,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
