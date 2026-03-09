import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/utils/debug_helper.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = Color(0xFF5B5FEF);
  static const primaryLight = Color(0xFF7C80F2);
  static const primaryDark = Color(0xFF4144D4);
  static const bg = Color(0xFFF5F6FA);
  static const surface = Colors.white;
  static const ink = Color(0xFF1A1D2E);
  static const muted = Color(0xFF6B7280);
  static const border = Color(0xFFE8EAF0);
  static const success = Color(0xFF10B981);
  static const error = Color(0xFFEF4444);
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
        // Debug button - remove in production or wrap with kDebugMode
        // floatingActionButton: FloatingActionButton(
        //   mini: true,
        //   backgroundColor: Colors.red,
        //   onPressed: () async {
        //     await DebugHelper.printStorageState();
        //     await DebugHelper.clearAllData();
        //     Get.snackbar(
        //       'Debug',
        //       'Storage cleared. Restart the app.',
        //       backgroundColor: Colors.red,
        //       colorText: Colors.white,
        //     );
        //   },
        //   child: const Icon(Icons.bug_report, size: 20),
        // ),
        body: FadeTransition(
          opacity: _fadeAnim ?? const AlwaysStoppedAnimation(1.0),
          child: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 8),
                  _buildWelcomeSection(),
                  const SizedBox(height: 28),
                  _buildProfileSelection(),
                  const SizedBox(height: 24),
                  _buildPhoneInput(),
                  const SizedBox(height: 18),
                  _buildKycNotice(),
                  const SizedBox(height: 24),

                  // Error message with animation
                  Obx(() {
                    if (_c.errorMsg.value == null)
                      return const SizedBox.shrink();
                    return AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      margin: const EdgeInsets.only(bottom: 16),
                      child: _buildError(_c.errorMsg.value!),
                    );
                  }),

                  _buildContinueButton(),
                  const SizedBox(height: 20),
                  _buildFooter(),
                  const SizedBox(height: 12),
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
        // Logo with subtle animation
        Hero(
          tag: 'app_logo',
          child: Image.asset(
            'assets/logo/logos.png',
            width: 100,
            height: 100,
          ),
        ),
        const SizedBox(height: 16),

        // Title with gradient
        ShaderMask(
          shaderCallback: (bounds) => const LinearGradient(
            colors: [_Dt.primary, _Dt.primaryLight],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ).createShader(bounds),
          child: const Text(
            'Bienvenue sur ProsArtisan',
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              color: Colors.white,
              letterSpacing: -0.5,
            ),
            textAlign: TextAlign.center,
          ),
        ),
        const SizedBox(height: 10),

        // Subtitle - more concise
        Text(
          'Connectez-vous pour accéder à vos services',
          style: TextStyle(
            fontSize: 14.5,
            color: _Dt.muted,
            fontWeight: FontWeight.w600,
            height: 1.4,
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
                  _c.role.value = 'client';
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
                  _c.role.value = 'artisan';
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
                  _c.role.value = 'fournisseur';
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
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
        decoration: BoxDecoration(
          color: isSelected ? _Dt.primary.withValues(alpha: 0.08) : _Dt.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? _Dt.primary : _Dt.border,
            width: isSelected ? 2.5 : 1.5,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: _Dt.primary.withValues(alpha: 0.2),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ]
              : [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 280),
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    gradient: isSelected
                        ? const LinearGradient(
                            colors: [_Dt.primary, _Dt.primaryLight],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                        : null,
                    color: isSelected ? null : _Dt.bg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Center(
                    child: Text(
                      emoji,
                      style: const TextStyle(fontSize: 28),
                    ),
                  ),
                ),
                if (isSelected)
                  Positioned(
                    top: -6,
                    right: -6,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [_Dt.primary, _Dt.primaryDark],
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: _Dt.primary.withValues(alpha: 0.4),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.check_rounded,
                        color: Colors.white,
                        size: 12,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              label,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
                color: isSelected ? _Dt.primary : _Dt.ink,
                letterSpacing: 0.4,
              ),
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
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
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: _Dt.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.phone_outlined, color: _Dt.primary, size: 18),
            ),
            const SizedBox(width: 10),
            const Text(
              'Numéro de téléphone',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: _Dt.ink,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
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
                child: Obx(() {
                  final hasContent = _c.phone.value.length > 4; // Use observable instead
                  return TextFormField(
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
                      hintText: '01 23 45 67 89',
                      hintStyle: TextStyle(
                        color: _Dt.muted.withValues(alpha: 0.4),
                        fontWeight: FontWeight.w500,
                        letterSpacing: 1.2,
                      ),
                      suffixIcon: hasContent
                          ? const Icon(
                              Icons.check_circle,
                              color: Color(0xFF10B981), // success color
                              size: 20,
                            )
                          : null,
                      counterText: '',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 16,
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
                        borderSide: BorderSide(color: _Dt.primary, width: 2.5),
                      ),
                      fillColor: _Dt.surface,
                      filled: true,
                    ),
                  );
                }),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ── KYC Notice ────────────────────────────────────────────────────────────

  Widget _buildKycNotice() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            _Dt.primary.withValues(alpha: 0.06),
            _Dt.primary.withValues(alpha: 0.03),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: _Dt.primary.withValues(alpha: 0.15),
          width: 1.5,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  _Dt.primary.withValues(alpha: 0.15),
                  _Dt.primary.withValues(alpha: 0.08),
                ],
              ),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.verified_user_outlined,
              color: _Dt.primary,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Vérification KYC requise',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: _Dt.ink,
                  ),
                ),
                const SizedBox(height: 6),
                RichText(
                  text: const TextSpan(
                    style: TextStyle(
                      fontSize: 12,
                      color: _Dt.muted,
                      fontWeight: FontWeight.w500,
                      height: 1.5,
                    ),
                    children: [
                      TextSpan(
                        text: 'Préparez votre ',
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
                        text: 'selfie',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: _Dt.primary,
                        ),
                      ),
                      TextSpan(
                        text: ' pour validation.',
                      ),
                    ],
                  ),
                ),
              ],
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

      return AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        height: 54,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          gradient: canContinue
              ? const LinearGradient(
                  colors: [_Dt.primary, _Dt.primaryLight],
                  begin: Alignment.centerLeft,
                  end: Alignment.centerRight,
                )
              : null,
          color: canContinue ? null : _Dt.border,
          boxShadow: canContinue
              ? [
                  BoxShadow(
                    color: _Dt.primary.withValues(alpha: 0.35),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: ElevatedButton(
          onPressed: canContinue
              ? (_c.isLoading.value ? null : _handleContinue)
              : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.transparent,
            foregroundColor: Colors.white,
            disabledBackgroundColor: Colors.transparent,
            disabledForegroundColor: _Dt.muted,
            shadowColor: Colors.transparent,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
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
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Text(
                      'Continuer',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3,
                      ),
                    ),
                    const SizedBox(width: 10),
                    AnimatedRotation(
                      duration: const Duration(milliseconds: 300),
                      turns: canContinue ? 0 : -0.25,
                      child: const Icon(Icons.arrow_forward_rounded, size: 20),
                    ),
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

  // ── Error Display ─────────────────────────────────────────────────────────

  Widget _buildError(String msg) {
    return TweenAnimationBuilder<double>(
      duration: const Duration(milliseconds: 400),
      tween: Tween(begin: 0.0, end: 1.0),
      curve: Curves.easeOutBack,
      builder: (context, value, child) {
        return Transform.scale(
          scale: value,
          child: Opacity(
            opacity: value,
            child: child,
          ),
        );
      },
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFEF2F2), Color(0xFFFEE2E2)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFFECACA), width: 1.5),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFFB91C1C).withValues(alpha: 0.1),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: const BoxDecoration(
                color: Color(0xFFB91C1C),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.error_outline_rounded,
                color: Colors.white,
                size: 16,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                msg,
                style: const TextStyle(
                  color: Color(0xFF7F1D1D),
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Footer ────────────────────────────────────────────────────────────────

  Widget _buildFooter() {
    return Column(
      children: [
        // Sign up prompt
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            const Text(
              'Nouveau sur ProsArtisan ? ',
              style: TextStyle(
                fontSize: 13.5,
                color: _Dt.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
            TextButton(
              onPressed: () {
                HapticFeedback.lightImpact();
                // Navigate to registration
              },
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: ShaderMask(
                shaderCallback: (bounds) => const LinearGradient(
                  colors: [_Dt.primary, _Dt.primaryLight],
                ).createShader(bounds),
                child: const Text(
                  'Créer un compte',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),

        // Help link
        InkWell(
          onTap: () {
            HapticFeedback.lightImpact();
            // Show KYC help
          },
          borderRadius: BorderRadius.circular(8),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.help_outline_rounded,
                  size: 16,
                  color: _Dt.muted.withValues(alpha: 0.8),
                ),
                const SizedBox(width: 6),
                Text(
                  'Aide sur la vérification KYC',
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: _Dt.muted.withValues(alpha: 0.8),
                    decoration: TextDecoration.underline,
                    decorationColor: _Dt.muted.withValues(alpha: 0.4),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
