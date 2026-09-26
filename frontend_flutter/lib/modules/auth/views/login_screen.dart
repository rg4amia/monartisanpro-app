import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
// Pour d'éventuels globals
import '../../../data/services/app_settings_service.dart';
import '../controllers/auth_controller.dart';
import '../widgets/login/login_account_dialogs.dart';
import '../widgets/login/login_glow_bubble.dart';
import '../widgets/login/login_profile_card.dart';
import '../widgets/login/login_sections.dart';
import '../widgets/login/login_tokens.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

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
        backgroundColor: LoginTokens.bg,
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
              child: LoginGlowBubble(
                size: 180,
                color: AppColors.client.withValues(alpha: 0.12),
              ),
            ),
            Positioned(
              top: 110,
              right: -70,
              child: LoginGlowBubble(
                size: 220,
                color: AppColors.accent.withValues(alpha: 0.10),
              ),
            ),
            Positioned(
              bottom: -90,
              left: 30,
              child: LoginGlowBubble(
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
                      buildLoginWelcomeSection(),
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
                          child: buildLoginError(_c.errorMsg.value!),
                        );
                      }),
                      _buildContinueButton(),
                      const SizedBox(height: 12),
                      _buildResetLink(),
                      const SizedBox(height: 12),
                      buildLoginFooter(),
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

  // ── Profile Selection ─────────────────────────────────────────────────────

  Widget _buildProfileSelection() {
    final appSettings = Get.find<AppSettingsService>();

    return Obx(() {
      final List<Widget> visibleCards = [];

      if (!appSettings.isHidden('CLIENT')) {
        visibleCards.add(
          buildLoginProfileCard(
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
          buildLoginProfileCard(
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
          buildLoginProfileCard(
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
          buildLoginProfileCard(
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
                Expanded(
                  child: SizedBox(height: itemHeight, child: visibleCards[0]),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: SizedBox(height: itemHeight, child: visibleCards[1]),
                ),
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
            Expanded(
              child: SizedBox(height: itemHeight, child: visibleCards[0]),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: SizedBox(height: itemHeight, child: visibleCards[1]),
            ),
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

  // ── Phone Input ───────────────────────────────────────────────────────────

  Widget _buildPhoneInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Obx(() {
              final activeColor = loginRoleColor(_selectedProfile.value);
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
                color: LoginTokens.ink,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() {
          final activeColor = loginRoleColor(_selectedProfile.value);
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
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
                  decoration: BoxDecoration(
                    color: LoginTokens.surface,
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(16),
                      bottomLeft: Radius.circular(16),
                    ),
                    border: Border.all(
                      color: _selectedProfile.value != null
                          ? activeColor.withValues(alpha: 0.6)
                          : LoginTokens.border,
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
                          color: LoginTokens.ink,
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
                      color: LoginTokens.ink,
                      letterSpacing: 1.2,
                    ),
                    decoration: InputDecoration(
                      hintText: '01 23 45 67 89',
                      hintStyle: TextStyle(
                        color: LoginTokens.muted.withValues(alpha: 0.4),
                        fontWeight: FontWeight.w500,
                        letterSpacing: 1.2,
                      ),
                      suffixIcon: hasContent
                          ? Icon(
                              Icons.check_circle,
                              color: loginRoleColor(_selectedProfile.value),
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
                          color: _selectedProfile.value != null
                              ? activeColor.withValues(alpha: 0.6)
                              : LoginTokens.border,
                          width: 1.5,
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: _selectedProfile.value != null
                              ? activeColor.withValues(alpha: 0.6)
                              : LoginTokens.border,
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
                      fillColor: LoginTokens.surface,
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
      final activeColor = loginRoleColor(_selectedProfile.value);
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
                      color: LoginTokens.ink,
                    ),
                  ),
                  const SizedBox(height: 6),
                  RichText(
                    text: TextSpan(
                      style: const TextStyle(
                        fontSize: 12,
                        color: LoginTokens.muted,
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
      final activeColor = loginRoleColor(_selectedProfile.value);
      final darkActiveColor =
          Color.alphaBlend(Colors.black.withValues(alpha: 0.15), activeColor);

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

  Future<bool> _showSecurityChallengeDialog(BuildContext context) async {
    final answerCtrl = TextEditingController();
    final roleColor = loginRoleColor(_selectedProfile.value);

    final result = await Get.dialog<bool>(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: roleColor.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.shield_outlined,
                  color: roleColor,
                  size: 28,
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'Contrôle de sécurité',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: LoginTokens.ink,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Pour protéger vos envois de SMS, confirmez que vous êtes bien un utilisateur réel :',
                style: TextStyle(
                  fontSize: 12.5,
                  color: LoginTokens.muted,
                  height: 1.4,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Obx(
                () => Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.secondary.withValues(alpha: 0.5),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: roleColor.withValues(alpha: 0.3),
                    ),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          _c.challengeQuestion.value ?? 'Addition requise',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: roleColor,
                          ),
                          softWrap: true,
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        onPressed: () => _c.fetchSecurityChallenge(),
                        icon: const Icon(Icons.refresh_rounded, size: 20),
                        tooltip: 'Changer le calcul',
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(
                          minWidth: 32,
                          minHeight: 32,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: answerCtrl,
                autofocus: true,
                keyboardType: TextInputType.number,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 2,
                ),
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  hintText: 'Votre réponse',
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: roleColor, width: 2),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: () => Get.back(result: false),
                      child: const Text(
                        'Annuler',
                        style: TextStyle(color: LoginTokens.muted),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        if (answerCtrl.text.trim().isEmpty) return;
                        _c.botAnswer.value = answerCtrl.text.trim();
                        Get.back(result: true);
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: roleColor,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text(
                        'Valider',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    return result == true;
  }

  void _handleContinue() async {
    if (_selectedProfile.value == null) return;

    unawaited(HapticFeedback.mediumImpact());

    // Charger le défi anti-robot si nécessaire
    if (_c.challengeToken.value == null) {
      await _c.fetchSecurityChallenge();
    }

    if (!mounted) return;

    if (_c.challengeQuestion.value != null) {
      final confirmed = await _showSecurityChallengeDialog(context);
      if (!confirmed) return;
    }

    // Send OTP avec validation
    await _c.sendOtp();

    // If OTP sent successfully, navigate to OTP verification screen
    if (_c.otpSent.value && _c.errorMsg.value == null) {
      unawaited(
        Get.toNamed(
          Routes.otpVerification,
          arguments: {
            'phone': _c.phone.value,
            'role': _selectedProfile.value,
          },
        ),
      );
    }
  }

  // ── Error Display ─────────────────────────────────────────────────────────

  // ── Footer ────────────────────────────────────────────────────────────────

  Widget _buildResetLink() {
    return Center(
      child: TextButton(
        onPressed: () => showLoginResetOptionsDialog(_c),
        child: const Text(
          'Paramètres perdus ? Réinitialiser',
          style: TextStyle(
            color: LoginTokens.muted,
            fontSize: 13,
            fontWeight: FontWeight.w700,
            decoration: TextDecoration.underline,
          ),
        ),
      ),
    );
  }
}
