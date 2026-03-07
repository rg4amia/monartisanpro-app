import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/utils/formatters.dart';
import '../../../app/routes/app_routes.dart';
import '../controllers/settings_controller.dart';

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
              'Profile',
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
    return Padding(
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
                ? 'User Profile'
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
                ? 'No email'
                : '${controller.userPhone.value}@email.com',
            style: const TextStyle(
              fontSize: 14,
              color: _C.muted,
            ),
          ),
        ],
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
                  label: 'Balance',
                  value:
                      'FCFA ${_formatBalance(controller.walletBalance.value)}',
                  color: _C.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _StatCard(
                  label: 'Orders',
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
            title: 'Personal Information',
            subtitle: 'Manage your identity details',
            onTap: () {},
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.credit_card_outlined,
            iconBg: _C.primaryLight,
            iconColor: _C.primary,
            title: 'Payment Methods',
            subtitle: 'Cards, Mobile Money, Wallets',
            onTap: () {},
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.shield_outlined,
            iconBg: _C.successLight,
            iconColor: _C.success,
            title: 'Security & KYC Status',
            subtitle: 'Secure your account and limits',
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
            title: 'Help Center',
            subtitle: 'FAQs and customer support',
            onTap: () {},
          ),
          const SizedBox(height: 12),
          _MenuItem(
            icon: Icons.logout,
            iconBg: _C.dangerLight,
            iconColor: _C.danger,
            title: 'Logout',
            subtitle: 'Sign out of your account',
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
        title: const Text('Logout'),
        content: const Text('Are you sure you want to sign out?'),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Cancel'),
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
            child: const Text('Logout'),
          ),
        ],
      ),
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
            'VERIFIED',
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
