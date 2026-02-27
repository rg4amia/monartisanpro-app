import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../home/views/artisan_home_screen.dart';
import '../../home/views/client_home_screen.dart';
import '../../home/views/supplier_home_screen.dart';
import '../../missions/views/missions_screen.dart';
import '../../notifications/views/notifications_screen.dart';
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

      final tabs = isFournisseur
          ? _fournisseurTabs()
          : _defaultTabs(role == 'artisan');

      return Scaffold(
        body: IndexedStack(
          index: c.currentIndex.value,
          children: tabs.screens,
        ),
        bottomNavigationBar: BottomNavigationBar(
          currentIndex: c.currentIndex.value,
          onTap: c.changeTab,
          items: tabs.items,
        ),
      );
    });
  }

  _TabConfig _defaultTabs(bool isArtisan) => _TabConfig(
        screens: [
          isArtisan ? const ArtisanHomeScreen() : const ClientHomeScreen(),
          const MissionsScreen(),
          const NotificationsScreen(),
          const SettingsScreen(),
        ],
        items: const [
          BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home),
              label: 'Accueil'),
          BottomNavigationBarItem(
              icon: Icon(Icons.assignment_outlined),
              activeIcon: Icon(Icons.assignment),
              label: 'Missions'),
          BottomNavigationBarItem(
              icon: Icon(Icons.notifications_outlined),
              activeIcon: Icon(Icons.notifications),
              label: 'Alertes'),
          BottomNavigationBarItem(
              icon: Icon(Icons.person_outline),
              activeIcon: Icon(Icons.person),
              label: 'Profil'),
        ],
      );

  _TabConfig _fournisseurTabs() => _TabConfig(
        screens: const [
          SupplierHomeScreen(),
          MissionsScreen(),
          NotificationsScreen(),
          SettingsScreen(),
        ],
        items: const [
          BottomNavigationBarItem(
              icon: Icon(Icons.qr_code_scanner_outlined),
              activeIcon: Icon(Icons.qr_code_scanner),
              label: 'Scanner'),
          BottomNavigationBarItem(
              icon: Icon(Icons.receipt_long_outlined),
              activeIcon: Icon(Icons.receipt_long),
              label: 'Transactions'),
          BottomNavigationBarItem(
              icon: Icon(Icons.notifications_outlined),
              activeIcon: Icon(Icons.notifications),
              label: 'Alertes'),
          BottomNavigationBarItem(
              icon: Icon(Icons.person_outline),
              activeIcon: Icon(Icons.person),
              label: 'Profil'),
        ],
      );
}

class _TabConfig {
  final List<Widget> screens;
  final List<BottomNavigationBarItem> items;

  const _TabConfig({required this.screens, required this.items});
}
