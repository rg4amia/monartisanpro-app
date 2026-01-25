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
import 'kyc_upload_page.dart';

/// Registration page for new users
class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _phoneController = TextEditingController();
  final _businessNameController = TextEditingController();

  String _selectedUserType = 'CLIENT';
  String? _selectedTradeCategory;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _phoneController.dispose();
    _businessNameController.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final authController = Get.find<AuthController>();

    final success = await authController.register(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      userType: _selectedUserType,
      phoneNumber: _phoneController.text.trim().isNotEmpty
          ? _phoneController.text.trim()
          : null,
      tradeCategory: _selectedTradeCategory,
      businessName: _businessNameController.text.trim().isNotEmpty
          ? _businessNameController.text.trim()
          : null,
    );

    if (success) {
      final user = authController.currentUser.value!;

      // If artisan or fournisseur, navigate to KYC upload
      if (user.isArtisan || user.isFournisseur) {
        Get.off(() => KycUploadPage(userId: user.id));
      } else {
        // For clients, navigate to home
        Get.offAllNamed('/home');
      }

      Get.snackbar(
        AppStrings.registrationSuccess,
        'Bienvenue sur ProSartisan!',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );
    } else {
      Get.snackbar(
        AppStrings.registrationFailed,
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
      appBar: AppBar(title: const Text(AppStrings.register)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24.0),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // User type selection
                Text(
                  AppStrings.selectUserType,
                  style: Theme.of(context).textTheme.titleMedium,
                ),

                const SizedBox(height: 16),

                _buildUserTypeCard(
                  'CLIENT',
                  AppStrings.client,
                  AppStrings.clientDescription,
                  Icons.person,
                ),

                const SizedBox(height: 12),

                _buildUserTypeCard(
                  'ARTISAN',
                  AppStrings.artisan,
                  AppStrings.artisanDescription,
                  Icons.construction,
                ),

                const SizedBox(height: 12),

                _buildUserTypeCard(
                  'FOURNISSEUR',
                  AppStrings.fournisseur,
                  AppStrings.fournisseurDescription,
                  Icons.store,
                ),

                const SizedBox(height: 24),

                // Email field
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                    labelText: AppStrings.email,
                    prefixIcon: const Icon(Icons.email),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
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

                const SizedBox(height: 16),

                // Phone number field
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: AppStrings.phoneNumber,
                    prefixIcon: const Icon(Icons.phone),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: (value) {
                    if (_selectedUserType != 'CLIENT' &&
                        (value == null || value.isEmpty)) {
                      return AppStrings.phoneRequired;
                    }
                    return null;
                  },
                ),

                const SizedBox(height: 16),

                // Trade category for artisans
                if (_selectedUserType == 'ARTISAN') ...[
                  DropdownButtonFormField<String>(
                    initialValue: _selectedTradeCategory,
                    decoration: InputDecoration(
                      labelText: AppStrings.tradeCategory,
                      prefixIcon: const Icon(Icons.work),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'PLUMBER',
                        child: Text(AppStrings.plumber),
                      ),
                      DropdownMenuItem(
                        value: 'ELECTRICIAN',
                        child: Text(AppStrings.electrician),
                      ),
                      DropdownMenuItem(
                        value: 'MASON',
                        child: Text(AppStrings.mason),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _selectedTradeCategory = value;
                      });
                    },
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return AppStrings.tradeCategoryRequired;
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                ],

                // Business name for fournisseurs
                if (_selectedUserType == 'FOURNISSEUR') ...[
                  TextFormField(
                    controller: _businessNameController,
                    decoration: InputDecoration(
                      labelText: AppStrings.businessName,
                      prefixIcon: const Icon(Icons.business),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return AppStrings.businessNameRequired;
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                ],

                // Password field
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: AppStrings.password,
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility
                            : Icons.visibility_off,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.passwordRequired;
                    }
                    if (value.length < 8) {
                      return AppStrings.passwordTooShort;
                    }
                    return null;
                  },
                ),

                const SizedBox(height: 16),

                // Confirm password field
                TextFormField(
                  controller: _confirmPasswordController,
                  obscureText: _obscureConfirmPassword,
                  decoration: InputDecoration(
                    labelText: AppStrings.confirmPassword,
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscureConfirmPassword
                            ? Icons.visibility
                            : Icons.visibility_off,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscureConfirmPassword = !_obscureConfirmPassword;
                        });
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.passwordRequired;
                    }
                    if (value != _passwordController.text) {
                      return AppStrings.passwordsDoNotMatch;
                    }
                    return null;
                  },
                ),

                const SizedBox(height: 24),

                // Register button
                Obx(
                  () => ElevatedButton(
                    onPressed: authController.isLoading.value
                        ? null
                        : _handleRegister,
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: authController.isLoading.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                Colors.white,
                              ),
                            ),
                          )
                        : const Text(
                            AppStrings.signUp,
                            style: TextStyle(fontSize: 16),
                          ),
                  ),
                ),

                const SizedBox(height: 16),

                // Login link
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Text(AppStrings.alreadyHaveAccount),
                    TextButton(
                      onPressed: () {
                        Get.back();
                      },
                      child: const Text(AppStrings.signIn),
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

  Widget _buildUserTypeCard(
    String value,
    String title,
    String description,
    IconData icon,
  ) {
    final isSelected = _selectedUserType == value;

    return InkWell(
      onTap: () {
        setState(() {
          _selectedUserType = value;
          if (value != 'ARTISAN') {
            _selectedTradeCategory = null;
          }
        });
      },
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          border: Border.all(
            color: isSelected ? Theme.of(context).primaryColor : Colors.grey,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(12),
          color: isSelected
              ? Theme.of(context).primaryColor.withValues(alpha: 0.1)
              : null,
        ),
        child: Row(
          children: [
            Icon(
              icon,
              size: 40,
              color: isSelected ? Theme.of(context).primaryColor : Colors.grey,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: isSelected ? Theme.of(context).primaryColor : null,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    description,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: Theme.of(context).primaryColor),
          ],
        ),
      ),
    );
  }
}
