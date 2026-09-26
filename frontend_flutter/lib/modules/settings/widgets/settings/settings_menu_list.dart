import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../controllers/settings_controller.dart';
import '../../views/legal_terms_screen.dart';
import 'my_evaluations_dialog.dart';
import 'settings_colors.dart';
import 'settings_dialogs.dart';
import 'settings_header.dart';

// ─── Menu List ────────────────────────────────────────────────────────────────
class SettingsMenuList extends StatelessWidget {
  final SettingsController controller;
  const SettingsMenuList({super.key, required this.controller});

  @override
  Widget build(BuildContext context) {
    const orangeAccent = Color(0xFFF97316);
    const orangeAccentLight = Color(0xFFFFF3EB);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section Paramètres
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: SettingsColors.surface,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: SettingsColors.subtle),
              boxShadow: [
                BoxShadow(
                  color: SettingsColors.ink.withValues(alpha: 0.03),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.settings_outlined,
                      color: orangeAccent,
                      size: 24,
                    ),
                    const SizedBox(width: 12),
                    const Text(
                      'Paramètres',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: SettingsColors.ink,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Notifications switch Row
                Obx(
                  () => Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: orangeAccentLight,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.notifications_none_outlined,
                          color: orangeAccent,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 14),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Notifications',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: SettingsColors.ink,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Activer/désactiver toutes les notifications',
                              style: TextStyle(
                                fontSize: 12,
                                color: SettingsColors.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Switch(
                        value: controller.notificationsEnabled.value,
                        onChanged: controller.toggleNotifications,
                        activeThumbColor: orangeAccent,
                        activeTrackColor: orangeAccent.withValues(alpha: 0.3),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),
                const Divider(height: 1, color: SettingsColors.subtle),
                const SizedBox(height: 16),

                // Son switch Row
                Obx(
                  () => Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: orangeAccentLight,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.volume_up_outlined,
                          color: orangeAccent,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 14),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Son',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                                color: SettingsColors.ink,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Activer le son des notifications',
                              style: TextStyle(
                                fontSize: 12,
                                color: SettingsColors.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Switch(
                        value: controller.notificationSoundEnabled.value,
                        onChanged: controller.toggleNotificationSound,
                        activeThumbColor: orangeAccent,
                        activeTrackColor: orangeAccent.withValues(alpha: 0.3),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          SettingsMenuItem(
            icon: Icons.person_outline,
            iconBg: SettingsColors.primaryLight,
            iconColor: SettingsColors.primary,
            title: 'Profil & Localisation',
            subtitle: 'Nom, e-mail et adresse géographique',
            onTap: () => Get.toNamed(Routes.updateProfile),
          ),
          const SizedBox(height: 12),
          Obx(() {
            final isClient = controller.userRole.value == 'client';
            final hasPaymentPhone = controller.paymentPhone.value.isNotEmpty;
            final providerName = controller.preferredPaymentProvider.value
                .toUpperCase()
                .replaceAll('_', ' ');

            final title = isClient
                ? 'Moyen de paiement Mobile Money'
                : 'Reversement Mobile Money';
            final subtitle = hasPaymentPhone
                ? '$providerName : ${controller.paymentPhone.value}'
                : (isClient
                    ? 'Associer un numéro Wave, Orange, MTN ou Moov'
                    : 'Numéro de réception des fonds (Wave, Orange, MTN, Moov)');

            return SettingsMenuItem(
              icon: isClient
                  ? Icons.payments_outlined
                  : Icons.account_balance_wallet_outlined,
              iconBg: const Color(0xFFECFDF5),
              iconColor: const Color(0xFF10B981),
              title: title,
              subtitle: subtitle,
              trailing: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: hasPaymentPhone
                      ? const Color(0xFFECFDF5)
                      : const Color(0xFFFFF3EB),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: hasPaymentPhone
                        ? const Color(0xFF10B981).withValues(alpha: 0.3)
                        : const Color(0xFFF97316).withValues(alpha: 0.3),
                  ),
                ),
                child: Text(
                  hasPaymentPhone ? 'CONFIGURÉ' : 'À ASSOCIER',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: hasPaymentPhone
                        ? const Color(0xFF10B981)
                        : const Color(0xFFF97316),
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              onTap: () => showPaymentPhoneDialog(context, controller),
            );
          }),
          const SizedBox(height: 12),
          SettingsMenuItem(
            icon: Icons.receipt_long_outlined,
            iconBg: const Color(0xFFEFF6FF),
            iconColor: const Color(0xFF3B82F6),
            title: 'Historique des Paiements',
            subtitle: 'Gains débloqués, jalons et transactions reçues',
            onTap: () => Get.toNamed(Routes.wallet),
          ),
          const SizedBox(height: 12),
          Obx(
            () => SettingsMenuItem(
              icon: Icons.shield_outlined,
              iconBg: SettingsColors.successLight,
              iconColor: SettingsColors.success,
              title: 'Sécurité & Statut KYC',
              subtitle: 'Sécurisez votre compte et vos limites',
              trailing:
                  SettingsVerifiedBadge(status: controller.kycStatus.value),
              onTap: controller.kycStatus.value != 'actif'
                  ? () => Get.toNamed(Routes.kycCni)
                  : null,
            ),
          ),
          const SizedBox(height: 12),
          SettingsMenuItem(
            icon: Icons.phone_android_outlined,
            iconBg: SettingsColors.primaryLight,
            iconColor: SettingsColors.primary,
            title: 'Modifier le numéro de téléphone',
            subtitle: 'Mettre à jour vos paramètres de connexion',
            onTap: () => showChangePhoneDialog(context, controller),
          ),
          const SizedBox(height: 12),
          SettingsMenuItem(
            icon: Icons.star_outline_rounded,
            iconBg: const Color(0xFFFEF3C7),
            iconColor: const Color(0xFFD97706),
            title: 'Mes avis & évaluations',
            subtitle: 'Consulter l\'historique des prestations notées',
            onTap: () => showMyEvaluationsDialog(context),
          ),
          const SizedBox(height: 12),
          Obx(() {
            final role = controller.userRole.value;
            if (role == 'artisan') {
              return SettingsMenuItem(
                icon: Icons.card_giftcard_outlined,
                iconBg: const Color(0xFFECFDF5),
                iconColor: const Color(0xFF059669),
                title: 'Parrainage',
                subtitle: 'Parrainez des apprentis artisans',
                onTap: () => Get.toNamed(Routes.parrainage),
              );
            }
            if (role == 'client') {
              return SettingsMenuItem(
                icon: Icons.card_giftcard_outlined,
                iconBg: const Color(0xFFECFDF5),
                iconColor: const Color(0xFF059669),
                title: 'Parrainage',
                subtitle: 'Parrainez vos proches, gagnez des réductions',
                onTap: () => Get.toNamed(Routes.parrainageClient),
              );
            }
            return const SizedBox.shrink();
          }),
          const SizedBox(height: 12),

          // Conditions d'utilisation
          SettingsMenuItem(
            icon: Icons.assignment_outlined,
            iconBg: const Color(0xFFF3E8FF),
            iconColor: const Color(0xFF9333EA),
            title: 'Conditions d\'utilisation',
            subtitle: 'Consulter nos conditions générales d\'utilisation',
            onTap: () => Get.to(() => const LegalTermsScreen(initialTab: 0)),
          ),
          const SizedBox(height: 12),

          // Politique de confidentialité
          SettingsMenuItem(
            icon: Icons.security_outlined,
            iconBg: const Color(0xFFDCFCE7),
            iconColor: const Color(0xFF16A34A),
            title: 'Politique de confidentialité',
            subtitle: 'Protection des données et conformité ARTCI',
            onTap: () => Get.to(() => const LegalTermsScreen(initialTab: 1)),
          ),
          const SizedBox(height: 12),

          // Recrutement BTP & Métiers — artisan (postuler) / client & fournisseur (recruter)
          Obx(() {
            final role = controller.userRole.value;
            if (role == 'artisan') {
              return Column(
                children: [
                  SettingsMenuItem(
                    icon: Icons.work_outline_rounded,
                    iconBg: const Color(0xFFEFF6FF),
                    iconColor: const Color(0xFF2563EB),
                    title: 'Offres de recrutement',
                    subtitle:
                        'Postulez aux offres de chantier près de chez vous',
                    onTap: () => Get.toNamed(Routes.recruitmentOffers),
                  ),
                  const SizedBox(height: 12),
                  // Jury ProsArtisan (Chantier 12) : dossiers d'arbitrage
                  // confiés aux artisans les mieux notés de leur métier.
                  SettingsMenuItem(
                    icon: Icons.balance_outlined,
                    iconBg: const Color(0xFFEEF2FF),
                    iconColor: const Color(0xFF4F46E5),
                    title: 'Espace juré',
                    subtitle: 'Litiges de votre métier soumis à votre avis',
                    onTap: () => Get.toNamed(Routes.juryDossiers),
                  ),
                  const SizedBox(height: 12),
                ],
              );
            }
            if (role == 'client' || role == 'fournisseur') {
              return Column(
                children: [
                  SettingsMenuItem(
                    icon: Icons.groups_outlined,
                    iconBg: const Color(0xFFEFF6FF),
                    iconColor: const Color(0xFF2563EB),
                    title: 'Recruter de la main-d\'œuvre',
                    subtitle: 'Publiez une offre et recevez des candidatures',
                    onTap: () => Get.toNamed(Routes.recruitmentPublish),
                  ),
                  const SizedBox(height: 12),
                ],
              );
            }
            return const SizedBox.shrink();
          }),

          // Aide et support
          SettingsMenuItem(
            icon: Icons.help_outline_outlined,
            iconBg: const Color(0xFFFFF3EB),
            iconColor: const Color(0xFFD97706),
            title: 'Aide et support',
            subtitle: 'FAQs et support client d\'assistance',
            onTap: () => Get.toNamed(Routes.support),
          ),
          const SizedBox(height: 12),

          // Déconnexion
          SettingsMenuItem(
            icon: Icons.logout,
            iconBg: const Color(0xFFFEE2E2),
            iconColor: const Color(0xFFDC2626),
            title: 'Déconnexion',
            subtitle: 'Se déconnecter de votre compte',
            titleColor: const Color(0xFFDC2626),
            onTap: () => confirmLogout(context, controller),
          ),
          const SizedBox(height: 12),

          // Supprimer mon compte
          SettingsMenuItem(
            icon: Icons.person_remove_outlined,
            iconBg: const Color(0xFFFEE2E2),
            iconColor: const Color(0xFFDC2626),
            title: 'Supprimer mon compte',
            subtitle: 'Supprimer définitivement vos données',
            titleColor: const Color(0xFFDC2626),
            onTap: () => confirmDeleteAccount(context, controller),
          ),
        ],
      ),
    );
  }
}

class SettingsMenuItem extends StatelessWidget {
  final IconData icon;
  final Color iconBg;
  final Color iconColor;
  final String title;
  final String subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color? titleColor;

  const SettingsMenuItem({
    super.key,
    required this.icon,
    required this.iconBg,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    this.trailing,
    this.onTap,
    this.titleColor,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: SettingsColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: SettingsColors.subtle),
          boxShadow: [
            BoxShadow(
              color: SettingsColors.ink.withValues(alpha: 0.04),
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
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: titleColor ?? SettingsColors.ink,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: SettingsColors.muted,
                    ),
                  ),
                ],
              ),
            ),
            trailing ??
                const Icon(
                  Icons.chevron_right,
                  color: SettingsColors.muted,
                  size: 20,
                ),
          ],
        ),
      ),
    );
  }
}
