import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../home/views/artisan_home_screen.dart';
import '../../home/views/artisan_map_screen.dart';
import '../../home/views/client_home_screen.dart';
import '../../home/views/supplier_home_screen.dart';
import '../../jcode/views/jcode_screen.dart';
import '../../missions/views/missions_screen.dart';
import '../../settings/views/settings_screen.dart';
import '../controllers/main_tab_controller.dart';

class MainTabScreen extends StatelessWidget {
  const MainTabScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MainTabController>();

    return Obx(() {
      final role = c.role.value ?? 'client';
      final isFournisseur = role == 'fournisseur';
      final isArtisan = role == 'artisan';

      final tabs = isFournisseur
          ? _fournisseurTabs()
          : isArtisan
              ? _artisanTabs()
              : _clientTabs();

      return Scaffold(
        body: IndexedStack(
          index: c.currentIndex.value,
          children: tabs.screens,
        ),
        bottomNavigationBar: _ModernBottomNav(
          currentIndex: c.currentIndex.value,
          onTap: c.changeTab,
          items: tabs.items,
        ),
      );
    });
  }

  _TabConfig _clientTabs() => _TabConfig(
        screens: [
          const ClientHomeScreen(),
          const ArtisanMapScreen(),
          const MissionsScreen(),
          const SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.home_outlined,
            activeIcon: Icons.home,
            label: 'Accueil',
          ),
          _NavItem(
            icon: Icons.map_outlined,
            activeIcon: Icons.map,
            label: 'Carte',
          ),
          _NavItem(
            icon: Icons.calendar_today_outlined,
            activeIcon: Icons.calendar_today,
            label: 'Missions',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person,
            label: 'Profil',
          ),
        ],
      );

  _TabConfig _artisanTabs() => _TabConfig(
        screens: const [
          ArtisanHomeScreen(),
          MissionsScreen(),
          JcodeScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.home_outlined,
            activeIcon: Icons.home,
            label: 'Accueil',
          ),
          _NavItem(
            icon: Icons.work_outline,
            activeIcon: Icons.work,
            label: 'Missions',
          ),
          _NavItem(
            icon: Icons.qr_code_2_outlined,
            activeIcon: Icons.qr_code_2,
            label: 'J-Code',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person,
            label: 'Profil',
          ),
        ],
      );

  _TabConfig _fournisseurTabs() => _TabConfig(
        screens: const [
          SupplierHomeScreen(),
          MissionsScreen(),
          SettingsScreen(),
        ],
        items: const [
          _NavItem(
            icon: Icons.home_outlined,
            activeIcon: Icons.home_rounded,
            label: 'ACCUEIL',
          ),
          _NavItem(
            icon: Icons.access_time_outlined,
            activeIcon: Icons.access_time_rounded,
            label: 'TRANSACTIONS',
          ),
          _NavItem(
            icon: Icons.person_outline_rounded,
            activeIcon: Icons.person_rounded,
            label: 'PROFIL',
          ),
        ],
      );
}

// ─── Modern Bottom Navigation ─────────────────────────────────────────────────
class _ModernBottomNav extends StatelessWidget {
  final int currentIndex;
  final Function(int) onTap;
  final List<_NavItem> items;

  const _ModernBottomNav({
    required this.currentIndex,
    required this.onTap,
    required this.items,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 20,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: List.generate(
              items.length,
              (i) => _NavButton(
                item: items[i],
                isActive: currentIndex == i,
                onTap: () => onTap(i),
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
  final VoidCallback onTap;

  const _NavButton({
    required this.item,
    required this.isActive,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    const primary = Color(0xFF4F46E5);
    const inactive = Color(0xFF9CA3AF);

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? primary.withValues(alpha: 0.1) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isActive ? item.activeIcon : item.icon,
              color: isActive ? primary : inactive,
              size: 24,
            ),
            const SizedBox(height: 4),
            Text(
              item.label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.w500,
                color: isActive ? primary : inactive,
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
