import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/design_tokens.dart';
import '../../../../core/navigation/main_navigation_screen.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/widgets/widgets.dart';
import '../../../artisan/presentation/screens/artisan_dashboard_screen.dart';
import '../../../vendor/presentation/screens/vendor_dashboard_screen.dart';
import 'role_selection_screen.dart';

/// Login Screen following MinimalAuthMobile design system
/// Based on design_login.json specifications
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
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

      if (success && mounted) {
        final user = _authController.currentUser.value;

        if (user != null) {
          Get.snackbar(
            'Succès',
            'Bienvenue ${user.name}!',
            backgroundColor: DesignTokens.success,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
            duration: const Duration(seconds: 2),
          );

          Future.delayed(const Duration(milliseconds: 500), () {
            _navigateToHome(user.role);
          });
        }
      } else if (mounted) {
        Get.snackbar(
          'Erreur',
          _authController.errorMessage.value,
          backgroundColor: DesignTokens.error,
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
      backgroundColor: DesignTokens.backgroundScreen,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(
            horizontal: DesignTokens.horizontalPadding,
          ),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: DesignTokens.spacing32),

                // Logo
                Center(
                  child: Container(
                    width: DesignTokens.logoSize,
                    height: DesignTokens.logoSize,
                    decoration: const BoxDecoration(
                      color: DesignTokens.primaryBase,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.build_circle,
                      size: 36,
                      color: DesignTokens.textOnPrimary,
                    ),
                  ),
                ),
                const SizedBox(height: DesignTokens.spacing24),

                // Title
                Text(
                  'Bienvenue',
                  style: DesignTokens.headingStyle(),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: DesignTokens.spacing8),

                // Subtitle
                Text(
                  'Connectez-vous à votre compte',
                  style: DesignTokens.subheadingStyle(
                    color: DesignTokens.neutral500,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: DesignTokens.spacing32),

                // Social Login Buttons
                AppSocialButton(
                  text: 'Continuer avec Google',
                  icon: Image.asset(
                    'assets/icons/google.png',
                    width: 24,
                    height: 24,
                    errorBuilder: (context, error, stackTrace) {
                      return const Icon(
                        Icons.g_mobiledata,
                        color: DesignTokens.error,
                      );
                    },
                  ),
                  onPressed: () {
                    Get.snackbar(
                      'Info',
                      'Google Sign In à venir',
                      backgroundColor: DesignTokens.info,
                      colorText: Colors.white,
                    );
                  },
                ),
                const SizedBox(height: DesignTokens.spacing16),

                AppSocialButton(
                  text: 'Continuer avec Apple',
                  icon: const Icon(Icons.apple, color: Colors.black),
                  onPressed: () {
                    Get.snackbar(
                      'Info',
                      'Apple Sign In à venir',
                      backgroundColor: DesignTokens.info,
                      colorText: Colors.white,
                    );
                  },
                ),
                const SizedBox(height: DesignTokens.spacing24),

                // Divider
                const AppDividerWithLabel(label: 'OU'),
                const SizedBox(height: DesignTokens.spacing24),

                // Email Input
                AppTextField(
                  controller: _emailController,
                  hintText: 'exemple@email.com',
                  labelText: 'Email',
                  keyboardType: TextInputType.emailAddress,
                  prefixIcon: const Icon(Icons.email_outlined),
                  textInputAction: TextInputAction.next,
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
                const SizedBox(height: DesignTokens.spacing16),

                // Password Input
                AppTextField(
                  controller: _passwordController,
                  hintText: '••••••••',
                  labelText: 'Mot de passe',
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
                  textInputAction: TextInputAction.done,
                  onSubmitted: (_) => _handleLogin(),
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
                const SizedBox(height: DesignTokens.spacing12),

                // Forgot Password Link
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () {
                      Get.snackbar(
                        'Info',
                        'Réinitialisation du mot de passe à venir',
                        backgroundColor: DesignTokens.info,
                        colorText: Colors.white,
                      );
                    },
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        horizontal: DesignTokens.spacing8,
                        vertical: DesignTokens.spacing4,
                      ),
                      minimumSize: const Size(
                        DesignTokens.minimumTouchTarget,
                        DesignTokens.minimumTouchTarget,
                      ),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: Text(
                      'Mot de passe oublié?',
                      style: DesignTokens.linkStyle(),
                    ),
                  ),
                ),
                const SizedBox(height: DesignTokens.spacing24),

                // Login Button
                Obx(
                  () => AppPrimaryButton(
                    text: 'Se connecter',
                    onPressed: _handleLogin,
                    isLoading: _authController.isLoading.value,
                  ),
                ),
                const SizedBox(height: DesignTokens.spacing24),

                // Sign Up Link
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Pas encore de compte? ',
                      style: DesignTokens.bodyStyle(
                        color: DesignTokens.neutral700,
                      ),
                    ),
                    TextButton(
                      onPressed: () =>
                          Get.to(() => const RoleSelectionScreen()),
                      style: TextButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                          horizontal: DesignTokens.spacing4,
                        ),
                        minimumSize: const Size(
                          0,
                          DesignTokens.minimumTouchTarget,
                        ),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: Text(
                        'Créer un compte',
                        style: DesignTokens.linkStyle(),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: DesignTokens.spacing32),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
