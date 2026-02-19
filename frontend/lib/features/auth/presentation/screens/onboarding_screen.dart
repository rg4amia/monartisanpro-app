import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/storage/preferences_manager.dart';
import 'login_screen.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  final List<OnboardingPage> _pages = [
    OnboardingPage(
      icon: Icons.search_outlined,
      gradient: AppColors.primaryGradient,
      title: 'Trouvez l\'artisan idéal',
      description:
          'Découvrez des artisans qualifiés et vérifiés près de chez vous. Comparez les profils, lisez les avis et choisissez le meilleur pour votre projet.',
      features: [
        'Recherche géolocalisée',
        'Profils vérifiés avec KYC',
        'Score de réputation N\'Zassa',
      ],
    ),
    OnboardingPage(
      icon: Icons.account_balance_wallet_outlined,
      gradient: AppColors.successGradient,
      title: 'Paiement sécurisé',
      description:
          'Votre argent est protégé grâce au système de séquestre. Les paiements sont libérés par étapes selon l\'avancement des travaux.',
      features: [
        'Escrow automatique',
        'Jetons matériel avec validation GPS',
        'Libération progressive',
      ],
    ),
    OnboardingPage(
      icon: Icons.verified_user_outlined,
      gradient: AppColors.warningGradient,
      title: 'Confiance et transparence',
      description:
          'Un système de notation transparent pour tous. Les artisans construisent leur réputation et accèdent au crédit bancaire.',
      features: [
        'Évaluations vérifiées',
        'Médiation en cas de litige',
        'Historique de transactions',
      ],
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _onPageChanged(int page) {
    setState(() => _currentPage = page);
  }

  void _nextPage() {
    if (_currentPage < _pages.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      _completeOnboarding();
    }
  }

  void _skipOnboarding() {
    _completeOnboarding();
  }

  void _completeOnboarding() {
    // Mark onboarding as seen
    PreferencesManager().setOnboardingSeen();

    // Navigate to login screen
    Get.offAll(() => const LoginScreen());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // Skip Button
            Padding(
              padding: const EdgeInsets.all(Spacing.base),
              child: Align(
                alignment: Alignment.topRight,
                child: TextButton(
                  onPressed: _skipOnboarding,
                  child: const Text('Passer'),
                ),
              ),
            ),

            // Page View
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                onPageChanged: _onPageChanged,
                itemCount: _pages.length,
                itemBuilder: (context, index) {
                  return _buildPage(_pages[index]);
                },
              ),
            ),

            // Page Indicators
            Padding(
              padding: const EdgeInsets.symmetric(vertical: Spacing.lg),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  _pages.length,
                  (index) => _buildPageIndicator(index),
                ),
              ),
            ),

            // Next/Get Started Button
            Padding(
              padding: const EdgeInsets.all(Spacing.screenPadding),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _nextPage,
                  child: Text(
                    _currentPage == _pages.length - 1 ? 'Commencer' : 'Suivant',
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPage(OnboardingPage page) {
    return Padding(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Icon with Gradient Background
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: page.gradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
            ),
            child: Icon(page.icon, size: 64, color: Colors.white),
          ),
          const SizedBox(height: Spacing.xxxl),

          // Title
          Text(
            page.title,
            style: Theme.of(
              context,
            ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.lg),

          // Description
          Text(
            page.description,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: AppColors.lightTextSecondary,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.xl),

          // Features List
          Container(
            padding: const EdgeInsets.all(Spacing.lg),
            decoration: BoxDecoration(
              color: AppColors.lightBackground,
              borderRadius: BorderRadius.circular(Spacing.radiusLg),
            ),
            child: Column(
              children: page.features
                  .map(
                    (feature) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(Spacing.xs),
                            decoration: BoxDecoration(
                              gradient: LinearGradient(colors: page.gradient),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.check,
                              color: Colors.white,
                              size: 16,
                            ),
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: Text(
                              feature,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPageIndicator(int index) {
    final isActive = index == _currentPage;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      margin: const EdgeInsets.symmetric(horizontal: Spacing.xs),
      width: isActive ? 24 : 8,
      height: 8,
      decoration: BoxDecoration(
        color: isActive
            ? Theme.of(context).colorScheme.primary
            : AppColors.lightTextTertiary,
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
      ),
    );
  }
}

class OnboardingPage {
  final IconData icon;
  final List<Color> gradient;
  final String title;
  final String description;
  final List<String> features;

  OnboardingPage({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.description,
    required this.features,
  });
}
