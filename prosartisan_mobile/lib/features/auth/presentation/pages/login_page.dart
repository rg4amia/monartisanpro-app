import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/buttons/secondary_button.dart';
import '../controllers/auth_controller.dart';
import 'register_page.dart';

/// Login page for user authentication
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final authController = Get.find<AuthController>();

    final success = await authController.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
    );

    if (success) {
      // Navigate to home page
      Get.offAllNamed('/home');
    } else {
      // Show error message
      Get.snackbar(
        AppStrings.loginFailed,
        authController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authController = Get.find<AuthController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(height: AppSpacing.xl * 2),

                // Logo
                Icon(Icons.construction, size: 80, color: AppColors.primary),

                SizedBox(height: AppSpacing.md),

                // App name
                Text(
                  AppStrings.appName,
                  style: AppTypography.headingLarge.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  textAlign: TextAlign.center,
                ),

                SizedBox(height: AppSpacing.sm),

                // Login title
                Text(
                  AppStrings.login,
                  style: AppTypography.headingMedium.copyWith(
                    color: AppColors.textSecondary,
                  ),
                  textAlign: TextAlign.center,
                ),

                SizedBox(height: AppSpacing.xl * 2),

                // Email field
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  style: AppTypography.bodyMedium.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.email,
                    labelStyle: AppTypography.bodyMedium.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.email,
                      color: AppColors.textSecondary,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(
                        color: AppColors.primary,
                        width: 2,
                      ),
                    ),
                    filled: true,
                    fillColor: AppColors.surface,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.emailRequired;
                    }
                    if (!GetUtils.isEmail(value)) {
                      return AppStrings.emailInvalid;
                    }
                    return null;
                  },
                ),

                SizedBox(height: AppSpacing.md),

                // Password field
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  style: AppTypography.bodyMedium.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.password,
                    labelStyle: AppTypography.bodyMedium.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.lock,
                      color: AppColors.textSecondary,
                    ),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility
                            : Icons.visibility_off,
                        color: AppColors.textSecondary,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      borderSide: BorderSide(
                        color: AppColors.primary,
                        width: 2,
                      ),
                    ),
                    filled: true,
                    fillColor: AppColors.surface,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.passwordRequired;
                    }
                    return null;
                  },
                ),

                SizedBox(height: AppSpacing.lg),

                // Login button
                Obx(
                  () => PrimaryButton(
                    onPressed: authController.isLoading.value
                        ? null
                        : _handleLogin,
                    text: AppStrings.signIn,
                    isLoading: authController.isLoading.value,
                    isFullWidth: true,
                  ),
                ),

                SizedBox(height: AppSpacing.md),

                // Register link
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      AppStrings.dontHaveAccount,
                      style: AppTypography.bodyMedium.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    SecondaryButton(
                      onPressed: () {
                        Get.to(() => const RegisterPage());
                      },
                      text: AppStrings.signUp,
                      isCompact: true,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
