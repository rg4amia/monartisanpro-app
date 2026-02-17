import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/widgets/widgets.dart';
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
        Get.snackbar(
          'Success',
          'Welcome back!',
          backgroundColor: AppColors.success,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
        // TODO: Navigate to home screen
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
                Icon(
                  Icons.build_circle,
                  size: 80,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: Spacing.lg),

                Text(
                  'Bienvenue',
                  style: Theme.of(context).textTheme.headlineLarge,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: Spacing.sm),

                Text(
                  'Connectez-vous à votre compte',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.lightTextSecondary,
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
                    child: const Text('Mot de passe oublié?'),
                  ),
                ),
                const SizedBox(height: Spacing.xl),

                // Login Button
                Obx(() => CustomButton(
                      text: 'Se connecter',
                      onPressed: _handleLogin,
                      isLoading: _authController.isLoading.value,
                      type: ButtonType.primary,
                    )),
                const SizedBox(height: Spacing.xl),

                // Divider
                Row(
                  children: [
                    const Expanded(child: Divider()),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: Spacing.base,
                      ),
                      child: Text(
                        'OU',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                    const Expanded(child: Divider()),
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
