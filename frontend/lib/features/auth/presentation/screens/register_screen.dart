import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/widgets/widgets.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmController = TextEditingController();
  final _authController = Get.find<AuthController>();

  late String _selectedRole;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  @override
  void initState() {
    super.initState();
    // Get role from navigation arguments or default to 'client'
    final args = Get.arguments as Map<String, dynamic>?;
    _selectedRole = args?['role'] ?? 'client';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _passwordConfirmController.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    if (_formKey.currentState!.validate()) {
      final success = await _authController.register(
        name: _nameController.text.trim(),
        email: _emailController.text.trim(),
        phone: _phoneController.text.trim(),
        password: _passwordController.text,
        passwordConfirmation: _passwordConfirmController.text,
        role: _selectedRole,
      );

      if (success) {
        Get.snackbar(
          'Succès',
          'Compte créé avec succès!',
          backgroundColor: AppColors.success,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
        // Navigate to OTP verification screen
        Get.off(() => OtpVerificationScreen(
              phone: '${AppConstants.countryCode}${_phoneController.text.trim()}',
            ));
      } else {
        Get.snackbar(
          'Erreur',
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
      appBar: const CustomAppBar(
        title: 'Créer un compte',
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: Spacing.lg),

                // Role Selection
                Text(
                  'Je suis:',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: Spacing.md),

                _buildRoleCard(
                  role: 'client',
                  title: 'Client',
                  description: 'Je cherche un artisan',
                  icon: Icons.person_outline,
                ),
                const SizedBox(height: Spacing.md),

                _buildRoleCard(
                  role: 'artisan',
                  title: 'Artisan',
                  description: 'Je propose mes services',
                  icon: Icons.build_outlined,
                ),
                const SizedBox(height: Spacing.md),

                _buildRoleCard(
                  role: 'fournisseur',
                  title: 'Fournisseur',
                  description: 'Je vends des matériaux',
                  icon: Icons.store_outlined,
                ),
                const SizedBox(height: Spacing.xl),

                // Name Field
                CustomTextField(
                  label: 'Nom complet',
                  hint: 'Jean Kouassi',
                  controller: _nameController,
                  prefixIcon: const Icon(Icons.person_outline),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Veuillez entrer votre nom';
                    }
                    if (value.length < 3) {
                      return 'Le nom doit contenir au moins 3 caractères';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.lg),

                // Phone Field
                CustomTextField(
                  label: 'Téléphone ${AppConstants.countryCode}',
                  hint: '0102030405',
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  prefixIcon: const Icon(Icons.phone_outlined),
                  maxLength: AppConstants.phoneLength,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Veuillez entrer votre numéro';
                    }
                    if (value.length != AppConstants.phoneLength) {
                      return 'Numéro invalide (${AppConstants.phoneLength} chiffres)';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.lg),

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
                      setState(() => _obscurePassword = !_obscurePassword);
                    },
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Veuillez entrer un mot de passe';
                    }
                    if (value.length < 8) {
                      return 'Minimum 8 caractères';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.lg),

                // Confirm Password Field
                CustomTextField(
                  label: 'Confirmer le mot de passe',
                  hint: '••••••••',
                  controller: _passwordConfirmController,
                  obscureText: _obscureConfirmPassword,
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscureConfirmPassword
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                    onPressed: () {
                      setState(() =>
                          _obscureConfirmPassword = !_obscureConfirmPassword);
                    },
                  ),
                  validator: (value) {
                    if (value != _passwordController.text) {
                      return 'Les mots de passe ne correspondent pas';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.xxxl),

                // Register Button
                Obx(() => CustomButton(
                      text: 'Créer mon compte',
                      onPressed: _handleRegister,
                      isLoading: _authController.isLoading.value,
                      type: ButtonType.primary,
                    )),
                const SizedBox(height: Spacing.lg),

                // Login Link
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Vous avez déjà un compte? ',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    TextButton(
                      onPressed: () => Get.back(),
                      child: const Text('Se connecter'),
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

  Widget _buildRoleCard({
    required String role,
    required String title,
    required String description,
    required IconData icon,
  }) {
    final isSelected = _selectedRole == role;

    return CustomCard(
      onTap: () => setState(() => _selectedRole = role),
      backgroundColor: isSelected
          ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
          : null,
      padding: const EdgeInsets.all(Spacing.base),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isSelected
                  ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.2)
                  : AppColors.lightBackground,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              icon,
              size: 28,
              color: isSelected
                  ? Theme.of(context).colorScheme.primary
                  : AppColors.lightTextSecondary,
            ),
          ),
          const SizedBox(width: Spacing.base),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: isSelected
                            ? Theme.of(context).colorScheme.primary
                            : null,
                        fontWeight: FontWeight.w600,
                      ),
                ),
                Text(
                  description,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          if (isSelected)
            Icon(
              Icons.check_circle,
              color: Theme.of(context).colorScheme.primary,
            ),
        ],
      ),
    );
  }
}
