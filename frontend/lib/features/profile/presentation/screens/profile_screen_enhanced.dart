import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../auth/presentation/screens/login_screen.dart';

/// Enhanced Profile Screen with modern design
/// Figma reference: node-id=3125:8012
class ProfileScreenEnhanced extends StatelessWidget {
  const ProfileScreenEnhanced({super.key});

  @override
  Widget build(BuildContext context) {
    final authController = Get.find<AuthController>();

    return Scaffold(
      backgroundColor: AppColors.darkBackground,
      body: SafeArea(
        child: Obx(() {
          final user = authController.currentUser.value;

          if (user == null) {
            return _buildNotLoggedIn();
          }

          return CustomScrollView(
            slivers: [
              _buildHeader(user),
              _buildContent(context, authController, user),
            ],
          );
        }),
      ),
    );
  }

  Widget _buildNotLoggedIn() {
    return Center(
      child: Container(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(Spacing.xl),
              decoration: BoxDecoration(
                color: AppColors.darkCard,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.person_outline,
                size: 80,
                color: AppColors.darkTextTertiary,
              ),
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
            const SizedBox(height: Spacing.sm),
            Text(
              'Connectez-vous pour accéder à votre profil',
              style: TextStyle(
                fontSize: 14,
                color: AppColors.darkTextSecondary,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: Spacing.xl),
            SizedBox(
              width: 200,
              child: ElevatedButton(
                onPressed: () => Get.offAll(() => const LoginScreen()),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: Spacing.base),
                ),
                child: const Text('Se connecter'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(dynamic user) {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.all(Spacing.xl),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              AppColors.darkAccentPrimary,
              AppColors.darkAccentPrimary.withOpacity(0.8),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Column(
          children: [
            // Avatar with edit button
            Stack(
              children: [
                Container(
                  width: 100,
                  height: 100,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 4),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.2),
                        blurRadius: 20,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: user.avatar != null
                      ? CircleAvatar(
                          backgroundImage: NetworkImage(user.avatar!),
                          radius: 48,
                        )
                      : const CircleAvatar(
                          backgroundColor: Colors.white,
                          child: Icon(
                            Icons.person,
                            size: 50,
                            color: AppColors.darkAccentPrimary,
                          ),
                        ),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(Spacing.xs),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.2),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Icon(
                      Icons.camera_alt,
                      size: 18,
                      color: AppColors.darkAccentPrimary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.lg),

            // Name
            Text(
              user.name,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: Spacing.xs),

            // Email
            Text(
              user.email,
              style: TextStyle(
                fontSize: 14,
                color: Colors.white.withOpacity(0.9),
              ),
            ),
            const SizedBox(height: Spacing.md),

            // Role Badge
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.lg,
                vertical: Spacing.sm,
              ),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.2),
                borderRadius: BorderRadius.circular(Spacing.radiusLg),
                border: Border.all(
                  color: Colors.white.withOpacity(0.3),
                  width: 1,
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(_getRoleIcon(user.role), size: 16, color: Colors.white),
                  const SizedBox(width: Spacing.xs),
                  Text(
                    _getRoleLabel(user.role),
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(
    BuildContext context,
    AuthController authController,
    dynamic user,
  ) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Stats Cards (if applicable)
            if (user.role == 'artisan' || user.role == 'client')
              _buildStatsSection(user),

            // Account Section
            _buildSectionHeader('Mon compte'),
            const SizedBox(height: Spacing.md),
            _buildMenuCard([
              _buildMenuItem(
                icon: Icons.person_outline,
                title: 'Informations personnelles',
                subtitle: 'Modifier vos informations',
                onTap: () {
                  Get.snackbar(
                    'À venir',
                    'Fonctionnalité en développement',
                    backgroundColor: AppColors.darkCard,
                    colorText: AppColors.darkTextPrimary,
                  );
                },
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.phone_outlined,
                title: 'Téléphone',
                subtitle: user.phone ?? 'Non renseigné',
                onTap: () {},
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.location_on_outlined,
                title: 'Adresse',
                subtitle: 'Gérer vos adresses',
                onTap: () {},
              ),
            ]),

            const SizedBox(height: Spacing.xl),

            // Settings Section
            _buildSectionHeader('Paramètres'),
            const SizedBox(height: Spacing.md),
            _buildMenuCard([
              _buildMenuItem(
                icon: Icons.notifications_outlined,
                title: 'Notifications',
                subtitle: 'Gérer les notifications',
                onTap: () {
                  Get.snackbar(
                    'À venir',
                    'Fonctionnalité en développement',
                    backgroundColor: AppColors.darkCard,
                    colorText: AppColors.darkTextPrimary,
                  );
                },
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.security_outlined,
                title: 'Sécurité',
                subtitle: 'Mot de passe et authentification',
                onTap: () {},
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.language_outlined,
                title: 'Langue',
                subtitle: 'Français',
                onTap: () {},
              ),
            ]),

            const SizedBox(height: Spacing.xl),

            // Support Section
            _buildSectionHeader('Support & Légal'),
            const SizedBox(height: Spacing.md),
            _buildMenuCard([
              _buildMenuItem(
                icon: Icons.help_outline,
                title: 'Centre d\'aide',
                subtitle: 'FAQ et assistance',
                onTap: () {},
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.chat_bubble_outline,
                title: 'Nous contacter',
                subtitle: 'Envoyer un message',
                onTap: () {},
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.privacy_tip_outlined,
                title: 'Confidentialité',
                subtitle: 'Politique de confidentialité',
                onTap: () {},
              ),
              _buildDivider(),
              _buildMenuItem(
                icon: Icons.description_outlined,
                title: 'Conditions d\'utilisation',
                subtitle: 'CGU et mentions légales',
                onTap: () {},
              ),
            ]),

            const SizedBox(height: Spacing.xl),

            // Logout Button
            Container(
              decoration: BoxDecoration(
                color: AppColors.darkCard,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                border: Border.all(
                  color: AppColors.darkAccentDanger.withOpacity(0.3),
                  width: 1,
                ),
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () => _handleLogout(context, authController),
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  child: Padding(
                    padding: const EdgeInsets.all(Spacing.base),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.logout,
                          color: AppColors.darkAccentDanger,
                          size: 20,
                        ),
                        const SizedBox(width: Spacing.sm),
                        const Text(
                          'Déconnexion',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: AppColors.darkAccentDanger,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            const SizedBox(height: Spacing.lg),

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
    );
  }

  Widget _buildStatsSection(dynamic user) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                icon: Icons.work_outline,
                label: 'Projets',
                value: '12',
                color: AppColors.darkAccentPrimary,
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _buildStatCard(
                icon: Icons.star_outline,
                label: 'Avis',
                value: '4.8',
                color: Color(0xFFF59E0B),
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: _buildStatCard(
                icon: Icons.verified_outlined,
                label: 'Score',
                value: '95',
                color: AppColors.darkAccentSecondary,
              ),
            ),
          ],
        ),
        const SizedBox(height: Spacing.xl),
      ],
    );
  }

  Widget _buildStatCard({
    required IconData icon,
    required String label,
    required String value,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: AppColors.darkCard,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(color: color.withOpacity(0.2), width: 1),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(height: Spacing.xs),
          Text(
            value,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w700,
              color: AppColors.darkTextPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(fontSize: 11, color: AppColors.darkTextSecondary),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w700,
        color: AppColors.darkTextPrimary,
      ),
    );
  }

  Widget _buildMenuCard(List<Widget> children) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.darkCard,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(color: AppColors.overlayLight, width: 1),
      ),
      child: Column(children: children),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.base),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(Spacing.sm),
                decoration: BoxDecoration(
                  color: AppColors.darkAccentPrimary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(Spacing.radiusSm),
                ),
                child: Icon(icon, color: AppColors.darkAccentPrimary, size: 20),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: AppColors.darkTextPrimary,
                      ),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: TextStyle(
                          fontSize: 13,
                          color: AppColors.darkTextSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right,
                color: AppColors.darkTextTertiary,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDivider() {
    return Divider(
      height: 1,
      thickness: 1,
      color: AppColors.overlayLight,
      indent: Spacing.base,
      endIndent: Spacing.base,
    );
  }

  Future<void> _handleLogout(
    BuildContext context,
    AuthController authController,
  ) async {
    final confirm = await Get.dialog<bool>(
      AlertDialog(
        backgroundColor: AppColors.darkCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusLg),
        ),
        title: const Text(
          'Déconnexion',
          style: TextStyle(
            color: AppColors.darkTextPrimary,
            fontWeight: FontWeight.w700,
          ),
        ),
        content: const Text(
          'Êtes-vous sûr de vouloir vous déconnecter?',
          style: TextStyle(color: AppColors.darkTextSecondary),
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
  }

  String _getRoleLabel(String role) {
    switch (role.toLowerCase()) {
      case 'client':
        return 'Client';
      case 'artisan':
        return 'Artisan';
      case 'vendor':
      case 'fournisseur':
        return 'Fournisseur';
      case 'admin':
        return 'Administrateur';
      default:
        return role;
    }
  }

  IconData _getRoleIcon(String role) {
    switch (role.toLowerCase()) {
      case 'client':
        return Icons.person;
      case 'artisan':
        return Icons.build;
      case 'vendor':
      case 'fournisseur':
        return Icons.store;
      case 'admin':
        return Icons.admin_panel_settings;
      default:
        return Icons.person;
    }
  }
}
