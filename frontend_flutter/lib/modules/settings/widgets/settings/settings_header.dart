import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/utils/formatters.dart';
import '../../controllers/settings_controller.dart';
import 'settings_colors.dart';

// ─── App Bar ──────────────────────────────────────────────────────────────────
class SettingsAppBar extends StatelessWidget {
  const SettingsAppBar({super.key});

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
                  color: SettingsColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: SettingsColors.subtle),
                ),
                child: const Icon(Icons.arrow_back, size: 20),
              ),
            ),
            const Text(
              'Profil',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: SettingsColors.ink,
              ),
            ),
            GestureDetector(
              onTap: () {},
              child: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: SettingsColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: SettingsColors.subtle),
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
class SettingsProfileHeader extends StatelessWidget {
  final SettingsController controller;
  const SettingsProfileHeader({super.key, required this.controller});

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
                    border: Border.all(color: SettingsColors.subtle, width: 3),
                    color: SettingsColors.primaryLight,
                  ),
                  child: Center(
                    child: Text(
                      Formatters.initial(controller.userName.value),
                      style: const TextStyle(
                        fontSize: 48,
                        fontWeight: FontWeight.w700,
                        color: SettingsColors.primary,
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
                      color: SettingsColors.primary,
                      shape: BoxShape.circle,
                      border:
                          Border.all(color: SettingsColors.surface, width: 3),
                    ),
                    child:
                        const Icon(Icons.edit, color: Colors.white, size: 16),
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
                color: SettingsColors.ink,
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
                color: SettingsColors.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Stats Row ────────────────────────────────────────────────────────────────
class SettingsStatsRow extends StatelessWidget {
  final SettingsController controller;
  const SettingsStatsRow({super.key, required this.controller});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
      child: Obx(
        () => Row(
          children: [
            Expanded(
              child: SettingsStatCard(
                label: 'Solde',
                value: 'FCFA ${_formatBalance(controller.walletBalance.value)}',
                color: SettingsColors.primary,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: SettingsStatCard(
                label: 'Commandes',
                value: '${controller.ordersCount.value}',
                color: SettingsColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatBalance(int balance) {
    if (balance >= 1000) {
      return '${(balance / 1000).toStringAsFixed(1)}k';
    }
    return balance.toString();
  }
}

class SettingsStatCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const SettingsStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
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
              color: SettingsColors.muted,
            ),
          ),
        ],
      ),
    );
  }
}

class SettingsVerifiedBadge extends StatelessWidget {
  final String status;
  const SettingsVerifiedBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    if (status != 'actif') {
      return const Icon(
        Icons.chevron_right,
        color: SettingsColors.muted,
        size: 20,
      );
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: SettingsColors.successLight,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.check_circle,
            size: 14,
            color: SettingsColors.success,
          ),
          const SizedBox(width: 4),
          const Text(
            'VÉRIFIÉ',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: SettingsColors.success,
              letterSpacing: 0.5,
            ),
          ),
        ],
      ),
    );
  }
}
