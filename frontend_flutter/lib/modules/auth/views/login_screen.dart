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
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 60),
              // Logo / Title
              Center(
                child: Column(
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Center(
                        child: Text('PA',
                            style: TextStyle(
                                color: Colors.white,
                                fontSize: 28,
                                fontWeight: FontWeight.w800)),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text('ProsArtisan',
                        style: TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary)),
                    const SizedBox(height: 6),
                    const Text(
                      'Connectez-vous pour continuer',
                      style: TextStyle(
                          fontSize: 14, color: AppColors.textSecondary),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 48),

              Obx(() => _c.otpSent.value ? _buildOtpStep() : _buildPhoneStep()),

              const SizedBox(height: 20),
              Obx(() {
                if (_c.errorMsg.value == null) return const SizedBox.shrink();
                return Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.danger.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline,
                          color: AppColors.danger, size: 16),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _c.errorMsg.value!,
                          style: const TextStyle(
                              color: AppColors.danger, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                );
              }),
            ],
          ),
        ),
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Numéro de téléphone',
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary)),
          const SizedBox(height: 8),
          Row(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
                decoration: BoxDecoration(
                  color: AppColors.card,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(12),
                    bottomLeft: Radius.circular(12),
                  ),
                  border: const Border.fromBorderSide(
                      BorderSide(color: AppColors.border)),
                ),
                child: const Text('+225',
                    style: TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary)),
              ),
              Expanded(
                child: TextFormField(
                  onChanged: (v) => _c.phone.value = '+225$v',
                  keyboardType: TextInputType.phone,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  maxLength: 10,
                  decoration: InputDecoration(
                    hintText: '07 00 00 00 00',
                    counterText: '',
                    border: const OutlineInputBorder(
                      borderRadius: BorderRadius.only(
                        topRight: Radius.circular(12),
                        bottomRight: Radius.circular(12),
                      ),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    enabledBorder: const OutlineInputBorder(
                      borderRadius: BorderRadius.only(
                        topRight: Radius.circular(12),
                        bottomRight: Radius.circular(12),
                      ),
                      borderSide: BorderSide(color: AppColors.border),
                    ),
                    focusedBorder: const OutlineInputBorder(
                      borderRadius: BorderRadius.only(
                        topRight: Radius.circular(12),
                        bottomRight: Radius.circular(12),
                      ),
                      borderSide:
                          BorderSide(color: AppColors.primary, width: 2),
                    ),
                    fillColor: AppColors.card,
                    filled: true,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Obx(() => ElevatedButton(
                onPressed: _c.canSendOtp
                    ? (_c.isLoading.value ? null : _c.sendOtp)
                    : _shake,
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      _c.canSendOtp ? AppColors.primary : AppColors.textMuted,
                ),
                child: _c.isLoading.value
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Text('Recevoir le code'),
              )),
        ],
      ),
    );
  }

  Widget _buildOtpStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            GestureDetector(
              onTap: () => _c.otpSent.value = false,
              child: const Icon(Icons.arrow_back_ios,
                  size: 18, color: AppColors.primary),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Code envoyé au ${_c.phone.value}',
                style: const TextStyle(
                    fontSize: 14, color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        const Text('Code de vérification',
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary)),
        const SizedBox(height: 8),
        TextFormField(
          onChanged: (v) {
            _c.otp.value = v;
            if (v.length == 4) {
              HapticFeedback.lightImpact();
            }
          },
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          maxLength: 4,
          textAlign: TextAlign.center,
          style: const TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w700,
              letterSpacing: 16,
              color: AppColors.primary),
          decoration: InputDecoration(
            counterText: '',
            hintText: '····',
            hintStyle: TextStyle(
                color: AppColors.textMuted.withValues(alpha: 0.5),
                letterSpacing: 16,
                fontSize: 32),
          ),
        ),
        const SizedBox(height: 20),
        Obx(() => ElevatedButton(
              onPressed: _c.canVerifyOtp
                  ? (_c.isLoading.value ? null : _c.verifyOtp)
                  : null,
              child: _c.isLoading.value
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Text('Vérifier'),
            )),
        const SizedBox(height: 12),
        Center(
          child: TextButton(
            onPressed: _c.sendOtp,
            child: const Text('Renvoyer le code'),
          ),
        ),
      ],
    );
  }
}
