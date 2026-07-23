import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/utils/formatters.dart';
import '../../../app/routes/app_routes.dart';
import '../controllers/settings_controller.dart';

import '../../main_tab/controllers/main_tab_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const success = Color(0xFF10B981);
  static const successLight = Color(0xFFD1FAE5);
  static const danger = Color(0xFFEF4444);
  static const dangerLight = Color(0xFFFEE2E2);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

class SettingsScreen extends GetView<SettingsController> {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            _AppBar(),
            SliverToBoxAdapter(child: _ProfileHeader(controller: controller)),
            SliverToBoxAdapter(child: const _SpaceSwitcherCard()),
            SliverToBoxAdapter(child: _StatsRow(controller: controller)),
            SliverToBoxAdapter(child: const SizedBox(height: 8)),
            SliverToBoxAdapter(child: _MenuList(controller: controller)),
            SliverToBoxAdapter(child: const SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            GestureDetector(
              onTap: () => Get.back(),
              child: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: _C.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _C.subtle),
                ),
                child: const Icon(Icons.arrow_back, size: 20),
              ),
            ),
            const Text(
              'Profil',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
            GestureDetector(
              onTap: () {},
              child: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: _C.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _C.subtle),
                ),
                child: const Icon(Icons.settings_outlined, size: 20),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Profile Header ───────────────────────────────────────────────────────────
