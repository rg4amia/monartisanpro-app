import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const navy    = Color(0xFF0B0F1A);
  static const gold    = Color(0xFFCA8A04);
  static const goldBr  = Color(0xFFFBBF24);
  static const blue    = Color(0xFF1D4ED8);
  static const bg      = Color(0xFFF8FAFC);
  static const surface = Colors.white;
  static const ink     = Color(0xFF0F172A);
  static const muted   = Color(0xFF64748B);
  static const border  = Color(0xFFE2E8F0);
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

  // Animations — nullable so hot-reload reassemble() can safely recreate them
  AnimationController? _heroCtrl;
  Animation<double>?   _heroFade;
  Animation<Offset>?   _heroSlide;
  AnimationController? _cardCtrl;
  Animation<double>?   _cardFade;
  Animation<Offset>?   _cardSlide;
  AnimationController? _glowCtrl;
  Animation<double>?   _glowAnim;
  AnimationController? _shakeCtrl;
  Animation<double>?   _shakeAnim;

  // OTP
  final List<TextEditingController> _otpCtrl =
      List.generate(4, (_) => TextEditingController());
  final List<FocusNode> _otpFocus =
      List.generate(4, (_) => FocusNode());

  // ── Animation setup ───────────────────────────────────────────────────────

  void _setupAnimations({bool jumpToEnd = false}) {
    // Dispose any existing controllers before recreating
    _heroCtrl?.dispose();
    _cardCtrl?.dispose();
    _glowCtrl?.dispose();
    _shakeCtrl?.dispose();

    _heroCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _heroFade  = CurvedAnimation(parent: _heroCtrl!, curve: Curves.easeOut);
    _heroSlide = Tween<Offset>(begin: const Offset(0, -0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _heroCtrl!, curve: Curves.easeOutCubic));

    _cardCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _cardFade  = CurvedAnimation(parent: _cardCtrl!, curve: Curves.easeOut);
    _cardSlide = Tween<Offset>(begin: const Offset(0, 0.2), end: Offset.zero)
        .animate(CurvedAnimation(parent: _cardCtrl!, curve: Curves.easeOutCubic));

    _glowCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    )..repeat(reverse: true);
    _glowAnim = Tween<double>(begin: 0.55, end: 1.0)
        .animate(CurvedAnimation(parent: _glowCtrl!, curve: Curves.easeInOut));

    _shakeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _shakeAnim = Tween<double>(begin: 0, end: 1).animate(_shakeCtrl!);

    if (jumpToEnd) {
      // Hot reload — skip intro animations, show final state immediately
      _heroCtrl!.value = 1.0;
      _cardCtrl!.value = 1.0;
    } else {
      Future.delayed(const Duration(milliseconds: 80), () {
        if (mounted) _heroCtrl?.forward();
      });
      Future.delayed(const Duration(milliseconds: 260), () {
        if (mounted) _cardCtrl?.forward();
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _setupAnimations();
  }

  /// Called on hot reload — re-creates animation objects so late fields
  /// from a previous class version are never accessed uninitialized.
  @override
  void reassemble() {
    super.reassemble();
    _setupAnimations(jumpToEnd: true);
  }

  @override
  void dispose() {
    _heroCtrl?.dispose();
    _cardCtrl?.dispose();
    _glowCtrl?.dispose();
    _shakeCtrl?.dispose();
    for (final c in _otpCtrl) { c.dispose(); }
    for (final f in _otpFocus) { f.dispose(); }
    super.dispose();
  }

  void _shake() {
    _shakeCtrl?.forward(from: 0);
    HapticFeedback.heavyImpact();
  }

  void _onOtpKey(String v, int i) {
    if (v.length == 1 && i < 3) _otpFocus[i + 1].requestFocus();
    if (v.isEmpty && i > 0) _otpFocus[i - 1].requestFocus();
    _c.otp.value = _otpCtrl.map((c) => c.text).join();
    if (_c.otp.value.length == 4) HapticFeedback.mediumImpact();
  }

  // ── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: _Dt.navy,
        body: LayoutBuilder(
          builder: (context, constraints) {
            final total     = constraints.maxHeight;
            final heroH     = total * 0.40;
            final minCardH  = total - heroH + 36; // +36 overlaps the arc

            return Stack(
              children: [
                // ── Background pattern (full screen)
                Positioned.fill(
                  child: CustomPaint(painter: _KentePainter()),
                ),

                // ── Scrollable body
                SafeArea(
                  bottom: false,
                  child: SingleChildScrollView(
                    physics: const BouncingScrollPhysics(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // ── Hero
                        FadeTransition(
                          opacity: _heroFade ?? const AlwaysStoppedAnimation(1.0),
                          child: SlideTransition(
                            position: _heroSlide ?? const AlwaysStoppedAnimation(Offset.zero),
                            child: SizedBox(
                              height: heroH,
                              child: _buildHero(),
                            ),
                          ),
                        ),

                        // ── White bottom card
                        FadeTransition(
                          opacity: _cardFade ?? const AlwaysStoppedAnimation(1.0),
                          child: SlideTransition(
                            position: _cardSlide ?? const AlwaysStoppedAnimation(Offset.zero),
                            child: ConstrainedBox(
                              constraints: BoxConstraints(minHeight: minCardH),
                              child: Container(
                                decoration: const BoxDecoration(
                                  color: _Dt.surface,
                                  borderRadius: BorderRadius.vertical(
                                    top: Radius.circular(36),
                                  ),
                                ),
                                padding: const EdgeInsets.fromLTRB(
                                    24, 16, 24, 40),
                                child: _buildCard(),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  // ── Hero ──────────────────────────────────────────────────────────────────

  Widget _buildHero() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 0),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Glowing logo
          AnimatedBuilder(
            animation: _glowAnim ?? const AlwaysStoppedAnimation(0.75),
            builder: (_, child) {
              final glow = _glowAnim?.value ?? 0.75;
              return Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: _Dt.gold.withValues(alpha: glow * 0.5),
                      blurRadius: 40,
                      spreadRadius: 8,
                    ),
                  ],
                ),
                child: child,
              );
            },
            child: Hero(
              tag: 'app_logo',
              child: Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFFBBF24), Color(0xFFCA8A04), Color(0xFF92400E)],
                  ),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: _Dt.goldBr.withValues(alpha: 0.4),
                    width: 1.5,
                  ),
                ),
                child: Stack(
                  children: [
                    const Center(
                      child: Icon(
                        Icons.handyman_rounded,
                        color: Colors.white,
                        size: 40,
                      ),
                    ),
                    Positioned(
                      top: -2,
                      right: -2,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Color(0xFF1D4ED8),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.verified,
                          color: Colors.white,
                          size: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),

          // App name
          RichText(
            text: const TextSpan(
              children: [
                TextSpan(
                  text: 'Pros',
                  style: TextStyle(
                    fontSize: 40,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                    letterSpacing: 0.5,
                    height: 1.05,
                  ),
                ),
                TextSpan(
                  text: 'Artisan',
                  style: TextStyle(
                    fontSize: 40,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFFFBBF24),
                    letterSpacing: 0.5,
                    height: 1.05,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Tagline pill
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.18),
              ),
            ),
            child: const Text(
              'Artisans Certifiés & Vérifiés en CI',
              style: TextStyle(
                fontSize: 12,
                color: Colors.white,
                fontWeight: FontWeight.w600,
                letterSpacing: 0.2,
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Stat chips
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildStatChip(Icons.groups_rounded, '10k+', 'Artisans'),
              const SizedBox(width: 10),
              _buildStatChip(Icons.thumb_up_alt_rounded, '98%', 'Satisfaits'),
              const SizedBox(width: 10),
              _buildStatChip(Icons.star_rounded, '4.9', 'Note moy.'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatChip(IconData icon, String value, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: _Dt.gold.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: _Dt.goldBr, size: 15),
          const SizedBox(width: 6),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                  height: 1.1,
                ),
              ),
              Text(
                label,
                style: TextStyle(
                  fontSize: 9.5,
                  fontWeight: FontWeight.w600,
                  color: Colors.white.withValues(alpha: 0.6),
                  height: 1.1,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ── Card ──────────────────────────────────────────────────────────────────

  Widget _buildCard() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // Drag handle
        Center(
          child: Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: _Dt.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
        const SizedBox(height: 20),

        // Step progress bar
        Obx(() => _buildProgress(_c.otpSent.value ? 1 : 0)),
        const SizedBox(height: 26),

        // Form
        Obx(() => _c.otpSent.value ? _buildOtpStep() : _buildPhoneStep()),

        // Error
        Obx(() {
          if (_c.errorMsg.value == null) return const SizedBox.shrink();
          return Padding(
            padding: const EdgeInsets.only(top: 14),
            child: _buildError(_c.errorMsg.value!),
          );
        }),

        const SizedBox(height: 28),
        _buildTrustRow(),
        const SizedBox(height: 22),
        _buildFooter(),
      ],
    );
  }

  // ── Progress ──────────────────────────────────────────────────────────────

  Widget _buildProgress(int step) {
    return Row(
      children: [
        _buildProgressStep(0, step, 'Numéro'),
        _buildProgressLine(step > 0),
        _buildProgressStep(1, step, 'Vérification'),
      ],
    );
  }

  Widget _buildProgressStep(int index, int current, String label) {
    final done   = index < current;
    final active = index == current;
    return Column(
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active
                ? _Dt.gold
                : done
                    ? _Dt.blue
                    : _Dt.border,
            boxShadow: active
                ? [
                    BoxShadow(
                      color: _Dt.gold.withValues(alpha: 0.35),
                      blurRadius: 10,
                      spreadRadius: 1,
                    ),
                  ]
                : null,
          ),
          child: Center(
            child: done
                ? const Icon(Icons.check_rounded, color: Colors.white, size: 14)
                : Text(
                    '${index + 1}',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: (active || done) ? Colors.white : _Dt.muted,
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            color: (active || done) ? _Dt.ink : _Dt.muted,
          ),
        ),
      ],
    );
  }

  Widget _buildProgressLine(bool done) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 500),
          height: 2,
          margin: const EdgeInsets.symmetric(horizontal: 6),
          decoration: BoxDecoration(
            color: done ? _Dt.blue : _Dt.border,
            borderRadius: BorderRadius.circular(1),
          ),
        ),
      ),
    );
  }

  // ── Phone Step ────────────────────────────────────────────────────────────

  Widget _buildPhoneStep() {
    return AnimatedBuilder(
      animation: _shakeAnim ?? const AlwaysStoppedAnimation(0.0),
      builder: (_, child) {
        final v  = _shakeAnim?.value ?? 0.0;
        final dx = v == 0.0
            ? 0.0
            : 8 * (1 - v) *
                (_shakeCtrl?.status == AnimationStatus.forward ? 1 : -1);
        return Transform.translate(offset: Offset(dx, 0), child: child);
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Heading
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: _Dt.gold.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.phone_android_rounded,
                  color: _Dt.gold,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Connexion',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        color: _Dt.ink,
                        letterSpacing: -0.5,
                        height: 1.1,
                      ),
                    ),
                    Text(
                      'Entrez votre numéro CI',
                      style: TextStyle(
                        fontSize: 13,
                        color: _Dt.muted,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 26),

          // Phone label
          const Text(
            'Numéro de téléphone',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: _Dt.ink,
            ),
          ),
          const SizedBox(height: 10),

          // Phone input row
          _buildPhoneField(),
          const SizedBox(height: 22),

          // CTA — gold for inviting first action
          Obx(() => _buildButton(
                label: 'Recevoir le code SMS',
                icon: Icons.sms_rounded,
                color: _Dt.gold,
                onTap: _c.canSendOtp
                    ? (_c.isLoading.value ? null : _c.sendOtp)
                    : _shake,
                isLoading: _c.isLoading.value,
                enabled: _c.canSendOtp,
              )),
        ],
      ),
    );
  }

  Widget _buildPhoneField() {
    return Row(
      children: [
        // Country flag + prefix
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
          decoration: BoxDecoration(
            color: const Color(0xFFF1F5F9),
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(14),
              bottomLeft: Radius.circular(14),
            ),
            border: Border.all(color: _Dt.border, width: 1.5),
          ),
          child: const Row(
            children: [
              Text('🇨🇮', style: TextStyle(fontSize: 18)),
              SizedBox(width: 6),
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
        // Digits
        Expanded(
          child: TextFormField(
            onChanged: (v) => _c.phone.value = '+225$v',
            keyboardType: TextInputType.phone,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            maxLength: 10,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: _Dt.ink,
              letterSpacing: 1.5,
            ),
            decoration: InputDecoration(
              hintText: '07 00 00 00 00',
              hintStyle: TextStyle(
                color: _Dt.ink.withValues(alpha: 0.28),
                fontWeight: FontWeight.w500,
                fontSize: 15,
                letterSpacing: 0,
              ),
              counterText: '',
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 18,
              ),
              border: const OutlineInputBorder(
                borderRadius: BorderRadius.only(
                  topRight: Radius.circular(14),
                  bottomRight: Radius.circular(14),
                ),
                borderSide: BorderSide(color: _Dt.border, width: 1.5),
              ),
              enabledBorder: const OutlineInputBorder(
                borderRadius: BorderRadius.only(
                  topRight: Radius.circular(14),
                  bottomRight: Radius.circular(14),
                ),
                borderSide: BorderSide(color: _Dt.border, width: 1.5),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: const BorderRadius.only(
                  topRight: Radius.circular(14),
                  bottomRight: Radius.circular(14),
                ),
                borderSide: BorderSide(
                  color: _Dt.gold,
                  width: 2,
                ),
              ),
              fillColor: Colors.white,
              filled: true,
            ),
          ),
        ),
      ],
    );
  }

  // ── OTP Step ──────────────────────────────────────────────────────────────

  Widget _buildOtpStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Heading with back button
        Row(
          children: [
            GestureDetector(
              onTap: () {
                _c.otpSent.value = false;
                for (final c in _otpCtrl) { c.clear(); }
                _c.otp.value = '';
                HapticFeedback.lightImpact();
              },
              child: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _Dt.border),
                ),
                child: const Icon(
                  Icons.arrow_back_rounded,
                  size: 20,
                  color: _Dt.ink,
                ),
              ),
            ),
            const SizedBox(width: 14),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Vérification',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      color: _Dt.ink,
                      letterSpacing: -0.5,
                      height: 1.1,
                    ),
                  ),
                  Text(
                    'Code SMS à 4 chiffres',
                    style: TextStyle(
                      fontSize: 13,
                      color: _Dt.muted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 18),

        // Sent-to banner
        Obx(() => Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: _Dt.gold.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _Dt.gold.withValues(alpha: 0.22)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.mark_email_unread_rounded,
                      color: _Dt.gold, size: 17),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Code envoyé au ${_c.phone.value}',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: _Dt.ink.withValues(alpha: 0.75),
                      ),
                    ),
                  ),
                ],
              ),
            )),
        const SizedBox(height: 26),

        // OTP label
        const Text(
          'Code de vérification',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: _Dt.ink,
          ),
        ),
        const SizedBox(height: 14),

        // 4 digit boxes
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(4, _buildOtpBox),
        ),
        const SizedBox(height: 26),

        // Verify button — blue for trust/confirm action
        Obx(() => _buildButton(
              label: 'Confirmer le code',
              icon: Icons.check_circle_outline_rounded,
              color: _Dt.blue,
              onTap: _c.canVerifyOtp
                  ? (_c.isLoading.value ? null : _c.verifyOtp)
                  : null,
              isLoading: _c.isLoading.value,
              enabled: _c.canVerifyOtp,
            )),
        const SizedBox(height: 14),

        // Resend
        Center(
          child: TextButton.icon(
            onPressed: _c.sendOtp,
            icon: const Icon(Icons.refresh_rounded, size: 16),
            label: const Text(
              'Renvoyer le code',
              style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700),
            ),
            style: TextButton.styleFrom(
              foregroundColor: _Dt.blue,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildOtpBox(int i) {
    return SizedBox(
      width: 68,
      height: 72,
      child: TextFormField(
        controller: _otpCtrl[i],
        focusNode: _otpFocus[i],
        onChanged: (v) => _onOtpKey(v, i),
        keyboardType: TextInputType.number,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        maxLength: 1,
        textAlign: TextAlign.center,
        autofocus: i == 0,
        style: const TextStyle(
          fontSize: 30,
          fontWeight: FontWeight.w900,
          color: _Dt.blue,
          letterSpacing: 0,
        ),
        decoration: InputDecoration(
          counterText: '',
          contentPadding: EdgeInsets.zero,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.border, width: 2),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.border, width: 2),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(16),
            borderSide: const BorderSide(color: _Dt.gold, width: 2.5),
          ),
          fillColor: const Color(0xFFFAFAFB),
          filled: true,
        ),
      ),
    );
  }

  // ── Shared Button ─────────────────────────────────────────────────────────

  Widget _buildButton({
    required String label,
    required IconData icon,
    required Color color,
    required VoidCallback? onTap,
    required bool isLoading,
    required bool enabled,
  }) {
    return SizedBox(
      width: double.infinity,
      height: 56,
      child: ElevatedButton(
        onPressed: onTap,
        style: ElevatedButton.styleFrom(
          backgroundColor: enabled ? color : _Dt.border,
          foregroundColor: Colors.white,
          disabledBackgroundColor: _Dt.border,
          disabledForegroundColor: _Dt.muted,
          elevation: enabled ? 4 : 0,
          shadowColor: enabled ? color.withValues(alpha: 0.4) : Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        child: isLoading
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
                  Icon(icon, size: 20),
                  const SizedBox(width: 10),
                  Text(
                    label,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.1,
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  // ── Error ─────────────────────────────────────────────────────────────────

  Widget _buildError(String msg) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.0, end: 1.0),
      duration: const Duration(milliseconds: 250),
      builder: (_, v, child) => Transform.scale(
        scale: v,
        child: Opacity(opacity: v, child: child),
      ),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFFEF2F2),
          borderRadius: BorderRadius.circular(14),
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
      ),
    );
  }

  // ── Trust Row ─────────────────────────────────────────────────────────────

  Widget _buildTrustRow() {
    return Row(
      children: [
        _buildTrustBadge(Icons.security_rounded, 'Paiements\nSécurisés'),
        const SizedBox(width: 10),
        _buildTrustBadge(Icons.gpp_good_rounded, 'KYC\nVérifié'),
        const SizedBox(width: 10),
        _buildTrustBadge(Icons.support_agent_rounded, 'Support\n24/7'),
      ],
    );
  }

  Widget _buildTrustBadge(IconData icon, String label) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 6),
        decoration: BoxDecoration(
          color: _Dt.bg,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: _Dt.border),
        ),
        child: Column(
          children: [
            Icon(icon, color: _Dt.blue, size: 20),
            const SizedBox(height: 5),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: _Dt.muted,
                height: 1.35,
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
        Text(
          'En continuant, vous acceptez nos',
          style: TextStyle(
            fontSize: 11.5,
            color: _Dt.ink.withValues(alpha: 0.4),
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 5),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _buildFooterLink('Conditions d\'utilisation'),
            Text(
              '  ·  ',
              style: TextStyle(color: _Dt.ink.withValues(alpha: 0.3)),
            ),
            _buildFooterLink('Confidentialité'),
          ],
        ),
      ],
    );
  }

  Widget _buildFooterLink(String label) {
    return TextButton(
      onPressed: () {},
      style: TextButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 2),
        minimumSize: Size.zero,
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w700,
          color: _Dt.blue,
        ),
      ),
    );
  }
}

