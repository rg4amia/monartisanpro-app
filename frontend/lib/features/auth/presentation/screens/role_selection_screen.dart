import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import 'register_screen.dart';

class RoleSelectionScreen extends StatelessWidget {
  const RoleSelectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: Spacing.xxxl),

              // Logo/Icon
              Icon(
                Icons.build_circle,
                size: 100,
                color: Theme.of(context).colorScheme.primary,
              ),
              const SizedBox(height: Spacing.xl),

              // Title
              Text(
                'Bienvenue sur ProsArtisan',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.md),

              // Subtitle
              Text(
                'La plateforme qui connecte artisans et clients en toute confiance',
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: Spacing.xxxl),

              // Section Header
              Text(
                'Choisissez votre profil',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: Spacing.lg),

              // Client Role Card
              _buildRoleCard(
                context: context,
                icon: Icons.person_outline,
                title: 'Client',
                description:
                    'Je cherche un artisan qualifié pour réaliser mes projets',
                features: [
                  'Trouver des artisans vérifiés',
                  'Paiement sécurisé via escrow',
                  'Suivi en temps réel',
                ],
                gradient: AppColors.primaryGradient,
                onTap: () => _navigateToRegister(context, 'client'),
              ),
              const SizedBox(height: Spacing.lg),

              // Artisan Role Card
              _buildRoleCard(
                context: context,
                icon: Icons.build_outlined,
                title: 'Artisan',
                description:
                    'Je propose mes services et développe mon activité',
                features: [
                  'Recevoir des demandes de devis',
                  'Score de réputation N\'Zassa',
                  'Paiements garantis',
                ],
                gradient: AppColors.successGradient,
                onTap: () => _navigateToRegister(context, 'artisan'),
              ),
              const SizedBox(height: Spacing.lg),

              // Fournisseur Role Card
              _buildRoleCard(
                context: context,
                icon: Icons.store_outlined,
                title: 'Fournisseur',
                description:
                    'Je vends des matériaux et équipements de construction',
                features: [
                  'Accepter les jetons matériel',
                  'Élargir ma clientèle',
                  'Paiements instantanés',
                ],
                gradient: AppColors.warningGradient,
                onTap: () => _navigateToRegister(context, 'fournisseur'),
              ),
              const SizedBox(height: Spacing.xxxl),

              // Login Link
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Vous avez déjà un compte? ',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  TextButton(
                    onPressed: () => Get.back(),
                    child: const Text('Se connecter'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildRoleCard({
    required BuildContext context,
    required IconData icon,
    required String title,
    required String description,
    required List<String> features,
    required List<Color> gradient,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Spacing.radiusLg),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: gradient,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(Spacing.radiusLg),
          boxShadow: [
            BoxShadow(
              color: gradient[0].withValues(alpha: 0.3),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icon and Title Row
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(Spacing.md),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    ),
                    child: Icon(
                      icon,
                      size: 32,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                  ),
                  const Icon(
                    Icons.arrow_forward_ios,
                    color: Colors.white,
                    size: 20,
                  ),
                ],
              ),
              const SizedBox(height: Spacing.md),

              // Description
              Text(
                description,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.9),
                    ),
              ),
              const SizedBox(height: Spacing.md),

              // Features List
              ...features.map((feature) => Padding(
                    padding: const EdgeInsets.only(bottom: Spacing.xs),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.check_circle,
                          color: Colors.white,
                          size: 16,
                        ),
                        const SizedBox(width: Spacing.xs),
                        Expanded(
                          child: Text(
                            feature,
                            style:
                                Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Colors.white.withValues(alpha: 0.85),
                                    ),
                          ),
                        ),
                      ],
                    ),
                  )),
            ],
          ),
        ),
      ),
    );
  }

  void _navigateToRegister(BuildContext context, String role) {
    Get.to(() => const RegisterScreen(), arguments: {'role': role});
  }
}