class _ProfileHeader extends StatelessWidget {
  final SettingsController controller;
  const _ProfileHeader({required this.controller});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Get.toNamed(Routes.updateProfile),
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Column(
          children: [
            Stack(
              children: [
                Container(
                  width: 120,
                  height: 120,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: _C.subtle, width: 3),
                    color: _C.primaryLight,
                  ),
                  child: Center(
                    child: Text(
                      Formatters.initial(controller.userName.value),
                      style: const TextStyle(
                        fontSize: 48,
                        fontWeight: FontWeight.w700,
                        color: _C.primary,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: _C.primary,
                      shape: BoxShape.circle,
                      border: Border.all(color: _C.surface, width: 3),
                    ),
                    child: const Icon(Icons.edit, color: Colors.white, size: 16),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              controller.userName.value.isEmpty
                  ? 'Profil Utilisateur'
                  : controller.userName.value,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w700,
                color: _C.ink,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              controller.userPhone.value.isEmpty
                  ? 'Pas d\'email'
                  : controller.userPhone.value,
              style: const TextStyle(
                fontSize: 14,
                color: _C.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Stats Row ────────────────────────────────────────────────────────────────
class _StatsRow extends StatelessWidget {
  final SettingsController controller;
  const _StatsRow({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
      child: Obx(() => Row(
            children: [
              Expanded(
                child: _StatCard(
                  label: 'Solde',
                  value:
                      'FCFA ${_formatBalance(controller.walletBalance.value)}',
                  color: _C.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _StatCard(
                  label: 'Commandes',
                  value: '${controller.ordersCount.value}',
                  color: _C.primary,
                ),
              ),
            ],
          )),
    );
  }

  String _formatBalance(int balance) {
    if (balance >= 1000) {
      return '${(balance / 1000).toStringAsFixed(1)}k';
    }
    return balance.toString();
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _StatCard({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: color,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: 13,
              color: _C.muted,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Menu List ────────────────────────────────────────────────────────────────
class _MenuList extends StatelessWidget {
  final SettingsController controller;
  const _MenuList({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        children: [
          _MenuItem(
            icon: Icons.person_outline,
            iconBg: _C.primaryLight,
            iconColor: _C.primary,
            title: 'Profil & Localisation',
            subtitle: 'Nom, e-mail et adresse géographique',
            onTap: () => Get.toNamed(Routes.updateProfile),
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.credit_card_outlined,
            iconBg: _C.primaryLight,
            iconColor: _C.primary,
            title: 'Modes de Paiement',
            subtitle: 'Cartes, Mobile Money, Portefeuilles',
            onTap: () {},
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.shield_outlined,
            iconBg: _C.successLight,
            iconColor: _C.success,
            title: 'Sécurité & Statut KYC',
            subtitle: 'Sécurisez votre compte et vos limites',
            trailing: _VerifiedBadge(status: controller.kycStatus.value),
            onTap: controller.kycStatus.value != 'actif'
                ? () => Get.toNamed(Routes.kycCni)
                : null,
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.help_outline,
            iconBg: _C.primaryLight,
            iconColor: _C.primary,
            title: 'Centre d\'aide',
            subtitle: 'FAQs et support client',
            onTap: () {},
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.phone_android_outlined,
            iconBg: _C.primaryLight,
            iconColor: _C.primary,
            title: 'Modifier le numéro de téléphone',
            subtitle: 'Mettre à jour vos paramètres de connexion',
            onTap: () => _showChangePhoneDialog(context, controller),
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.logout,
            iconBg: _C.dangerLight,
            iconColor: _C.danger,
            title: 'Déconnexion',
            subtitle: 'Se déconnecter de votre compte',
            onTap: () => _confirmLogout(context, controller),
          ),
        ],
      ),
    );
  }

  void _confirmLogout(BuildContext context, SettingsController controller) {
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Déconnexion'),
        content: const Text('Êtes-vous sûr de vouloir vous déconnecter ?'),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.logout();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: _C.danger,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: const Text('Déconnexion'),
          ),
        ],
      ),
    );
  }

  void _showChangePhoneDialog(BuildContext context, SettingsController controller) {
    final phoneCtrl = TextEditingController(text: controller.userPhone.value);
    final otpCtrl = TextEditingController();

    controller.isChangePhoneOtpSent.value = false;
    controller.changePhoneError.value = null;

    Get.dialog(
      Obx(() => AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            title: const Text(
              'Modifier le numéro',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Entrez votre nouveau numéro de téléphone (+225). Un code de validation OTP vous sera envoyé.',
                    style: TextStyle(color: _C.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: phoneCtrl,
                    enabled: !controller.isChangePhoneOtpSent.value,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Nouveau numéro (+225)',
                      border: OutlineInputBorder(),
                      hintText: '+2250707000000',
                    ),
                  ),
                  if (controller.isChangePhoneOtpSent.value) ...[
                    const SizedBox(height: 16),
                    const Divider(),
                    const SizedBox(height: 8),
                    const Text(
                      'Saisissez le code OTP reçu :',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: otpCtrl,
                      keyboardType: TextInputType.number,
                      maxLength: 4,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, letterSpacing: 8),
                      decoration: const InputDecoration(
                        border: OutlineInputBorder(),
                        counterText: '',
                        hintText: '0000',
                      ),
                    ),
                  ],
                  if (controller.changePhoneError.value != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      controller.changePhoneError.value!,
                      style: const TextStyle(color: _C.danger, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Annuler'),
              ),
              ElevatedButton(
                onPressed: controller.isChangingPhone.value
                    ? null
                    : () async {
                        final newPhone = phoneCtrl.text.trim();
                        if (newPhone.isEmpty || !newPhone.startsWith('+225') || newPhone.length < 14) {
                          Get.snackbar(
                            'Numéro invalide',
                            'Veuillez entrer un numéro valide au format +225XXXXXXXXXX',
                            backgroundColor: _C.danger,
                            colorText: Colors.white,
                          );
                          return;
                        }

                        if (!controller.isChangePhoneOtpSent.value) {
                          final success = await controller.requestChangePhone(newPhone);
                          if (success) {
                            Get.snackbar(
                              'OTP envoyé',
                              'Un code OTP a été envoyé sur le nouveau numéro.',
                              backgroundColor: _C.success,
                              colorText: Colors.white,
                            );
                          } else {
                            Get.snackbar(
                              'Erreur',
                              controller.changePhoneError.value ?? 'Impossible d\'envoyer le code.',
                              backgroundColor: _C.danger,
                              colorText: Colors.white,
                            );
                          }
                        } else {
                          final otp = otpCtrl.text.trim();
                          if (otp.length != 4) {
                            Get.snackbar(
                              'OTP requis',
                              'Veuillez saisir le code OTP à 4 chiffres.',
                              backgroundColor: _C.danger,
                              colorText: Colors.white,
                            );
                            return;
                          }

                          final success = await controller.confirmChangePhone(newPhone, otp);
                          if (success) {
                            Get.back();
                            Get.snackbar(
                              'Numéro modifié',
                              'Votre numéro de téléphone de connexion a été mis à jour.',
                              backgroundColor: _C.success,
                              colorText: Colors.white,
                            );
                          } else {
                            Get.snackbar(
                              'Code OTP erroné',
                              controller.changePhoneError.value ?? 'Le code saisi est invalide.',
                              backgroundColor: _C.danger,
                              colorText: Colors.white,
                            );
                          }
                        }
                      },
                child: controller.isChangingPhone.value
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Text(controller.isChangePhoneOtpSent.value ? 'Confirmer' : 'Suivant'),
              ),
            ],
          )),
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final Color iconBg;
  final Color iconColor;
  final String title;
  final String subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;

  const _MenuItem({
    required this.icon,
    required this.iconBg,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    this.trailing,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: _C.subtle),
          boxShadow: [
            BoxShadow(
              color: _C.ink.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: iconBg,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: iconColor, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: _C.ink,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: _C.muted,
                    ),
                  ),
                ],
              ),
            ),
            trailing ??
                const Icon(
                  Icons.chevron_right,
                  color: _C.muted,
                  size: 20,
                ),
          ],
        ),
      ),
    );
  }
}