// ─── Kente-Inspired Background Painter ───────────────────────────────────────

class _KentePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    // Horizontal micro-bands (Kente textile feel — gold, red, green)
    final bands = [
      (const Color(0xFFCA8A04), 2.5),  // gold
      (const Color(0xFF7F1D1D), 1.5),  // dark red
      (const Color(0xFF14532D), 1.5),  // dark green
    ];

    final paint = Paint()..style = PaintingStyle.fill;
    double y = 0;
    while (y < size.height) {
      for (final (color, h) in bands) {
        paint.color = color.withValues(alpha: 0.09);
        canvas.drawRect(Rect.fromLTWH(0, y, size.width, h), paint);
        y += h;
        if (y > size.height) break;
      }
    }

    // Diagonal hatching
    final linePaint = Paint()
      ..color = Colors.white.withValues(alpha: 0.025)
      ..strokeWidth = 1
      ..style = PaintingStyle.stroke;

    const step = 42.0;
    for (double i = -size.height; i < size.width + size.height; i += step) {
      canvas.drawLine(Offset(i, 0), Offset(i + size.height, size.height), linePaint);
    }

    // Sparse dot grid
    final dotPaint = Paint()
      ..color = Colors.white.withValues(alpha: 0.06)
      ..style = PaintingStyle.fill;

    const grid = 80.0;
    for (double x = 0; x < size.width; x += grid) {
      for (double y2 = 0; y2 < size.height; y2 += grid) {
        canvas.drawCircle(Offset(x, y2), 1.5, dotPaint);
      }
    }

    // Subtle diamond shapes at grid intersections
    final diamondPaint = Paint()
      ..color = _Dt.gold.withValues(alpha: 0.07)
      ..style = PaintingStyle.fill;

    const dGrid = 160.0;
    for (double x = dGrid / 2; x < size.width; x += dGrid) {
      for (double y3 = dGrid / 2; y3 < size.height; y3 += dGrid) {
        final path = Path()
          ..moveTo(x, y3 - 6)
          ..lineTo(x + 4, y3)
          ..lineTo(x, y3 + 6)
          ..lineTo(x - 4, y3)
          ..close();
        canvas.drawPath(path, diamondPaint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}


