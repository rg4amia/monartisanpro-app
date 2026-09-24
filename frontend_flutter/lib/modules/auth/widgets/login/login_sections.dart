import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../core/theme/app_colors.dart';
import 'login_tokens.dart';

Widget buildLoginWelcomeSection() {
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
            color: LoginTokens.primary,
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
          color: LoginTokens.muted,
          fontWeight: FontWeight.w600,
          height: 1.4,
        ),
        textAlign: TextAlign.center,
      ),
    ],
  );
}

Widget buildLoginError(String msg) {
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

Widget buildLoginFooter() {
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
              color: LoginTokens.muted,
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
                colors: [LoginTokens.primary, LoginTokens.primaryLight],
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
                color: LoginTokens.muted.withValues(alpha: 0.8),
              ),
              const SizedBox(width: 6),
              Text(
                'Aide sur la vérification KYC',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: LoginTokens.muted.withValues(alpha: 0.8),
                  decoration: TextDecoration.underline,
                  decorationColor: LoginTokens.muted.withValues(alpha: 0.4),
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}
