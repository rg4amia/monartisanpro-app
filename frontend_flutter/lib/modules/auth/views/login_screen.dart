import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = Color(0xFF5B5FEF);
  static const bg = Color(0xFFF5F6FA);
  static const surface = Colors.white;
  static const ink = Color(0xFF1A1D2E);
  static const muted = Color(0xFF6B7280);
  static const border = Color(0xFFE8EAF0);
}

// ─── Login Screen ────────────────────────────────────────────────────────────

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with TickerProviderStateMixin {
  final _c = Get.find<AuthController>();

  final _phoneCtrl = TextEditingController();
  final _selectedProfile = Rx<String?>(null);

  // Animations
  AnimationController? _fadeCtrl;
  Animation<double>? _fadeAnim;

  // ── Animation setup ───────────────────────────────────────────────────────

  void _setupAnimations({bool jumpToEnd = false}) {
    _fadeCtrl?.dispose();

    _fadeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _fadeAnim = CurvedAnimation(parent: _fadeCtrl!, curve: Curves.easeOut);

    if (jumpToEnd) {
      _fadeCtrl!.value = 1.0;
    } else {
      Future.delayed(const Duration(milliseconds: 100), () {
        if (mounted) _fadeCtrl?.forward();
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _setupAnimations();
  }

  @override
  void reassemble() {
    super.reassemble();
    _setupAnimations(jumpToEnd: true);
  }

  @override
  void dispose() {
    _fadeCtrl?.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  // ── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: _Dt.bg,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          elevation: 0,
          title: const Text(
            'Accédez à votre compte',
            style: TextStyle(
              color: _Dt.ink,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          centerTitle: true,
        ),
        body: FadeTransition(
          opacity: _fadeAnim ?? const AlwaysStoppedAnimation(1.0),
          child: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 20),
                  _buildWelcomeSection(),
                  const SizedBox(height: 32),
                  _buildProfileSelection(),
                  const SizedBox(height: 32),
                  _buildPhoneInput(),
                  const SizedBox(height: 24),
                  _buildKycNotice(),
                  const SizedBox(height: 32),

                  // Error message
                  Obx(() {
                    if (_c.errorMsg.value == null)
                      return const SizedBox.shrink();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: _buildError(_c.errorMsg.value!),
                    );
                  }),

                  _buildContinueButton(),
                  const SizedBox(height: 24),
                  _buildFooter(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ── Welcome Section ───────────────────────────────────────────────────────

  Widget _buildWelcomeSection() {
    return Column(
      children: [
        const Text(
          'Bienvenue sur ProsArtisan',
          style: TextStyle(
            fontSize: 28,
            fontWeight: FontWeight.w900,
            color: _Dt.ink,
            letterSpacing: -0.5,
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        Text(
          'Choisissez votre profil pour continuer. Veuillez préparer votre CNI pour le processus d\'identification par selfie.',
          style: TextStyle(
            fontSize: 14,
            color: _Dt.muted,
            fontWeight: FontWeight.w500,
            height: 1.5,
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  // ── Profile Selection ─────────────────────────────────────────────────────

  Widget _buildProfileSelection() {
    return Obx(() => Row(
          children: [
            Expanded(
              child: _buildProfileCard(
                'CLIENT',
                Icons.person_outline_rounded,
                '👩‍💼',
                _selectedProfile.value == 'CLIENT',
                () {
                  _selectedProfile.value = 'CLIENT';
                  _c.role.value = 'CLIENT';
                  HapticFeedback.mediumImpact();
                },
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildProfileCard(
                'ARTISAN',
                Icons.construction_outlined,
                '👨‍🔧',
                _selectedProfile.value == 'ARTISAN',
                () {
                  _selectedProfile.value = 'ARTISAN';
                  _c.role.value = 'ARTISAN';
                  HapticFeedback.mediumImpact();
                },
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildProfileCard(
                'FOURNISSEUR',
                Icons.warehouse_outlined,
                '🏭',
                _selectedProfile.value == 'FOURNISSEUR',
                () {
                  _selectedProfile.value = 'FOURNISSEUR';
                  _c.role.value = 'FOURNISSEUR';
                  HapticFeedback.mediumImpact();
                },
              ),
            ),
          ],
        ));
  }

  Widget _buildProfileCard(
    String label,
    IconData icon,
    String emoji,
    bool isSelected,
    VoidCallback onTap,
  ) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 12),
        decoration: BoxDecoration(
          color: isSelected ? _Dt.primary.withValues(alpha: 0.08) : _Dt.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? _Dt.primary : _Dt.border,
            width: isSelected ? 2.5 : 1.5,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: _Dt.primary.withValues(alpha: 0.15),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: Column(
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: isSelected
                        ? _Dt.primary.withValues(alpha: 0.12)
                        : _Dt.bg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Center(
                    child: Text(
                      emoji,
                      style: const TextStyle(fontSize: 36),
                    ),
                  ),
                ),
                if (isSelected)
                  Positioned(
                    top: -4,
                    right: -4,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: _Dt.primary,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.check,
                        color: Colors.white,
                        size: 14,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: isSelected ? _Dt.primary : _Dt.ink,
                letterSpacing: 0.3,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Phone Input ───────────────────────────────────────────────────────────

  Widget _buildPhoneInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(Icons.phone_outlined, color: _Dt.primary, size: 20),
            const SizedBox(width: 8),
            const Text(
              'N° Téléphone',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: _Dt.ink,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
              decoration: BoxDecoration(
                color: _Dt.surface,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(12),
                  bottomLeft: Radius.circular(12),
                ),
                border: Border.all(color: _Dt.border, width: 1.5),
              ),
              child: const Row(
                children: [
                  Text('🇨🇮', style: TextStyle(fontSize: 20)),
                  SizedBox(width: 8),
                  Text(
                    '+225',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                      color: _Dt.ink,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: TextFormField(
                controller: _phoneCtrl,
                onChanged: (v) => _c.phone.value = '+225$v',
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                maxLength: 10,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: _Dt.ink,
                  letterSpacing: 1.2,
                ),
                decoration: InputDecoration(
                  hintText: '00 00 00 00 00',
                  hintStyle: TextStyle(
                    color: _Dt.muted.withValues(alpha: 0.4),
                    fontWeight: FontWeight.w500,
                    letterSpacing: 1.2,
                  ),
                  counterText: '',
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 18,
                  ),
                  border: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide(color: _Dt.border, width: 1.5),
                  ),
                  enabledBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide(color: _Dt.border, width: 1.5),
                  ),
                  focusedBorder: const OutlineInputBorder(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(12),
                      bottomRight: Radius.circular(12),
                    ),
                    borderSide: BorderSide(color: _Dt.primary, width: 2),
                  ),
                  fillColor: _Dt.surface,
                  filled: true,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  // ── KYC Notice ────────────────────────────────────────────────────────────

  Widget _buildKycNotice() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _Dt.primary.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: _Dt.primary.withValues(alpha: 0.2),
          width: 1,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: _Dt.primary.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.info_outline,
              color: _Dt.primary,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: RichText(
              text: const TextSpan(
                style: TextStyle(
                  fontSize: 13,
                  color: _Dt.ink,
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
                children: [
                  TextSpan(
                    text: 'Vérification sécurisée: ',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  TextSpan(
                    text: 'Vous devrez prendre une photo de votre',
                  ),
                  TextSpan(
                    text: 'CNI',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: _Dt.primary,
                    ),
                  ),
                  TextSpan(text: ' et un '),
                  TextSpan(
                    text: 'selfie en direct',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: _Dt.primary,
                    ),
                  ),
                  TextSpan(
                    text: ' pour activer votre profil professionnel.',
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── Continue Button ───────────────────────────────────────────────────────

  Widget _buildContinueButton() {
    return Obx(() {
      final canContinue =
          _selectedProfile.value != null && _c.phone.value.length >= 14;

      return SizedBox(
        width: double.infinity,
        height: 56,
        child: ElevatedButton(
          onPressed: canContinue
              ? (_c.isLoading.value ? null : _handleContinue)
              : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: canContinue ? _Dt.primary : _Dt.border,
            foregroundColor: Colors.white,
            disabledBackgroundColor: _Dt.border,
            disabledForegroundColor: _Dt.muted,
            elevation: canContinue ? 4 : 0,
            shadowColor: canContinue
                ? _Dt.primary.withValues(alpha: 0.3)
                : Colors.transparent,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
          child: _c.isLoading.value
              ? const SizedBox(
                  height: 22,
                  width: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    color: Colors.white,
                  ),
                )
              : const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Continue',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3,
                      ),
                    ),
                    SizedBox(width: 8),
                    Icon(Icons.arrow_forward, size: 20),
                  ],
                ),
        ),
      );
    });
  }

  void _handleContinue() async {
    if (_selectedProfile.value == null) return;

    HapticFeedback.mediumImpact();

    // Send OTP
    await _c.sendOtp();

    // If OTP sent successfully, navigate to OTP verification screen
    if (_c.otpSent.value && _c.errorMsg.value == null) {
      Get.toNamed(Routes.otpVerification, arguments: {
        'phone': _c.phone.value,
        'role': _selectedProfile.value,
      });
    }
  }

  // ── Footer ────────────────────────────────────────────────────────────────

  Widget _buildError(String msg) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFECACA), width: 1.5),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: const BoxDecoration(
              color: Color(0xFFFEE2E2),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.error_outline_rounded,
              color: Color(0xFFB91C1C),
              size: 17,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              msg,
              style: const TextStyle(
                color: Color(0xFF7F1D1D),
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── Footer ────────────────────────────────────────────────────────────────

  Widget _buildFooter() {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text(
              'New to N\'Zassa? ',
              style: TextStyle(
                fontSize: 14,
                color: _Dt.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
            TextButton(
              onPressed: () {
                // Navigate to registration
              },
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: const Text(
                'Create an account',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: _Dt.primary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.help_outline,
              size: 16,
              color: _Dt.muted,
            ),
            const SizedBox(width: 6),
            TextButton(
              onPressed: () {
                // Show KYC help
              },
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: const Text(
                'Need help with the KYC process?',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: _Dt.muted,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
