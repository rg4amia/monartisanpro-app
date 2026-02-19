import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/widgets/widgets.dart';
import '../../../home/presentation/screens/home_screen.dart';
import '../../../artisan/presentation/screens/artisan_dashboard_screen.dart';
import '../../../vendor/presentation/screens/vendor_dashboard_screen.dart';
import 'role_selection_screen.dart';

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

      if (success) {
        // Navigate based on user role
        final user = _authController.currentUser.value;

        if (user != null) {
          print('User logged in: ${user.name}, Role: ${user.role}');

          Get.snackbar(
            'Succès',
            'Bienvenue ${user.name}!',
            backgroundColor: AppColors.success,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
            duration: const Duration(seconds: 2),
          );

          // Small delay to show snackbar before navigation
          Future.delayed(const Duration(milliseconds: 500), () {
            _navigateToHome(user.role);
          });
        } else {
          print('ERROR: Login success but user is null');
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
    print('Navigating to home for role: $role');

    final roleKey = role.toLowerCase().trim();
    print('Role key after processing: $roleKey');

    switch (roleKey) {
      case 'client':
        print('Navigating to HomeScreen');
        Get.offAll(() => const HomeScreen());
        break;
      case 'artisan':
        print('Navigating to ArtisanDashboardScreen');
        Get.offAll(() => const ArtisanDashboardScreen());
        break;
      case 'fournisseur':
        print('Navigating to VendorDashboardScreen');
        Get.offAll(() => const VendorDashboardScreen());
        break;
      default:
        print('Role not matched, using default HomeScreen. Role was: $roleKey');
        Get.offAll(() => const HomeScreen());
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark
          ? AppColors.darkBackground
          : AppColors.lightBackground,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: Spacing.xxxl),

                // Logo and Title
                Center(
                  child: Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: isDark
                            ? [
                                AppColors.darkAccentPrimary,
                                AppColors.darkAccentSecondary,
                              ]
                            : AppColors.primaryGradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color:
                              (isDark
                                      ? AppColors.darkAccentPrimary
                                      : AppColors.lightAccentPrimary)
                                  .withValues(alpha: 0.3),
                          blurRadius: 20,
                          spreadRadius: 0,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.build_circle,
                      size: 48,
                      color: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(height: Spacing.xl),

                Text(
                  'Bienvenue',
                  style: Theme.of(context).textTheme.headlineLarge?.copyWith(
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                    color: isDark
                        ? AppColors.darkTextPrimary
                        : AppColors.lightTextPrimary,
                    height: 1.2,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: Spacing.sm),

                Text(
                  'Connectez-vous à votre compte',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontSize: 16,
                    color: isDark
                        ? AppColors.darkTextSecondary
                        : AppColors.lightTextSecondary,
                    height: 1.5,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: Spacing.xxxl),

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
                        color: isDark
                            ? AppColors.darkAccentPrimary
                            : AppColors.lightAccentPrimary,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: Spacing.xl),

                // Login Button
                Obx(
                  () => CustomButton(
                    text: 'Se connecter',
                    onPressed: _handleLogin,
                    isLoading: _authController.isLoading.value,
                    type: ButtonType.primary,
                  ),
                ),
                const SizedBox(height: Spacing.xl),

                // Divider
                Row(
                  children: [
                    Expanded(
                      child: Divider(
                        color: isDark
                            ? AppColors.darkTextMuted
                            : AppColors.lightTextTertiary,
                        thickness: 1,
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Spacing.base,
                      ),
                      child: Text(
                        'OU',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: isDark
                              ? AppColors.darkTextTertiary
                              : AppColors.lightTextTertiary,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Divider(
                        color: isDark
                            ? AppColors.darkTextMuted
                            : AppColors.lightTextTertiary,
                        thickness: 1,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Spacing.xl),

                // Register Button
                CustomButton(
                  text: 'Créer un compte',
                  onPressed: () => Get.to(() => const RoleSelectionScreen()),
                  type: ButtonType.outline,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
