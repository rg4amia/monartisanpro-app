import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
// Pour d'éventuels globals
import '../../../data/services/app_settings_service.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.primaryLight;
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const border = AppColors.border;
  static const success = AppColors.success;
  static const client = AppColors.client;
  static const artisan = AppColors.accent;
  static const fournisseur = AppColors.success;
  static const driver = AppColors.driver;
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
        body: Stack(
          children: [
            Positioned(
              top: -80,
              left: -40,
              child: _GlowBubble(
                size: 180,
                color: AppColors.client.withValues(alpha: 0.12),
              ),
            ),
            Positioned(
              top: 110,
              right: -70,
              child: _GlowBubble(
                size: 220,
                color: AppColors.accent.withValues(alpha: 0.10),
              ),
            ),
            Positioned(
              bottom: -90,
              left: 30,
              child: _GlowBubble(
                size: 200,
                color: AppColors.success.withValues(alpha: 0.10),
              ),
            ),
            FadeTransition(
              opacity: _fadeAnim ?? const AlwaysStoppedAnimation(1.0),
              child: SafeArea(
                child: SingleChildScrollView(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
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
                      Obx(() {
                        if (_c.errorMsg.value == null) {
                          return const SizedBox.shrink();
                        }
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 300),
                          margin: const EdgeInsets.only(bottom: 16),
                          child: _buildError(_c.errorMsg.value!),
                        );
                      }),
                      _buildContinueButton(),
                      const SizedBox(height: 12),
                      _buildResetLink(),
                      const SizedBox(height: 12),
                      _buildFooter(),
                      const SizedBox(height: 12),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Welcome Section ───────────────────────────────────────────────────────

  Widget _buildWelcomeSection() {
    return Column(
      children: [
        // Logo with glow & subtle animation
        Hero(
          tag: 'app_logo',
          child: Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.15),
                  blurRadius: 24,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Image.asset(
              'assets/logo/logos.png',
              width: 100,
              height: 100,
            ),
          ),
        ),
        const SizedBox(height: 16),

        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color: AppColors.secondary,
            borderRadius: BorderRadius.circular(999),
          ),
          child: const Text(
            'Client, artisan ou fournisseur',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: _Dt.primary,
              letterSpacing: 0.4,
            ),
          ),
        ),
        const SizedBox(height: 14),
        ShaderMask(
          shaderCallback: (bounds) => const LinearGradient(
            colors: [Color(0xFF1E1E1E), Color(0xFFC0842C)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ).createShader(bounds),
          child: const Text(
            'Bienvenue sur ProsArtisan',
            style: TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w900,
              color: Colors.white,
              letterSpacing: -0.8,
              height: 1.1,
            ),
            textAlign: TextAlign.center,
          ),
        ),
        const SizedBox(height: 10),

        Text(
          'Accédez à votre espace sécurisé pour gérer vos missions, vos paiements et votre suivi KYC.',
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
    final appSettings = Get.find<AppSettingsService>();

    return Obx(() {
      final List<Widget> visibleCards = [];

      if (!appSettings.isHidden('CLIENT')) {
        visibleCards.add(
          _buildProfileCard(
            'CLIENT',
            Icons.person_outline_rounded,
            '👩‍💼',
            _selectedProfile.value == 'CLIENT',
            appSettings.isBlocked('CLIENT', isNewUser: false),
            () {
              _selectedProfile.value = 'CLIENT';
              _c.role.value = 'client';
              HapticFeedback.mediumImpact();
            },
          ),
        );
      }

      if (!appSettings.isHidden('ARTISAN')) {
        visibleCards.add(
          _buildProfileCard(
            'ARTISAN',
            Icons.construction_outlined,
            '👨‍🔧',
            _selectedProfile.value == 'ARTISAN',
            appSettings.isBlocked('ARTISAN', isNewUser: false),
            () {
              _selectedProfile.value = 'ARTISAN';
              _c.role.value = 'artisan';
              HapticFeedback.mediumImpact();
            },
          ),
        );
      }

      if (!appSettings.isHidden('FOURNISSEUR')) {
        visibleCards.add(
          _buildProfileCard(
            'FOURNISSEUR',
            Icons.warehouse_outlined,
            '🏭',
            _selectedProfile.value == 'FOURNISSEUR',
            appSettings.isBlocked('FOURNISSEUR', isNewUser: false),
            () {
              _selectedProfile.value = 'FOURNISSEUR';
              _c.role.value = 'fournisseur';
              HapticFeedback.mediumImpact();
            },
          ),
        );
      }

      if (!appSettings.isHidden('LIVREUR')) {
        visibleCards.add(
          _buildProfileCard(
            'LIVREUR',
            Icons.local_shipping_outlined,
            '🚚',
            _selectedProfile.value == 'LIVREUR',
            appSettings.isBlocked('LIVREUR', isNewUser: false),
            () {
              _selectedProfile.value = 'LIVREUR';
              _c.role.value = 'driver';
              HapticFeedback.mediumImpact();
            },
          ),
        );
      }

      if (visibleCards.isEmpty) {
        return const Center(
          child: Padding(
            padding: EdgeInsets.all(16.0),
            child: Text(
              'Aucune option d\'accès disponible actuellement.',
              style: TextStyle(
                color: Colors.red,
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
              textAlign: TextAlign.center,
            ),
          ),
        );
      }

      // Automatically centers and justifies the remaining icons depending on visibility status
      final screenWidth = MediaQuery.of(context).size.width;
      // 40 is horizontal padding (20 on each side), 12 is the spacing between cards
      final double itemWidth = (screenWidth - 40 - 12) / 2;
      // We want to preserve aspect ratio of 1.25, so height = width / 1.25
      final double itemHeight = itemWidth / 1.25;

      // 4 items: 2x2 Grid
      if (visibleCards.length == 4) {
        return GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.25,
          children: visibleCards,
        );
      }

      // 3 items: 2 on first row, 1 centered on second row
      if (visibleCards.length == 3) {
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(child: SizedBox(height: itemHeight, child: visibleCards[0])),
                const SizedBox(width: 12),
                Expanded(child: SizedBox(height: itemHeight, child: visibleCards[1])),
              ],
            ),
            const SizedBox(height: 12),
            Center(
              child: SizedBox(
                width: itemWidth,
                height: itemHeight,
                child: visibleCards[2],
              ),
            ),
          ],
        );
      }

      // 2 items: 1 row of 2
      if (visibleCards.length == 2) {
        return Row(
          children: [
            Expanded(child: SizedBox(height: itemHeight, child: visibleCards[0])),
            const SizedBox(width: 12),
            Expanded(child: SizedBox(height: itemHeight, child: visibleCards[1])),
          ],
        );
      }

      // 1 item: centered
      return Center(
        child: SizedBox(
          width: itemWidth,
          height: itemHeight,
          child: visibleCards[0],
        ),
      );
    });
  }

  Widget _buildProfileCard(
    String label,
    IconData icon,
    String emoji,
    bool isSelected,
    bool isBlocked,
    VoidCallback onTap,
  ) {
    final appSettings = Get.find<AppSettingsService>();
    final accent = _roleColor(label);
    return GestureDetector(
      onTap: isBlocked ? () {
        Get.snackbar(
          'Accès désactivé', 
          appSettings.getDisabledMessage(label),
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: Colors.red.withValues(alpha: 0.9),
          colorText: Colors.white,
          margin: const EdgeInsets.all(16),
        );
      } : onTap,
      child: AnimatedScale(
        scale: isSelected ? 1.03 : 1.0,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutBack,
        child: Opacity(
          opacity: isBlocked ? 0.4 : 1.0,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 280),
            curve: Curves.easeOutCubic,
            transform: isSelected
                ? Matrix4.translationValues(0, -5, 0)
                : Matrix4.identity(),
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
            decoration: BoxDecoration(
              color: isSelected ? accent.withValues(alpha: 0.10) : _Dt.surface,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: isSelected ? accent : _Dt.border,
                width: isSelected ? 2.5 : 1.5,
              ),
              boxShadow: isSelected
                  ? [
                      BoxShadow(
                        color: accent.withValues(alpha: 0.20),
                        blurRadius: 18,
                        offset: const Offset(0, 8),
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
                          ? LinearGradient(
                              colors: [accent, accent.withValues(alpha: 0.82)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            )
                          : null,
                      color: isSelected ? null : _Dt.bg,
                      borderRadius: BorderRadius.circular(14),
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
                          color: accent,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: accent.withValues(alpha: 0.35),
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
                  if (isBlocked)
                    Positioned(
                      top: -6,
                      right: -6,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: Colors.red.shade400,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.red.withValues(alpha: 0.3),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.lock_outline_rounded,
                          color: Colors.white,
                          size: 14,
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
                  fontWeight: FontWeight.w900,
                  color: isSelected ? accent : _Dt.ink,
                  letterSpacing: 0.4,
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
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
            Obx(() {
              final activeColor = _roleColor(_selectedProfile.value);
              return Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: activeColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  Icons.phone_outlined,
                  color: activeColor,
                  size: 18,
                ),
              );
            }),
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
        Obx(() {
          final activeColor = _roleColor(_selectedProfile.value);
          final hasContent = _c.phone.value.length > 4;
          return Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: activeColor.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
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
                      topLeft: Radius.circular(16),
                      bottomLeft: Radius.circular(16),
                    ),
                    border: Border.all(
                      color: _selectedProfile.value != null ? activeColor.withValues(alpha: 0.6) : _Dt.border,
                      width: 1.5,
                    ),
                  ),
                  child: const Row(
                    children: [
                      Text('🇨🇮', style: TextStyle(fontSize: 20)),
                      SizedBox(width: 8),
                      Text(
                        '+225',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
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
                      hintText: '01 23 45 67 89',
                      hintStyle: TextStyle(
                        color: _Dt.muted.withValues(alpha: 0.4),
                        fontWeight: FontWeight.w500,
                        letterSpacing: 1.2,
                      ),
                      suffixIcon: hasContent
                          ? Icon(
                              Icons.check_circle,
                              color: _roleColor(_selectedProfile.value),
                              size: 20,
                            )
                          : null,
                      counterText: '',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 16,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: _selectedProfile.value != null ? activeColor.withValues(alpha: 0.6) : _Dt.border,
                          width: 1.5,
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: _selectedProfile.value != null ? activeColor.withValues(alpha: 0.6) : _Dt.border,
                          width: 1.5,
                        ),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(color: activeColor, width: 2.5),
                      ),
                      fillColor: _Dt.surface,
                      filled: true,
                    ),
                  ),
                ),
              ],
            ),
          );
        }),
      ],
    );
  }

  // ── KYC Notice ────────────────────────────────────────────────────────────

  Widget _buildKycNotice() {
    return Obx(() {
      final activeColor = _roleColor(_selectedProfile.value);
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              activeColor.withValues(alpha: 0.06),
              activeColor.withValues(alpha: 0.02),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: activeColor.withValues(alpha: 0.18),
            width: 1.5,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(7),
              decoration: BoxDecoration(
                color: activeColor.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.verified_user_outlined,
                color: activeColor,
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
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                      color: _Dt.ink,
                    ),
                  ),
                  const SizedBox(height: 6),
                  RichText(
                    text: TextSpan(
                      style: const TextStyle(
                        fontSize: 12,
                        color: _Dt.muted,
                        fontWeight: FontWeight.w600,
                        height: 1.5,
                      ),
                      children: [
                        const TextSpan(
                          text: 'Préparez votre ',
                        ),
                        TextSpan(
                          text: 'CNI',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            color: activeColor,
                          ),
                        ),
                        const TextSpan(text: ' et un '),
                        TextSpan(
                          text: 'selfie',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            color: activeColor,
                          ),
                        ),
                        const TextSpan(
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
    });
  }

  // ── Continue Button ───────────────────────────────────────────────────────

  Widget _buildContinueButton() {
    return Obx(() {
      final canContinue =
          _selectedProfile.value != null && _c.phone.value.length >= 14;
      final activeColor = _roleColor(_selectedProfile.value);
      final darkActiveColor = Color.alphaBlend(Colors.black.withValues(alpha: 0.15), activeColor);

      return AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        height: 56,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(28),
          gradient: canContinue
              ? LinearGradient(
                  colors: [activeColor, darkActiveColor],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: canContinue ? null : const Color(0xFFE0E0E0),
          boxShadow: canContinue
              ? [
                  BoxShadow(
                    color: activeColor.withValues(alpha: 0.35),
                    blurRadius: 16,
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
            disabledForegroundColor: const Color(0xFF9E9E9E),
            shadowColor: Colors.transparent,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(28),
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
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(width: 10),
                    AnimatedRotation(
                      duration: const Duration(milliseconds: 350),
                      curve: Curves.easeOutBack,
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

    unawaited(HapticFeedback.mediumImpact());

    // Send OTP
    await _c.sendOtp();

    // If OTP sent successfully, navigate to OTP verification screen
    if (_c.otpSent.value && _c.errorMsg.value == null) {
      unawaited(Get.toNamed(Routes.otpVerification, arguments: {
        'phone': _c.phone.value,
        'role': _selectedProfile.value,
      }));
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

  Color _roleColor(String? label) {
    if (label == null) return _Dt.primary;
    switch (label) {
      case 'ARTISAN':
        return _Dt.artisan;
      case 'FOURNISSEUR':
        return _Dt.fournisseur;
      case 'LIVREUR':
        return _Dt.driver;
      case 'CLIENT':
      default:
        return _Dt.client;
    }
  }

  Widget _buildResetLink() {
    return Center(
      child: TextButton(
        onPressed: _showResetOptionsDialog,
        child: const Text(
          'Paramètres perdus ? Réinitialiser',
          style: TextStyle(
            color: _Dt.muted,
            fontSize: 13,
            fontWeight: FontWeight.w700,
            decoration: TextDecoration.underline,
          ),
        ),
      ),
    );
  }

  void _showResetOptionsDialog() {
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Réinitialisation de connexion',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        content: const Text(
          'Choisissez l\'action de réinitialisation appropriée pour votre situation :',
          style: TextStyle(color: _Dt.muted),
        ),
        actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        actions: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              OutlinedButton.icon(
                onPressed: () async {
                  Get.back();
                  await _c.resetLocalSession();
                  Get.snackbar(
                    'Session réinitialisée',
                    'Le cache local a été vidé. Vous pouvez à présent vous reconnecter.',
                    backgroundColor: _Dt.success,
                    colorText: Colors.white,
                    snackPosition: SnackPosition.TOP,
                  );
                },
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Réinitialiser l\'application (local)'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: _Dt.primary,
                  side: const BorderSide(color: _Dt.primary),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
              const SizedBox(height: 8),
              ElevatedButton.icon(
                onPressed: () {
                  Get.back();
                  _showRecoverAccountDialog();
                },
                icon: const Icon(Icons.swap_calls_rounded),
                label: const Text('Changement de numéro de téléphone'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _Dt.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Fermer'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showRecoverAccountDialog() {
    final oldPhoneCtrl = TextEditingController(text: '+225');
    final newPhoneCtrl = TextEditingController(text: '+225');
    final nameCtrl = TextEditingController();
    final otpCtrl = TextEditingController();

    // Reset controller states
    _c.resetOldPhone.value = '+225';
    _c.resetNewPhone.value = '+225';
    _c.resetName.value = '';
    _c.resetRole.value = null;
    _c.resetOtp.value = '';
    _c.isResetOtpSent.value = false;
    _c.errorMsg.value = null;

    Get.dialog(
      Obx(() => AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            title: const Text(
              'Récupération de compte',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Rattachez votre ancien compte à votre nouveau numéro de téléphone.',
                    style: TextStyle(color: _Dt.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  
                  // Role Selector
                  DropdownButtonFormField<String>(
                    initialValue: _c.resetRole.value,
                    decoration: const InputDecoration(
                      labelText: 'Votre espace / rôle',
                      border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'client', child: Text('Client')),
                      DropdownMenuItem(value: 'artisan', child: Text('Artisan')),
                      DropdownMenuItem(value: 'fournisseur', child: Text('Fournisseur')),
                      DropdownMenuItem(value: 'driver', child: Text('Livreur')),
                    ],
                    onChanged: _c.isResetOtpSent.value ? null : (val) {
                      _c.resetRole.value = val;
                    },
                  ),
                  const SizedBox(height: 12),

                  // Name
                  TextField(
                    controller: nameCtrl,
                    enabled: !_c.isResetOtpSent.value,
                    decoration: const InputDecoration(
                      labelText: 'Nom complet exact',
                      border: OutlineInputBorder(),
                      hintText: 'Ex: Jean Dupont',
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    onChanged: (val) => _c.resetName.value = val,
                  ),
                  const SizedBox(height: 12),

                  // Old Phone
                  TextField(
                    controller: oldPhoneCtrl,
                    enabled: !_c.isResetOtpSent.value,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Ancien numéro (+225)',
                      border: OutlineInputBorder(),
                      hintText: '+2250707000000',
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    onChanged: (val) => _c.resetOldPhone.value = val,
                  ),
                  const SizedBox(height: 12),

                  // New Phone
                  TextField(
                    controller: newPhoneCtrl,
                    enabled: !_c.isResetOtpSent.value,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Nouveau numéro (+225)',
                      border: OutlineInputBorder(),
                      hintText: '+2250707000000',
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    onChanged: (val) => _c.resetNewPhone.value = val,
                  ),

                  if (_c.isResetOtpSent.value) ...[
                    const SizedBox(height: 16),
                    const Divider(),
                    const SizedBox(height: 8),
                    const Text(
                      'Entrez le code OTP reçu sur votre nouveau numéro :',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: otpCtrl,
                      keyboardType: TextInputType.number,
                      maxLength: 4,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, letterSpacing: 8),
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        counterText: '',
                        hintText: '0000',
                        contentPadding: EdgeInsets.symmetric(vertical: 8),
                      ),
                      onChanged: (val) => _c.resetOtp.value = val,
                    ),
                  ],

                  if (_c.errorMsg.value != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _c.errorMsg.value!,
                      style: const TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Annuler'),
              ),
              ElevatedButton(
                onPressed: _c.isResetting.value
                    ? null
                    : () async {
                        if (!_c.isResetOtpSent.value) {
                          // Envoyer OTP
                          await _c.requestResetPhone();
                          if (_c.errorMsg.value != null) {
                            Get.snackbar(
                              'Erreur',
                              _c.errorMsg.value!,
                              backgroundColor: Colors.red,
                              colorText: Colors.white,
                            );
                          } else {
                            Get.snackbar(
                              'OTP envoyé',
                              'Un code de validation a été envoyé sur votre nouveau numéro.',
                              backgroundColor: _Dt.success,
                              colorText: Colors.white,
                            );
                          }
                        } else {
                          // Confirmer la récupération
                          final success = await _c.confirmResetPhone();
                          if (success) {
                            Get.back();
                            Get.snackbar(
                              'Compte récupéré',
                              'Votre compte a été associé à votre nouveau numéro avec succès.',
                              backgroundColor: _Dt.success,
                              colorText: Colors.white,
                            );
                            unawaited(Get.offAllNamed(Routes.mainTab));
                          } else {
                            Get.snackbar(
                              'Code OTP erroné',
                              _c.errorMsg.value ?? 'Le code saisi est invalide.',
                              backgroundColor: Colors.red,
                              colorText: Colors.white,
                            );
                          }
                        }
                      },
                child: _c.isResetting.value
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Text(_c.isResetOtpSent.value ? 'Confirmer' : 'Suivant'),
              ),
            ],
          )),
    );
  }
}

class _GlowBubble extends StatelessWidget {
  const _GlowBubble({
    required this.size,
    required this.color,
  });

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [
              color,
              color.withValues(alpha: 0),
            ],
          ),
        ),
      ),
    );
  }
}
