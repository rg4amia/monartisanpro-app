import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/navigation/main_navigation_screen.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/widgets/widgets.dart';
import '../../../artisan/presentation/screens/artisan_dashboard_screen.dart';
import '../../../vendor/presentation/screens/vendor_dashboard_screen.dart';
import 'role_selection_screen.dart';

/// Login screen with background image
/// Figma reference: node-id=3112:6602
class LoginScreenWithBackground extends StatefulWidget {
  const LoginScreenWithBackground({super.key});

  @override
  State<LoginScreenWithBackground> createState() =>
      _LoginScreenWithBackgroundState();
}

class _LoginScreenWithBackgroundState extends State<LoginScreenWithBackground> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _authController = Get.put(AuthController());
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (_formKey.currentState!.validate()) {
      final success = await _authController.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );

      if (success) {
        final user = _authController.currentUser.value;

        if (user != null) {
          Get.snackbar(
            'Succès',
            'Bienvenue ${user.name}!',
            backgroundColor: AppColors.success,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
            duration: const Duration(seconds: 2),
          );

          Future.delayed(const Duration(milliseconds: 500), () {
            _navigateToHome(user.role);
          });
        } else {
          Get.snackbar(
            'Erreur',
            'Erreur de connexion, veuillez réessayer',
            backgroundColor: AppColors.error,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
          );
        }
      } else {
        Get.snackbar(
          'Error',
          _authController.errorMessage.value,
          backgroundColor: AppColors.error,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
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
      body: Stack(
        children: [
          // Background Image
          Positioned.fill(
            child: Image.asset(
              'assets/images/login_background.png',
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) {
                // Fallback to gradient if image not found
                return Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: AppColors.primaryGradient,
                    ),
                  ),
                );
              },
            ),
          ),

          // Gradient Overlay for better readability
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withOpacity(0.3),
                    Colors.black.withOpacity(0.6),
                  ],
                ),
              ),
            ),
          ),

          // Content
          SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(Spacing.screenPadding),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: Spacing.xxxl),

                    // Logo
                    Center(
                      child: Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.2),
                              blurRadius: 20,
                              spreadRadius: 0,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Icon(
                          Icons.build_circle,
                          size: 48,
                          color: AppColors.lightAccentPrimary,
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Title
                    Text(
                      'Bienvenue',
                      style: Theme.of(context).textTheme.headlineLarge
                          ?.copyWith(
                            fontSize: 32,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                            height: 1.2,
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: Spacing.sm),

                    // Subtitle
                    Text(
                      'Connectez-vous à votre compte',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontSize: 16,
                        color: Colors.white.withOpacity(0.9),
                        height: 1.5,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: Spacing.xxxl),

                    // Login Form Card
                    Container(
                      padding: const EdgeInsets.all(Spacing.xl),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(Spacing.radiusXl),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.1),
                            blurRadius: 20,
                            spreadRadius: 0,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          // Email Field
                          CustomTextField(
                            label: 'Email',
                            hint: 'exemple@email.com',
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            prefixIcon: const Icon(Icons.email_outlined),
                            validator: (value) {
                              if (value == null || value.isEmpty) {
                                return 'Veuillez entrer votre email';
                              }
                              if (!GetUtils.isEmail(value)) {
                                return 'Email invalide';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: Spacing.lg),

                          // Password Field
                          CustomTextField(
                            label: 'Mot de passe',
                            hint: '••••••••',
                            controller: _passwordController,
                            obscureText: _obscurePassword,
                            prefixIcon: const Icon(Icons.lock_outline),
                            suffixIcon: IconButton(
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                              onPressed: () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
                            ),
                            validator: (value) {
                              if (value == null || value.isEmpty) {
                                return 'Veuillez entrer votre mot de passe';
                              }
                              if (value.length < 8) {
                                return 'Le mot de passe doit contenir au moins 8 caractères';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: Spacing.md),

                          // Forgot Password
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(
                              onPressed: () {
                                // TODO: Navigate to forgot password
                              },
                              style: TextButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: Spacing.base,
                                  vertical: Spacing.sm,
                                ),
                              ),
                              child: Text(
                                'Mot de passe oublié?',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.lightAccentPrimary,
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: Spacing.lg),

                          // Login Button
                          Obx(
                            () => CustomButton(
                              text: 'Se connecter',
                              onPressed: _handleLogin,
                              isLoading: _authController.isLoading.value,
                              type: ButtonType.primary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Divider
                    Row(
                      children: [
                        Expanded(
                          child: Divider(
                            color: Colors.white.withOpacity(0.5),
                            thickness: 1,
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.base,
                          ),
                          child: Text(
                            'OU',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: Colors.white.withOpacity(0.8),
                                ),
                          ),
                        ),
                        Expanded(
                          child: Divider(
                            color: Colors.white.withOpacity(0.5),
                            thickness: 1,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Register Button
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        border: Border.all(
                          color: Colors.white.withOpacity(0.5),
                          width: 2,
                        ),
                      ),
                      child: TextButton(
                        onPressed: () =>
                            Get.to(() => const RoleSelectionScreen()),
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                            vertical: Spacing.base,
                          ),
                        ),
                        child: const Text(
                          'Créer un compte',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
