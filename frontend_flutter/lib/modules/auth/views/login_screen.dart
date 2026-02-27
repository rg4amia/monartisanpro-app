import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/auth_controller.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with TickerProviderStateMixin {
  final _c = Get.find<AuthController>();
  late final AnimationController _shakeCtrl;
  late final Animation<double> _shakeAnim;
  late final AnimationController _fadeCtrl;
  late final Animation<double> _fadeAnim;
  late final AnimationController _slideCtrl;
  late final Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();

    // Shake animation
    _shakeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _shakeAnim = Tween<double>(begin: 0, end: 1).animate(_shakeCtrl);

    // Fade animation
    _fadeCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _fadeAnim = CurvedAnimation(
      parent: _fadeCtrl,
      curve: Curves.easeInOut,
    );

    // Slide animation
    _slideCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _slideAnim = Tween<Offset>(
      begin: const Offset(0, 0.3),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _slideCtrl,
      curve: Curves.easeOutCubic,
    ));

    // Start animations
    _fadeCtrl.forward();
    _slideCtrl.forward();
  }

  @override
  void dispose() {
    _shakeCtrl.dispose();
    _fadeCtrl.dispose();
    _slideCtrl.dispose();
    super.dispose();
  }

  void _shake() {
    _shakeCtrl.forward(from: 0);
    HapticFeedback.heavyImpact();
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: Stack(
        children: [
          // Professional subtle background pattern
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: size.height * 0.4,
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    const Color(0xFF0F172A),
                    const Color(0xFF0F172A).withValues(alpha: 0.95),
                    const Color(0xFFF8FAFC),
                  ],
                  stops: const [0.0, 0.7, 1.0],
                ),
              ),
            ),
          ),
          // Subtle grid pattern overlay
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: size.height * 0.35,
            child: CustomPaint(
              painter: _GridPainter(),
            ),
          ),

          // Main content
          SafeArea(
            child: FadeTransition(
              opacity: _fadeAnim,
              child: SlideTransition(
                position: _slideAnim,
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(height: size.height * 0.05),

                      // Logo & Title Section
                      _buildHeader(),

                      SizedBox(height: size.height * 0.04),

                      // Professional credentials
                      _buildCredentials(),

                      const SizedBox(height: 28),

                      // Form Section
                      Obx(() => _c.otpSent.value
                          ? _buildOtpStep()
                          : _buildPhoneStep()),

                      const SizedBox(height: 20),

                      // Error Message
                      Obx(() {
                        if (_c.errorMsg.value == null) {
                          return const SizedBox.shrink();
                        }
                        return _buildErrorMessage(_c.errorMsg.value!);
                      }),

                      const SizedBox(height: 28),

                      // Trust indicators
                      _buildTrustIndicators(),

                      const SizedBox(height: 24),

                      // Footer
                      _buildFooter(),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Center(
      child: Column(
        children: [
          // Professional badge-style logo
          Hero(
            tag: 'app_logo',
            child: Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF0F172A).withValues(alpha: 0.15),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ],
                border: Border.all(
                  color: const Color(0xFF0369A1).withValues(alpha: 0.2),
                  width: 2,
                ),
              ),
              child: Stack(
                children: [
                  Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.handyman_rounded,
                          size: 40,
                          color: const Color(0xFF0369A1),
                        ),
                        const SizedBox(height: 4),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFF0369A1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Text(
                            'PRO',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 1,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Verified badge
                  Positioned(
                    top: -2,
                    right: -2,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: Color(0xFF0369A1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.verified,
                        color: Colors.white,
                        size: 16,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // App name with handwritten style
          const Text(
            'ProsArtisan',
            style: TextStyle(
              fontSize: 42,
              fontWeight: FontWeight.w700,
              color: Colors.white,
              letterSpacing: 1,
              height: 1.1,
              shadows: [
                Shadow(
                  color: Color(0xFF000000),
                  offset: Offset(0, 2),
                  blurRadius: 8,
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),

          // Professional subtitle
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.3),
                width: 1,
              ),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.workspace_premium,
                  color: Colors.white,
                  size: 16,
                ),
                SizedBox(width: 6),
                Text(
                  'Artisans Certifiés & Vérifiés',
                  style: TextStyle(
                    fontSize: 13,
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCredentials() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          _buildCredentialItem(Icons.verified_user, '100%\nSécurisé'),
          const SizedBox(width: 16),
          Container(
            width: 1,
            height: 40,
            color: const Color(0xFF334155).withValues(alpha: 0.2),
          ),
          const SizedBox(width: 16),
          _buildCredentialItem(Icons.people_outline, '10k+\nArtisans'),
          const SizedBox(width: 16),
          Container(
            width: 1,
            height: 40,
            color: const Color(0xFF334155).withValues(alpha: 0.2),
          ),
          const SizedBox(width: 16),
          _buildCredentialItem(Icons.star_border, '4.8/5\nNote'),
        ],
      ),
    );
  }

  Widget _buildCredentialItem(IconData icon, String label) {
    return Expanded(
      child: Column(
        children: [
          Icon(
            icon,
            color: const Color(0xFF0369A1),
            size: 28,
          ),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0F172A),
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTrustIndicators() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF0369A1).withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: const Color(0xFF0369A1).withValues(alpha: 0.2),
          width: 1,
        ),
      ),
      child: Row(
        children: [
          Icon(
            Icons.security,
            color: const Color(0xFF0369A1),
            size: 20,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Vos données sont protégées et cryptées',
              style: TextStyle(
                fontSize: 12,
                color: const Color(0xFF0F172A).withValues(alpha: 0.8),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorMessage(String message) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.0, end: 1.0),
      duration: const Duration(milliseconds: 300),
      builder: (context, value, child) {
        return Transform.scale(
          scale: value,
          child: Opacity(
            opacity: value,
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Colors.red.shade50,
                    Colors.red.shade100,
                  ],
                ),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: Colors.red.shade300,
                  width: 2,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade100,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.error_outline_rounded,
                      color: Colors.red.shade700,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      message,
                      style: TextStyle(
                        color: Colors.red.shade900,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildFooter() {
    return Center(
      child: Column(
        children: [
          Text(
            'En continuant, vous acceptez nos',
            style: TextStyle(
              fontSize: 12,
              color: const Color(0xFF4C1D95).withValues(alpha: 0.6),
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 4,
            children: [
              TextButton(
                onPressed: () {},
                style: TextButton.styleFrom(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  backgroundColor:
                      const Color(0xFF7C3AED).withValues(alpha: 0.08),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'Conditions',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF7C3AED),
                  ),
                ),
              ),
              Text(
                ' et ',
                style: TextStyle(
                  fontSize: 12,
                  color: const Color(0xFF4C1D95).withValues(alpha: 0.6),
                  fontWeight: FontWeight.w500,
                ),
              ),
              TextButton(
                onPressed: () {},
                style: TextButton.styleFrom(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  backgroundColor:
                      const Color(0xFF7C3AED).withValues(alpha: 0.08),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'Confidentialité',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF7C3AED),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPhoneStep() {
    return AnimatedBuilder(
      animation: _shakeAnim,
      builder: (_, child) {
        final offset = _shakeAnim.value == 0
            ? 0.0
            : (8 *
                (1 - _shakeAnim.value) *
                (_shakeCtrl.status == AnimationStatus.forward ? 1 : -1));
        return Transform.translate(
          offset: Offset(offset, 0),
          child: child,
        );
      },
      child: Container(
        padding: const EdgeInsets.all(28),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(
            color: const Color(0xFF7C3AED).withValues(alpha: 0.2),
            width: 2,
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF7C3AED).withValues(alpha: 0.12),
              blurRadius: 24,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [
                        Color(0xFF7C3AED),
                        Color(0xFFA78BFA),
                      ],
                    ),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(
                    Icons.shopping_bag_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ),
                const SizedBox(width: 16),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Commencer',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF4C1D95),
                          letterSpacing: -0.5,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Accédez à 10k+ artisans',
                        style: TextStyle(
                          fontSize: 14,
                          color: Color(0xFF7C3AED),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 28),
            const Text(
              'Numéro de téléphone',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: Color(0xFF4C1D95),
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 20,
                  ),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        const Color(0xFF7C3AED).withValues(alpha: 0.1),
                        const Color(0xFFA78BFA).withValues(alpha: 0.1),
                      ],
                    ),
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(16),
                      bottomLeft: Radius.circular(16),
                    ),
                    border: Border.all(
                      color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
                      width: 2,
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 28,
                        height: 20,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(4),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 4,
                            ),
                          ],
                        ),
                        child: const Center(
                          child: Text(
                            '🇨🇮',
                            style: TextStyle(fontSize: 14),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        '+225',
                        style: TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 17,
                          color: Color(0xFF4C1D95),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: TextFormField(
                    onChanged: (v) => _c.phone.value = '+225$v',
                    keyboardType: TextInputType.phone,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                    ],
                    maxLength: 10,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1.2,
                      color: Color(0xFF4C1D95),
                    ),
                    decoration: InputDecoration(
                      hintText: '07 00 00 00 00',
                      hintStyle: TextStyle(
                        color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
                        fontWeight: FontWeight.w600,
                      ),
                      counterText: '',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 20,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
                          width: 2,
                        ),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
                          width: 2,
                        ),
                      ),
                      focusedBorder: const OutlineInputBorder(
                        borderRadius: BorderRadius.only(
                          topRight: Radius.circular(16),
                          bottomRight: Radius.circular(16),
                        ),
                        borderSide: BorderSide(
                          color: Color(0xFF7C3AED),
                          width: 2,
                        ),
                      ),
                      fillColor: Colors.white,
                      filled: true,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 28),
            Obx(() => SizedBox(
                  width: double.infinity,
                  height: 60,
                  child: ElevatedButton(
                    onPressed: _c.canSendOtp
                        ? (_c.isLoading.value ? null : _c.sendOtp)
                        : _shake,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _c.canSendOtp
                          ? const Color(0xFF22C55E)
                          : const Color(0xFFE5E7EB),
                      foregroundColor: Colors.white,
                      elevation: _c.canSendOtp ? 4 : 0,
                      shadowColor:
                          const Color(0xFF22C55E).withValues(alpha: 0.4),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: _c.isLoading.value
                        ? const SizedBox(
                            height: 26,
                            width: 26,
                            child: CircularProgressIndicator(
                              strokeWidth: 3,
                              color: Colors.white,
                            ),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Text(
                                'Recevoir le code',
                                style: TextStyle(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 0.3,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Icon(
                                Icons.arrow_forward_rounded,
                                size: 22,
                                color: Colors.white.withValues(alpha: 0.95),
                              ),
                            ],
                          ),
                  ),
                )),
          ],
        ),
      ),
    );
  }

  Widget _buildOtpStep() {
    return Container(
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: const Color(0xFF7C3AED).withValues(alpha: 0.2),
          width: 2,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF7C3AED).withValues(alpha: 0.12),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              GestureDetector(
                onTap: () {
                  _c.otpSent.value = false;
                  HapticFeedback.lightImpact();
                },
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [
                        Color(0xFF7C3AED),
                        Color(0xFFA78BFA),
                      ],
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.arrow_back_rounded,
                    size: 20,
                    color: Colors.white,
                  ),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Vérification',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF4C1D95),
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Code → ${_c.phone.value}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: Color(0xFF7C3AED),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 32),
          const Text(
            'Code de vérification',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF4C1D95),
            ),
          ),
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  const Color(0xFF7C3AED).withValues(alpha: 0.08),
                  const Color(0xFFA78BFA).withValues(alpha: 0.08),
                ],
              ),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: const Color(0xFF7C3AED).withValues(alpha: 0.3),
                width: 2,
              ),
            ),
            child: TextFormField(
              onChanged: (v) {
                _c.otp.value = v;
                if (v.length == 4) {
                  HapticFeedback.mediumImpact();
                }
              },
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              maxLength: 4,
              textAlign: TextAlign.center,
              autofocus: true,
              style: const TextStyle(
                fontSize: 48,
                fontWeight: FontWeight.w900,
                letterSpacing: 24,
                color: Color(0xFF7C3AED),
              ),
              decoration: InputDecoration(
                counterText: '',
                hintText: '····',
                hintStyle: TextStyle(
                  color: const Color(0xFF7C3AED).withValues(alpha: 0.25),
                  letterSpacing: 24,
                  fontSize: 48,
                  fontWeight: FontWeight.w900,
                ),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 20),
              ),
            ),
          ),
          const SizedBox(height: 28),
          Obx(() => SizedBox(
                width: double.infinity,
                height: 60,
                child: ElevatedButton(
                  onPressed: _c.canVerifyOtp
                      ? (_c.isLoading.value ? null : _c.verifyOtp)
                      : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF22C55E),
                    foregroundColor: Colors.white,
                    elevation: 4,
                    shadowColor: const Color(0xFF22C55E).withValues(alpha: 0.4),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    disabledBackgroundColor: const Color(0xFFE5E7EB),
                  ),
                  child: _c.isLoading.value
                      ? const SizedBox(
                          height: 26,
                          width: 26,
                          child: CircularProgressIndicator(
                            strokeWidth: 3,
                            color: Colors.white,
                          ),
                        )
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text(
                              'Vérifier',
                              style: TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.3,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Icon(
                              Icons.check_circle_rounded,
                              size: 22,
                              color: Colors.white.withValues(alpha: 0.95),
                            ),
                          ],
                        ),
                ),
              )),
          const SizedBox(height: 18),
          Center(
            child: TextButton.icon(
              onPressed: _c.sendOtp,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              label: const Text(
                'Renvoyer le code',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF7C3AED),
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 14,
                ),
                backgroundColor:
                    const Color(0xFF7C3AED).withValues(alpha: 0.08),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
