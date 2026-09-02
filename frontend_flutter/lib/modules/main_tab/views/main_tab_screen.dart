import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/services/app_settings_service.dart';
import '../../../shared/widgets/maintenance_overlay.dart';
import '../../../shared/widgets/offline_banner.dart';
import '../../home/views/artisan_home_screen.dart';
import '../../home/views/artisan_map_screen.dart';
import '../../home/views/client_home_screen.dart';
import '../../home/views/driver_home_screen.dart';
import '../../home/views/supplier_home_screen.dart';
import '../../ia/views/ia_assistant_screen.dart';
import '../../jcode/views/jcode_screen.dart';
import '../../jcode/views/scanner_screen.dart';
import '../../jcode/views/supplier_catalog_screen.dart';
import '../../missions/views/missions_screen.dart';
import '../../orders/views/client_suppliers_list_screen.dart';
import '../../settings/views/settings_screen.dart';
import '../../wallet/views/wallet_screen.dart';
import '../controllers/main_tab_controller.dart';

class MainTabScreen extends StatelessWidget {
  const MainTabScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MainTabController>();
    final appSettings = Get.find<AppSettingsService>();

    return Obx(() {
      final role = c.role.value ?? 'client';

      // Check if space is blocked
      if (appSettings.isBlocked(role, isNewUser: false)) {
        return MaintenanceOverlay(
          role: role,
          onRefresh: () => appSettings.fetchSettings(),
        );
      }

      final isFournisseur = role == 'fournisseur';
      final isArtisan = role == 'artisan';
      final isDriver =
          role == 'driver' || role == 'livreur' || role == 'LIVREUR';

      final tabs = isFournisseur
          ? _fournisseurTabs()
          : isArtisan
              ? _artisanTabs()
              : isDriver
                  ? _driverTabs()
                  : _clientTabs();

      final Color spaceThemeColor = isFournisseur
          ? AppColors.success
          : isArtisan
              ? AppColors.accent
              : isDriver
                  ? AppColors.driver
                  : AppColors.client;

      // Safe index bound protection if role switches dynamically
      final safeIndex = c.currentIndex.value >= tabs.screens.length
          ? 0
          : c.currentIndex.value;

      return Scaffold(
        body: Column(
          children: [
            const OfflineBanner(),
            Expanded(
              child: IndexedStack(
                index: safeIndex,
                children: tabs.screens,
              ),
            ),
          ],
        ),
        bottomNavigationBar: _ModernBottomNav(
          currentIndex: safeIndex,
          onTap: c.changeTab,
          items: tabs.items,
          activeColor: spaceThemeColor,
        ),
      );
    });
  }

  // ── 1. ESPACE CLIENT ────────────────────────────────────────────────────────
  _TabConfig _clientTabs() => _TabConfig(
        screens: const [
          ClientHomeScreen(),
          ArtisanMapScreen(),
          ClientSuppliersListScreen(),
          MissionsScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.home_outlined,
            activeIcon: Icons.home_rounded,
            label: 'Accueil',
          ),
          _NavItem(
            icon: Icons.map_outlined,
            activeIcon: Icons.map_rounded,
            label: 'Artisans',
          ),
          _NavItem(
            icon: Icons.storefront_outlined,
            activeIcon: Icons.storefront_rounded,
            label: 'Boutiques',
          ),
          _NavItem(
            icon: Icons.assignment_outlined,
            activeIcon: Icons.assignment_rounded,
            label: 'Missions',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person_rounded,
            label: 'Profil',
          ),
        ],
      );

  // ── 2. ESPACE ARTISAN ───────────────────────────────────────────────────────
  _TabConfig _artisanTabs() => _TabConfig(
        screens: const [
          ArtisanHomeScreen(),
          MissionsScreen(),
          JcodeScreen(),
          IaAssistantScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.home_outlined,
            activeIcon: Icons.home_rounded,
            label: 'Accueil',
          ),
          _NavItem(
            icon: Icons.work_outline_rounded,
            activeIcon: Icons.work_rounded,
            label: 'Devis & Projets',
          ),
          _NavItem(
            icon: Icons.qr_code_2_outlined,
            activeIcon: Icons.qr_code_2_rounded,
            label: 'J-Codes',
          ),
          _NavItem(
            icon: Icons.smart_toy_outlined,
            activeIcon: Icons.smart_toy_rounded,
            label: 'Assistant IA',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person_rounded,
            label: 'Profil',
          ),
        ],
      );

  // ── 3. ESPACE FOURNISSEUR ──────────────────────────────────────────────────
  _TabConfig _fournisseurTabs() => _TabConfig(
        screens: const [
          SupplierHomeScreen(),
          ScannerScreen(),
          SupplierCatalogScreen(),
          MissionsScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.storefront_outlined,
            activeIcon: Icons.storefront_rounded,
            label: 'Boutique',
          ),
          _NavItem(
            icon: Icons.qr_code_scanner_outlined,
            activeIcon: Icons.qr_code_scanner_rounded,
            label: 'Scan J-Code',
          ),
          _NavItem(
            icon: Icons.inventory_2_outlined,
            activeIcon: Icons.inventory_2_rounded,
            label: 'Catalogue',
          ),
          _NavItem(
            icon: Icons.receipt_long_outlined,
            activeIcon: Icons.receipt_long_rounded,
            label: 'Commandes',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person_rounded,
            label: 'Profil',
          ),
        ],
      );

  // ── 4. ESPACE LIVREUR ───────────────────────────────────────────────────────
  _TabConfig _driverTabs() => _TabConfig(
        screens: const [
          DriverHomeScreen(),
          MissionsScreen(),
          ScannerScreen(),
          WalletScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.two_wheeler_outlined,
            activeIcon: Icons.two_wheeler_rounded,
            label: 'Tableau Bord',
          ),
          _NavItem(
            icon: Icons.local_shipping_outlined,
            activeIcon: Icons.local_shipping_rounded,
            label: 'Livraisons',
          ),
          _NavItem(
            icon: Icons.qr_code_scanner_outlined,
            activeIcon: Icons.qr_code_scanner_rounded,
            label: 'Code Retrait',
          ),
          _NavItem(
            icon: Icons.account_balance_wallet_outlined,
            activeIcon: Icons.account_balance_wallet_rounded,
            label: 'Gains',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person_rounded,
            label: 'Profil',
          ),
        ],
      );
}

