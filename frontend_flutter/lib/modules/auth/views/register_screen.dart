import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:flutter/gestures.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/config/env_config.dart';
import '../controllers/auth_controller.dart';

// ─── Design Tokens ───────────────────────────────────────────────────────────

class _Dt {
  static const primary = AppColors.primary;
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const border = AppColors.border;
}

// ─── Register Screen ─────────────────────────────────────────────────────────

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen>
    with TickerProviderStateMixin {
  final _c = Get.find<AuthController>();

  final _nameCtrl = TextEditingController();

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
    _nameCtrl.dispose();
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
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: _Dt.ink),
            onPressed: () => Get.back(),
          ),
          title: const Text(
            'Complétez votre profil',
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
                  _buildNameInput(),
                  const SizedBox(height: 32),
                  _buildRoleInfo(),
                  const SizedBox(height: 24),
                  _buildKycNotice(),
                  const SizedBox(height: 32),

                  // Error message
                  Obx(() {
                    if (_c.errorMsg.value == null) {
                      return const SizedBox.shrink();
                    }
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: _buildError(_c.errorMsg.value!),
                    );
                  }),

                  _buildCguCheckbox(),
                  const SizedBox(height: 24),

                  _buildContinueButton(),
                  const SizedBox(height: 24),
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
          'Une dernière étape avant de démarrer votre aventure professionnelle avec nous.',
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

  // ── Name Input ────────────────────────────────────────────────────────────

  Widget _buildNameInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(Icons.person_outline, color: _Dt.primary, size: 20),
            const SizedBox(width: 8),
            const Text(
              'Nom complet',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: _Dt.ink,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _nameCtrl,
          onChanged: (v) => _c.name.value = v,
          keyboardType: TextInputType.name,
          textCapitalization: TextCapitalization.words,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: _Dt.ink,
          ),
          decoration: InputDecoration(
            hintText: 'Ex: Kouassi Jean-Marc',
            hintStyle: TextStyle(
              color: _Dt.muted.withValues(alpha: 0.4),
              fontWeight: FontWeight.w500,
            ),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 18,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: _Dt.border, width: 1.5),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: _Dt.border, width: 1.5),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: _Dt.primary, width: 2),
            ),
            fillColor: _Dt.surface,
            filled: true,
          ),
        ),
      ],
    );
  }

  // ── Role Info ─────────────────────────────────────────────────────────────

  Widget _buildRoleInfo() {
    return Obx(() {
      final role = _c.role.value ?? 'CLIENT';
      final roleInfo = _getRoleInfo(role);

      return Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: _Dt.primary.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: _Dt.primary.withValues(alpha: 0.2),
            width: 1.5,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: _Dt.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(
                child: Text(
                  roleInfo['emoji']!,
                  style: const TextStyle(fontSize: 32),
                ),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Profil : ${roleInfo['label']}',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: _Dt.ink,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    roleInfo['description']!,
                    style: TextStyle(
                      fontSize: 13,
                      color: _Dt.muted,
                      fontWeight: FontWeight.w500,
                      height: 1.4,
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

  Map<String, String> _getRoleInfo(String role) {
    final norm = role.toUpperCase();
    switch (norm) {
      case 'ARTISAN':
        return {
          'emoji': '👨‍🔧',
          'label': 'Artisan',
          'description': 'Recevez des missions et développez votre activité',
        };
      case 'FOURNISSEUR':
        return {
          'emoji': '🏭',
          'label': 'Fournisseur',
          'description': 'Fournissez des matériaux aux artisans via J-Code',
        };
      case 'DRIVER':
      case 'LIVREUR':
        return {
          'emoji': '🚚',
          'label': 'Livreur',
          'description': 'Enlevez et livrez les colis de matériaux en toute sécurité',
        };
      default:
        return {
          'emoji': '👩‍💼',
          'label': 'Client',
          'description': 'Trouvez des artisans qualifiés pour vos projets',
        };
    }
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
                    text: 'Prochaine étape : ',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                  TextSpan(
                    text:
                        'Vous devrez compléter votre vérification d\'identité (KYC) pour accéder à toutes les fonctionnalités.',
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
      final canContinue = _c.name.value.trim().length >= 2 && _c.cguAccepted.value;

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
                      'Continuer vers le KYC',
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
    if (_c.name.value.trim().length < 2) return;

    HapticFeedback.mediumImpact();

    // Register user (complete profile)
    await _c.register();

    // If registration successful, navigate to KYC CNI capture
    if (_c.errorMsg.value == null) {
      // Verify token is saved before navigation
      final token = await StorageService.getToken();

      if (token != null) {
        Get.offAllNamed(Routes.kycCni);
      } else {
        _c.errorMsg.value = 'Erreur: Token non reçu. Veuillez réessayer.';
      }
    }
  }

  // ── CGU Checkbox ──────────────────────────────────────────────────────────

  Widget _buildCguCheckbox() {
    return Obx(() => Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 24,
          width: 24,
          child: Checkbox(
            value: _c.cguAccepted.value,
            onChanged: (val) {
              if (val != null) _c.cguAccepted.value = val;
            },
            activeColor: _Dt.primary,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(4),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: GestureDetector(
            onTap: () {
              _c.cguAccepted.value = !_c.cguAccepted.value;
            },
            child: RichText(
              text: TextSpan(
                style: const TextStyle(
                  fontSize: 13,
                  color: _Dt.ink,
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
                children: [
                  const TextSpan(text: 'J\'ai lu et j\'accepte les '),
                  TextSpan(
                    text: 'Conditions Générales d\'Utilisation',
                    style: const TextStyle(
                      color: _Dt.primary,
                      fontWeight: FontWeight.w700,
                      decoration: TextDecoration.underline,
                    ),
                    recognizer: TapGestureRecognizer()
                      ..onTap = () async {
                        final url = Uri.parse(EnvConfig.baseUrl.replaceAll('/api/v1', '/cgu'));
                        if (await canLaunchUrl(url)) {
                          await launchUrl(url, mode: LaunchMode.externalApplication);
                        }
                      },
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ));
  }

  // ── Error ─────────────────────────────────────────────────────────────────

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
}
