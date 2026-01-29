import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/controllers/trade_controller.dart';
import '../controllers/auth_controller.

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
        backgroundColor: AppColors.accentSuccess,
        colorText: AppColors.textPrimary,
      );
    } else {
      Get.snackbar(
        AppStrings.registrationFailed,
        authController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: AppColors.textPrimary,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authController = Get.find<AuthController>();

    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          AppStrings.register,
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(AppSpacing.screenPadding),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // User type selection
                Text(
                  AppStrings.selectUserType,
                  style: AppTypography.sectionTitle.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),

                SizedBox(height: AppSpacing.base),

                _buildUserTypeCard(
                  'CLIENT',
                  AppStrings.client,
                  AppStrings.clientDescription,
                  Icons.person,
                ),

                SizedBox(height: AppSpacing.md),

                _buildUserTypeCard(
                  'ARTISAN',
                  AppStrings.artisan,
                  AppStrings.artisanDescription,
                  Icons.construction,
                ),

                SizedBox(height: AppSpacing.md),

                _buildUserTypeCard(
                  'FOURNISSEUR',
                  AppStrings.fournisseur,
                  AppStrings.fournisseurDescription,
                  Icons.store,
                ),

                SizedBox(height: AppSpacing.xl),

                // Email field
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.email,
                    labelStyle: AppTypography.body.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.email,
                      color: AppColors.textSecondary,
                    ),
                    filled: true,
                    fillColor: AppColors.cardBg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(
                        color: AppColors.accentPrimary,
                        width: 2,
                      ),
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

                SizedBox(height: AppSpacing.base),

                // Phone number field
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.phoneNumber,
                    labelStyle: AppTypography.body.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.phone,
                      color: AppColors.textSecondary,
                    ),
                    filled: true,
                    fillColor: AppColors.cardBg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(
                        color: AppColors.accentPrimary,
                        width: 2,
                      ),
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

                SizedBox(height: AppSpacing.base),

                // Trade category for artisans
                if (_selectedUserType == 'ARTISAN') ...[
                  DropdownButtonFormField<String>(
                    initialValue: _selectedTradeCategory,
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                    ),
                    dropdownColor: AppColors.cardBg,
                    decoration: InputDecoration(
                      labelText: AppStrings.tradeCategory,
                      labelStyle: AppTypography.body.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      prefixIcon: Icon(
                        Icons.work,
                        color: AppColors.textSecondary,
                      ),
                      filled: true,
                      fillColor: AppColors.cardBg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(
                          color: AppColors.accentPrimary,
                          width: 2,
                        ),
                      ),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 'PLUMBER',
                        child: Text(
                          AppStrings.plumber,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      DropdownMenuItem(
                        value: 'ELECTRICIAN',
                        child: Text(
                          AppStrings.electrician,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      DropdownMenuItem(
                        value: 'MASON',
                        child: Text(
                          AppStrings.mason,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
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
                  SizedBox(height: AppSpacing.base),
                ],

                // Business name for fournisseurs
                if (_selectedUserType == 'FOURNISSEUR') ...[
                  TextFormField(
                    controller: _businessNameController,
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                    ),
                    decoration: InputDecoration(
                      labelText: AppStrings.businessName,
                      labelStyle: AppTypography.body.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      prefixIcon: Icon(
                        Icons.business,
                        color: AppColors.textSecondary,
                      ),
                      filled: true,
                      fillColor: AppColors.cardBg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(AppRadius.md),
                        borderSide: BorderSide(
                          color: AppColors.accentPrimary,
                          width: 2,
                        ),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return AppStrings.businessNameRequired;
                      }
                      return null;
                    },
                  ),
                  SizedBox(height: AppSpacing.base),
                ],

                // Password field
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.password,
                    labelStyle: AppTypography.body.copyWith(
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
                    filled: true,
                    fillColor: AppColors.cardBg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(
                        color: AppColors.accentPrimary,
                        width: 2,
                      ),
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

                SizedBox(height: AppSpacing.base),

                // Confirm password field
                TextFormField(
                  controller: _confirmPasswordController,
                  obscureText: _obscureConfirmPassword,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.confirmPassword,
                    labelStyle: AppTypography.body.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.lock,
                      color: AppColors.textSecondary,
                    ),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscureConfirmPassword
                            ? Icons.visibility
                            : Icons.visibility_off,
                        color: AppColors.textSecondary,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscureConfirmPassword = !_obscureConfirmPassword;
                        });
                      },
                    ),
                    filled: true,
                    fillColor: AppColors.cardBg,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      borderSide: BorderSide(
                        color: AppColors.accentPrimary,
                        width: 2,
                      ),
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

                SizedBox(height: AppSpacing.lg),

                // Register button
                Obx(
                  () => PrimaryButton(
                    onPressed: authController.isLoading.value
                        ? null
                        : _handleRegister,
                    text: AppStrings.signUp,
                    isLoading: authController.isLoading.value,
                  ),
                ),

                SizedBox(height: AppSpacing.base),

                // Login link
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      AppStrings.alreadyHaveAccount,
                      style: AppTypography.body.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: () {
                          Get.back();
                        },
                        borderRadius: AppRadius.buttonRadius,
                        child: Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: AppSpacing.sm,
                            vertical: AppSpacing.xs,
                          ),
                          child: Text(
                            AppStrings.signIn,
                            style: AppTypography.button.copyWith(
                              color: AppColors.accentPrimary,
                            ),
                          ),
                        ),
                      ),
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
      borderRadius: AppRadius.cardRadius,
      child: Container(
        padding: EdgeInsets.all(AppSpacing.base),
        decoration: BoxDecoration(
          border: Border.all(
            color: isSelected
                ? AppColors.accentPrimary
                : AppColors.overlayMedium,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: AppRadius.cardRadius,
          color: isSelected
              ? AppColors.accentPrimary.withValues(alpha: 0.1)
              : AppColors.cardBg,
        ),
        child: Row(
          children: [
            Icon(
              icon,
              size: 40,
              color: isSelected
                  ? AppColors.accentPrimary
                  : AppColors.textSecondary,
            ),
            SizedBox(width: AppSpacing.base),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTypography.sectionTitle.copyWith(
                      fontWeight: FontWeight.bold,
                      color: isSelected
                          ? AppColors.accentPrimary
                          : AppColors.textPrimary,
                    ),
                  ),
                  SizedBox(height: AppSpacing.xs),
                  Text(
                    description,
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: AppColors.accentPrimary),
          ],
        ),
      ),
    );
  }
}