// ─── Modern Bottom Navigation with Space Color Theme ───────────────────────────
class _ModernBottomNav extends StatelessWidget {
  final int currentIndex;
  final Function(int) onTap;
  final List<_NavItem> items;
  final Color activeColor;

  const _ModernBottomNav({
    required this.currentIndex,
    required this.onTap,
    required this.items,
    required this.activeColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 20,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: List.generate(
              items.length,
              (i) => Expanded(
                child: _NavButton(
                  item: items[i],
                  isActive: currentIndex == i,
                  activeColor: activeColor,
                  onTap: () => onTap(i),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  final _NavItem item;
  final bool isActive;
  final Color activeColor;
  final VoidCallback onTap;

  const _NavButton({
    required this.item,
    required this.isActive,
    required this.activeColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    const inactive = Color(0xFF9CA3AF);

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
        decoration: BoxDecoration(
          color: isActive
              ? activeColor.withValues(alpha: 0.12)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isActive ? item.activeIcon : item.icon,
              color: isActive ? activeColor : inactive,
              size: 22,
            ),
            const SizedBox(height: 4),
            Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 10,
                fontWeight: isActive ? FontWeight.w700 : FontWeight.w500,
                color: isActive ? activeColor : inactive,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NavItem {
  final IconData icon;
  final IconData activeIcon;
  final String label;

  const _NavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
  });
}

class _TabConfig {
  final List<Widget> screens;
  final List<_NavItem> items;

  const _TabConfig({required this.screens, required this.items});
}
