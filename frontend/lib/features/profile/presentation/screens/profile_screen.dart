import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../auth/presentation/screens/login_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authController = Get.find<AuthController>();

    return Scaffold(
      backgroundColor: AppColors.darkBackground,
      body: SafeArea(
        child: Obx(() {
          final user = authController.currentUser.value;

          if (user == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.person_outline,
                    size: 80,
                    color: AppColors.darkTextTertiary,
                  ),
                  const SizedBox(height: Spacing.xl),
                  const Text(
                    'Non connecté',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                      color: AppColors.darkTextPrimary,
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  ElevatedButton(
                    onPressed: () => Get.offAll(() => const LoginScreen()),
                    child: const Text('Se connecter'),
                  ),
                ],
              ),
            );
          }

          return CustomScrollView(
            slivers: [
              // App Bar
              SliverAppBar(
                expandedHeight: 200,
                pinned: true,
                backgroundColor: AppColors.darkCard,
                flexibleSpace: FlexibleSpaceBar(
                  background: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          AppColors.darkAccentPrimary,
                          AppColors.darkAccentPrimary.withValues(alpha: 0.7),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const SizedBox(height: 40),
                        // Avatar
                        Container(
                          width: 80,
                          height: 80,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 3),
                          ),
                          child: user.avatar != null
                              ? CircleAvatar(
                                  backgroundImage: NetworkImage(user.avatar!),
                                  radius: 38,
                                )
                              : const CircleAvatar(
                                  backgroundColor: Colors.white,
                                  child: Icon(
                                    Icons.person,
                                    size: 40,
                                    color: AppColors.darkAccentPrimary,
                                  ),
                                ),
                        ),
                        const SizedBox(height: Spacing.md),
                        // Name
                        Text(
                          user.name,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: Spacing.xs),
                        // Role Badge
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.md,
                            vertical: Spacing.xs,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusSm,
                            ),
                          ),
                          child: Text(
                            _getRoleLabel(user.role),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              // Content
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Account Section
                      _buildSectionTitle('Mon compte'),
                      const SizedBox(height: Spacing.md),
                      _buildMenuItem(
                        icon: Icons.person_outline,
                        title: 'Informations personnelles',
                        onTap: () {
                          Get.snackbar(
                            'À venir',
                            'Fonctionnalité en développement',
                            backgroundColor: AppColors.darkCard,
                            colorText: AppColors.darkTextPrimary,
                          );
                        },
                      ),
                      _buildMenuItem(
                        icon: Icons.email_outlined,
                        title: 'Email',
                        subtitle: user.email,
                        onTap: () {},
                      ),
                      _buildMenuItem(
                        icon: Icons.phone_outlined,
                        title: 'Téléphone',
                        subtitle: user.phone ?? 'Non renseigné',
                        onTap: () {},
                      ),

                      const SizedBox(height: Spacing.xl),

                      // Settings Section
                      _buildSectionTitle('Paramètres'),
                      const SizedBox(height: Spacing.md),
                      _buildMenuItem(
                        icon: Icons.notifications_outlined,
                        title: 'Notifications',
                        onTap: () {
                          Get.snackbar(
                            'À venir',
                            'Fonctionnalité en développement',
                            backgroundColor: AppColors.darkCard,
                            colorText: AppColors.darkTextPrimary,
                          );
                        },
                      ),
                      _buildMenuItem(
                        icon: Icons.language_outlined,
                        title: 'Langue',
                        subtitle: 'Français',
                        onTap: () {},
                      ),
                      _buildMenuItem(
                        icon: Icons.dark_mode_outlined,
                        title: 'Thème',
                        subtitle: 'Sombre',
                        onTap: () {},
                      ),

                      const SizedBox(height: Spacing.xl),

                      // Support Section
                      _buildSectionTitle('Support'),
                      const SizedBox(height: Spacing.md),
                      _buildMenuItem(
                        icon: Icons.help_outline,
                        title: 'Centre d\'aide',
                        onTap: () {},
                      ),
                      _buildMenuItem(
                        icon: Icons.privacy_tip_outlined,
                        title: 'Politique de confidentialité',
                        onTap: () {},
                      ),
                      _buildMenuItem(
                        icon: Icons.description_outlined,
                        title: 'Conditions d\'utilisation',
                        onTap: () {},
                      ),

                      const SizedBox(height: Spacing.xl),

                      // Logout Button
                      OutlinedButton.icon(
                        onPressed: () async {
                          final confirm = await Get.dialog<bool>(
                            AlertDialog(
                              backgroundColor: AppColors.darkCard,
                              title: const Text(
                                'Déconnexion',
                                style: TextStyle(
                                  color: AppColors.darkTextPrimary,
                                ),
                              ),
                              content: const Text(
                                'Êtes-vous sûr de vouloir vous déconnecter?',
                                style: TextStyle(
                                  color: AppColors.darkTextSecondary,
                                ),
                              ),
                              actions: [
                                TextButton(
                                  onPressed: () => Get.back(result: false),
                                  child: const Text('Annuler'),
                                ),
                                ElevatedButton(
                                  onPressed: () => Get.back(result: true),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.darkAccentDanger,
                                  ),
                                  child: const Text('Déconnexion'),
                                ),
                              ],
                            ),
                          );

                          if (confirm == true) {
                            await authController.logout();
                            Get.offAll(() => const LoginScreen());
                          }
                        },
                        icon: const Icon(Icons.logout),
                        label: const Text('Déconnexion'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.darkAccentDanger,
                          side: const BorderSide(
                            color: AppColors.darkAccentDanger,
                          ),
                          padding: const EdgeInsets.symmetric(
                            vertical: Spacing.base,
                          ),
                        ),
                      ),

                      const SizedBox(height: Spacing.md),

                      // Version
                      Center(
                        child: Text(
                          'Version 1.0.0',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.darkTextTertiary,
                          ),
                        ),
                      ),

                      const SizedBox(height: Spacing.xl),
                    ],
                  ),
                ),
              ),
            ],
          );
        }),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w700,
        color: AppColors.darkTextPrimary,
      ),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: Spacing.sm),
      decoration: BoxDecoration(
        color: AppColors.darkCard,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(Spacing.sm),
          decoration: BoxDecoration(
            color: AppColors.overlayLight,
            borderRadius: BorderRadius.circular(Spacing.radiusSm),
          ),
          child: Icon(icon, color: AppColors.darkAccentPrimary, size: 20),
        ),
        title: Text(
          title,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.darkTextPrimary,
          ),
        ),
        subtitle: subtitle != null
            ? Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12,
                  color: AppColors.darkTextSecondary,
                ),
              )
            : null,
        trailing: Icon(Icons.chevron_right, color: AppColors.darkTextTertiary),
        onTap: onTap,
      ),
    );
  }

  String _getRoleLabel(String role) {
    switch (role) {
      case 'client':
        return 'Client';
      case 'artisan':
        return 'Artisan';
      case 'vendor':
        return 'Fournisseur';
      case 'admin':
        return 'Administrateur';
      default:
        return role;
    }
  }
}
