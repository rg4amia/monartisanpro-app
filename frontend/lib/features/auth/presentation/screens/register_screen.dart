import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/network/trade_service.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/models/trade_model.dart';
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
  final _tradeService = TradeService();

  late String _selectedRole;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  // Artisan-specific state
  int? _selectedSectorId;
  int? _selectedTradeId;
  List<Sector> _sectors = [];
  List<Trade> _trades = [];
  bool _isLoadingSectors = false;
  bool _isLoadingTrades = false;

  Color get _roleColor => switch (_selectedRole) {
        'artisan' => AppColors.lightAccentSecondary,
        'fournisseur' => AppColors.lightAccentHighlight,
        _ => AppColors.lightAccentPrimary,
      };

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>?;
    _selectedRole = args?['role'] ?? 'client';
    _loadSectors();
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

  Future<void> _loadSectors() async {
    setState(() => _isLoadingSectors = true);
    try {
      final response = await _tradeService.getSectors();
      if (response.success && response.data != null) {
        setState(() => _sectors = response.data!);
      }
    } catch (_) {
      // Silently fail — sector dropdown will be empty
    } finally {
      setState(() => _isLoadingSectors = false);
    }
  }

  Future<void> _loadTradesBySector(int sectorId) async {
    setState(() {
      _isLoadingTrades = true;
      _selectedTradeId = null;
      _trades = [];
    });
    try {
      final response = await _tradeService.getTradesBySector(sectorId);
      if (response.success && response.data != null) {
        setState(() => _trades = response.data!);
      }
    } catch (_) {
      // Silently fail
    } finally {
      setState(() => _isLoadingTrades = false);
    }
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
        tradeId: _selectedTradeId,
      );

      if (success) {
        Get.snackbar(
          'Succès',
          'Compte créé avec succès!',
          backgroundColor: AppColors.success,
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
        Get.off(() => OtpVerificationScreen(
              phone:
                  '${AppConstants.countryCode}${_phoneController.text.trim()}',
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

                RoleSelectorWidget(
                  selectedRole: _selectedRole,
                  onRoleChanged: (role) {
                    setState(() {
                      _selectedRole = role;
                      // Reset artisan-specific fields when switching away
                      if (role != 'artisan') {
                        _selectedSectorId = null;
                        _selectedTradeId = null;
                        _trades = [];
                      }
                    });
                  },
                ),
                const SizedBox(height: Spacing.xl),

                // Artisan-specific: sector + trade
                if (_selectedRole == 'artisan') _buildArtisanSection(),

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
                      setState(
                        () => _obscureConfirmPassword =
                            !_obscureConfirmPassword,
                      );
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
                Obx(
                  () => AppPrimaryButton(
                    text: 'Créer mon compte',
                    onPressed: _handleRegister,
                    isLoading: _authController.isLoading.value,
                    color: _roleColor,
                  ),
                ),
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

  Widget _buildArtisanSection() {
    final inputDecoration = InputDecoration(
      filled: true,
      fillColor: AppColors.lightBackgroundSecondary,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        borderSide: BorderSide(
          color: AppColors.lightTextTertiary.withValues(alpha: 0.3),
        ),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        borderSide: BorderSide(
          color: AppColors.lightTextTertiary.withValues(alpha: 0.3),
        ),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        borderSide: const BorderSide(
          color: AppColors.lightAccentSecondary,
          width: 2,
        ),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        borderSide: const BorderSide(color: AppColors.error),
      ),
      contentPadding: const EdgeInsets.symmetric(
        horizontal: Spacing.base,
        vertical: Spacing.md,
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Sector Dropdown
        Text(
          "Secteur d'activité",
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
        ),
        const SizedBox(height: Spacing.sm),
        _isLoadingSectors
            ? const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: Spacing.base),
                  child: CircularProgressIndicator(),
                ),
              )
            : DropdownButtonFormField<int>(
                initialValue: _selectedSectorId,
                hint: const Text('Choisir un secteur'),
                decoration: inputDecoration,
                isExpanded: true,
                items: _sectors
                    .map(
                      (s) => DropdownMenuItem(value: s.id, child: Text(s.name)),
                    )
                    .toList(),
                onChanged: (id) {
                  if (id != null) {
                    setState(() => _selectedSectorId = id);
                    _loadTradesBySector(id);
                  }
                },
                validator: (_) {
                  if (_selectedSectorId == null) {
                    return 'Veuillez choisir un secteur';
                  }
                  return null;
                },
              ),
        const SizedBox(height: Spacing.lg),

        // Trade Dropdown — visible only when a sector is selected
        if (_selectedSectorId != null) ...[
          Text(
            'Métier',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: Spacing.sm),
          _isLoadingTrades
              ? const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: Spacing.base),
                    child: CircularProgressIndicator(),
                  ),
                )
              : DropdownButtonFormField<int>(
                  initialValue: _selectedTradeId,
                  hint: const Text('Choisir un métier'),
                  decoration: inputDecoration,
                  isExpanded: true,
                  items: _trades
                      .map(
                        (t) =>
                            DropdownMenuItem(value: t.id, child: Text(t.name)),
                      )
                      .toList(),
                  onChanged: (id) => setState(() => _selectedTradeId = id),
                  validator: (_) {
                    if (_selectedTradeId == null) {
                      return 'Veuillez choisir un métier';
                    }
                    return null;
                  },
                ),
          const SizedBox(height: Spacing.lg),
        ],
      ],
    );
  }
}