class _VerifiedBadge extends StatelessWidget {
  final String status;
  const _VerifiedBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    if (status != 'actif') {
      return const Icon(Icons.chevron_right, color: _C.muted, size: 20);
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: _C.successLight,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.check_circle, size: 14, color: _C.success),
          const SizedBox(width: 4),
          const Text(
            'VÉRIFIÉ',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: _C.success,
              letterSpacing: 0.5,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Carte de Changement d'Espace ─────────────────────────────────────────────
class _SpaceSwitcherCard extends StatelessWidget {
  const _SpaceSwitcherCard();

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<MainTabController>()) return const SizedBox.shrink();
    final tabCtrl = Get.find<MainTabController>();

    final roles = [
      (key: 'client', label: 'Client', icon: Icons.person_rounded, color: const Color(0xFF2F6FED)),
      (key: 'artisan', label: 'Artisan', icon: Icons.engineering_rounded, color: const Color(0xFFE67E22)),
      (key: 'fournisseur', label: 'Fournisseur', icon: Icons.storefront_rounded, color: const Color(0xFF27AE60)),
      (key: 'driver', label: 'Livreur', icon: Icons.two_wheeler_rounded, color: const Color(0xFF1ABC9C)),
    ];

    return Obx(() {
      final currentRole = tabCtrl.role.value ?? 'client';

      return Container(
        margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'ESPACES DE NAVIGATION',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: Color(0xFF6B7280),
                letterSpacing: 0.8,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: roles.map((r) {
                final isActive = currentRole == r.key ||
                    (r.key == 'driver' &&
                        (currentRole == 'livreur' || currentRole == 'LIVREUR'));
                return Expanded(
                  child: GestureDetector(
                    onTap: () => tabCtrl.switchSpace(r.key),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color: isActive
                            ? r.color.withValues(alpha: 0.12)
                            : const Color(0xFFF9FAFB),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: isActive ? r.color : const Color(0xFFE5E7EB),
                          width: isActive ? 1.5 : 1.0,
                        ),
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(r.icon,
                              size: 20,
                              color: isActive ? r.color : const Color(0xFF9CA3AF)),
                          const SizedBox(height: 4),
                          Text(
                            r.label,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight:
                                  isActive ? FontWeight.w700 : FontWeight.w500,
                              color: isActive ? r.color : const Color(0xFF6B7280),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ],
        ),
      );
    });
  }
}
