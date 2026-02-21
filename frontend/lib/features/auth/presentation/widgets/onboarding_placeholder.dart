import 'package:flutter/material.dart';
import '../../../../core/theme/app_colors.dart';

/// Placeholder widget for onboarding screens when images are not available
/// Shows a gradient background with an icon
class OnboardingPlaceholder extends StatelessWidget {
  final IconData icon;
  final List<Color> gradient;

  const OnboardingPlaceholder({
    super.key,
    required this.icon,
    required this.gradient,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: gradient,
        ),
      ),
      child: Center(
        child: Icon(icon, size: 120, color: Colors.white.withOpacity(0.3)),
      ),
    );
  }
}

/// Alternative onboarding page data with fallback to gradient
class OnboardingPageWithFallback {
  final String? imagePath;
  final IconData fallbackIcon;
  final List<Color> fallbackGradient;
  final String title;
  final String description;

  OnboardingPageWithFallback({
    this.imagePath,
    required this.fallbackIcon,
    required this.fallbackGradient,
    required this.title,
    required this.description,
  });

  Widget buildBackground() {
    if (imagePath != null) {
      return Container(
        width: double.infinity,
        height: double.infinity,
        decoration: BoxDecoration(
          image: DecorationImage(
            image: AssetImage(imagePath!),
            fit: BoxFit.cover,
            onError: (exception, stackTrace) {
              // Image failed to load, will show fallback
            },
          ),
        ),
        child: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: fallbackGradient.map((c) => c.withOpacity(0.3)).toList(),
            ),
          ),
        ),
      );
    }

    return OnboardingPlaceholder(
      icon: fallbackIcon,
      gradient: fallbackGradient,
    );
  }

  // Predefined pages with fallbacks
  static List<OnboardingPageWithFallback> defaultPages() {
    return [
      OnboardingPageWithFallback(
        imagePath: 'assets/images/onboarding_1.png',
        fallbackIcon: Icons.search_outlined,
        fallbackGradient: AppColors.primaryGradient,
        title: 'Trouvez l\'artisan idéal',
        description:
            'Découvrez des artisans qualifiés et vérifiés près de chez vous. Comparez les profils, lisez les avis et choisissez le meilleur pour votre projet.',
      ),
      OnboardingPageWithFallback(
        imagePath: 'assets/images/onboarding_2.png',
        fallbackIcon: Icons.account_balance_wallet_outlined,
        fallbackGradient: AppColors.successGradient,
        title: 'Paiement sécurisé',
        description:
            'Votre argent est protégé grâce au système de séquestre. Les paiements sont libérés par étapes selon l\'avancement des travaux.',
      ),
      OnboardingPageWithFallback(
        imagePath: 'assets/images/onboarding_3.png',
        fallbackIcon: Icons.verified_user_outlined,
        fallbackGradient: AppColors.warningGradient,
        title: 'Confiance et transparence',
        description:
            'Un système de notation transparent pour tous. Les artisans construisent leur réputation et accèdent au crédit bancaire.',
      ),
    ];
  }
}
