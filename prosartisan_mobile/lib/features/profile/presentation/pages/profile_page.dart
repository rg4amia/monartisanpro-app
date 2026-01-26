import 'package:flutter/material.dart' hide TextButton;
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/navigation/bottom_nav_bar.dart';
import '../../../../shared/widgets/buttons/primary_button.dart'
    show PrimaryButton, SecondaryButton, TextButton;

/// Page du profil utilisateur
class ProfilePage extends StatelessWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Mon Profil',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () {
              // Naviguer vers les paramètres
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: AppSpacing.screenPaddingAll,
        child: Column(
          children: [
            // Avatar et info utilisateur
            Container(
              padding: AppSpacing.cardPaddingAll,
              decoration: BoxDecoration(
                color: AppColors.cardBg,
                borderRadius: AppRadius.cardRadius,
              ),
              child: Column(
                children: [
                  // Avatar
                  Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: AppColors.accentPrimary,
                      borderRadius: AppRadius.circular(40),
                    ),
                    child: const Icon(
                      Icons.person,
                      size: 40,
                      color: Colors.white,
                    ),
                  ),

                  const SizedBox(height: AppSpacing.base),

                  // Nom
                  Text(
                    'Amadou Diallo',
                    style: AppTypography.h3.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),

                  const SizedBox(height: AppSpacing.xs),

                  // Email
                  Text(
                    'amadou.diallo@example.com',
                    style: AppTypography.bodySmall.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),

                  const SizedBox(height: AppSpacing.base),

                  // Bouton modifier profil
                  SecondaryButton(
                    text: 'Modifier le profil',
                    onPressed: () {
                      // Naviguer vers l'édition du profil
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: AppSpacing.xl),

            // Options du menu
            _buildMenuSection(),

            const SizedBox(height: AppSpacing.xl),

            // Bouton déconnexion
            PrimaryButton(
              text: 'Se déconnecter',
              backgroundColor: AppColors.accentDanger,
              onPressed: () {
                // Gérer la déconnexion
                _showLogoutDialog(context);
              },
            ),
          ],
        ),
      ),
      bottomNavigationBar: CustomBottomNavBar(
        items: DefaultBottomNavItems.items,
        currentRoute: 'profile',
        onItemTapped: (route) {
          if (route != 'profile') {
            Navigator.pushReplacementNamed(context, '/$route');
          }
        },
      ),
    );
  }

  Widget _buildMenuSection() {
    final menuItems = [
      {
        'icon': Icons.history,
        'title': 'Historique des services',
        'route': '/history',
      },
      {
        'icon': Icons.favorite_outline,
        'title': 'Mes favoris',
        'route': '/favorites',
      },
      {
        'icon': Icons.payment,
        'title': 'Moyens de paiement',
        'route': '/payment-methods',
      },
      {
        'icon': Icons.help_outline,
        'title': 'Aide et support',
        'route': '/help',
      },
      {'icon': Icons.info_outline, 'title': 'À propos', 'route': '/about'},
    ];

    return Container(
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
      ),
      child: Column(
        children: menuItems.map((item) {
          final isLast = menuItems.last == item;
          return Column(
            children: [
              ListTile(
                leading: Icon(
                  item['icon'] as IconData,
                  color: AppColors.textSecondary,
                ),
                title: Text(
                  item['title'] as String,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),
                trailing: const Icon(
                  Icons.chevron_right,
                  color: AppColors.textMuted,
                ),
                onTap: () {
                  // Naviguer vers la route
                },
              ),
              if (!isLast)
                Divider(height: 1, color: AppColors.overlayMedium, indent: 56),
            ],
          );
        }).toList(),
      ),
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.cardBg,
        title: Text(
          'Déconnexion',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        content: Text(
          'Êtes-vous sûr de vouloir vous déconnecter ?',
          style: AppTypography.body.copyWith(color: AppColors.textSecondary),
        ),
        actions: [
          TextButton(
            text: 'Annuler',
            onPressed: () => Navigator.pop(context),
            textColor: AppColors.textSecondary,
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              // Gérer la déconnexion
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.accentDanger,
            ),
            child: const Text('Déconnecter'),
          ),
        ],
      ),
    );
  }
}
