import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';
import '../../../core/theme/app_shadows.dart';

/// Modèle pour un élément de navigation
class BottomNavItem {
  final String id;
  final String label;
  final IconData icon;
  final IconData activeIcon;
  final int? badgeCount;

  const BottomNavItem({
    required this.id,
    required this.label,
    required this.icon,
    required this.activeIcon,
    this.badgeCount,
  });
}

/// Barre de navigation inférieure personnalisée
/// Suit le design system avec 5 éléments: Home, Bookings, Categories, Chat, Profile
class CustomBottomNavBar extends StatelessWidget {
  final List<BottomNavItem> items;
  final String currentRoute;
  final Function(String) onItemTapped;
  final bool floating;

  const CustomBottomNavBar({
    super.key,
    required this.items,
    required this.currentRoute,
    required this.onItemTapped,
    this.floating = false,
  });

  @override
  Widget build(BuildContext context) {
    if (floating) {
      return _buildFloatingNavBar();
    }
    return _buildStandardNavBar();
  }

  Widget _buildStandardNavBar() {
    return Container(
      height: AppSpacing.bottomNavHeight,
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.bottomNavRadius,
        boxShadow: AppShadows.bottomNavigation,
      ),
      child: SafeArea(
        child: Row(
          children: items.map((item) {
            final isActive = currentRoute == item.id;
            return Expanded(child: _buildNavItem(item, isActive));
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildFloatingNavBar() {
    return Positioned(
      bottom: AppSpacing.base,
      left: AppSpacing.base,
      right: AppSpacing.base,
      child: Container(
        height: 64,
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: AppRadius.circular(32),
          boxShadow: AppShadows.floatingButton,
        ),
        child: Row(
          children: items.map((item) {
            final isActive = currentRoute == item.id;
            return Expanded(child: _buildNavItem(item, isActive));
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildNavItem(BottomNavItem item, bool isActive) {
    return GestureDetector(
      onTap: () => onItemTapped(item.id),
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: AppSpacing.symmetric(vertical: AppSpacing.sm),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Icône avec badge
            Stack(
              clipBehavior: Clip.none,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: isActive
                        ? AppColors.accentPrimary.withOpacity(0.1)
                        : Colors.transparent,
                    borderRadius: AppRadius.circular(12),
                  ),
                  child: Icon(
                    isActive ? item.activeIcon : item.icon,
                    size: AppSpacing.iconSize,
                    color: isActive
                        ? AppColors.accentPrimary
                        : AppColors.textMuted,
                  ),
                ),

                // Badge de notification
                if (item.badgeCount != null && item.badgeCount! > 0)
                  Positioned(
                    right: 0,
                    top: 0,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: AppColors.accentDanger,
                        borderRadius: AppRadius.circular(10),
                      ),
                      constraints: const BoxConstraints(
                        minWidth: 20,
                        minHeight: 20,
                      ),
                      child: Text(
                        item.badgeCount! > 99
                            ? '99+'
                            : item.badgeCount.toString(),
                        style: AppTypography.tiny.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),

            const SizedBox(height: AppSpacing.xs),

            // Label
            AnimatedDefaultTextStyle(
              duration: const Duration(milliseconds: 200),
              style: AppTypography.navLabel.copyWith(
                color: isActive ? AppColors.accentPrimary : AppColors.textMuted,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
              ),
              child: Text(item.label),
            ),
          ],
        ),
      ),
    );
  }
}

/// Barre de navigation avec indicateur animé
class AnimatedBottomNavBar extends StatelessWidget {
  final List<BottomNavItem> items;
  final String currentRoute;
  final Function(String) onItemTapped;

  const AnimatedBottomNavBar({
    super.key,
    required this.items,
    required this.currentRoute,
    required this.onItemTapped,
  });

  @override
  Widget build(BuildContext context) {
    final currentIndex = items.indexWhere((item) => item.id == currentRoute);

    return Container(
      height: AppSpacing.bottomNavHeight,
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.bottomNavRadius,
        boxShadow: AppShadows.bottomNavigation,
      ),
      child: SafeArea(
        child: Stack(
          children: [
            // Indicateur animé
            AnimatedPositioned(
              duration: const Duration(milliseconds: 300),
              curve: Curves.easeInOut,
              left:
                  (MediaQuery.of(context).size.width / items.length) *
                  currentIndex,
              top: 8,
              child: Container(
                width: MediaQuery.of(context).size.width / items.length,
                height: 4,
                alignment: Alignment.center,
                child: Container(
                  width: 32,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.accentPrimary,
                    borderRadius: AppRadius.circular(2),
                  ),
                ),
              ),
            ),

            // Items de navigation
            Row(
              children: items.asMap().entries.map((entry) {
                final index = entry.key;
                final item = entry.value;
                final isActive = currentRoute == item.id;

                return Expanded(
                  child: GestureDetector(
                    onTap: () => onItemTapped(item.id),
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      padding: AppSpacing.symmetric(vertical: AppSpacing.md),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          // Icône avec badge
                          Stack(
                            clipBehavior: Clip.none,
                            children: [
                              Icon(
                                isActive ? item.activeIcon : item.icon,
                                size: AppSpacing.iconSize,
                                color: isActive
                                    ? AppColors.accentPrimary
                                    : AppColors.textMuted,
                              ),

                              // Badge
                              if (item.badgeCount != null &&
                                  item.badgeCount! > 0)
                                Positioned(
                                  right: -6,
                                  top: -6,
                                  child: Container(
                                    padding: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      color: AppColors.accentDanger,
                                      borderRadius: AppRadius.circular(10),
                                    ),
                                    constraints: const BoxConstraints(
                                      minWidth: 18,
                                      minHeight: 18,
                                    ),
                                    child: Text(
                                      item.badgeCount! > 99
                                          ? '99+'
                                          : item.badgeCount.toString(),
                                      style: AppTypography.tiny.copyWith(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold,
                                      ),
                                      textAlign: TextAlign.center,
                                    ),
                                  ),
                                ),
                            ],
                          ),

                          const SizedBox(height: AppSpacing.xs),

                          // Label
                          Text(
                            item.label,
                            style: AppTypography.navLabel.copyWith(
                              color: isActive
                                  ? AppColors.accentPrimary
                                  : AppColors.textMuted,
                              fontWeight: isActive
                                  ? FontWeight.w600
                                  : FontWeight.normal,
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
      ),
    );
  }
}

/// Barre de navigation avec effet de vague
class WaveBottomNavBar extends StatelessWidget {
  final List<BottomNavItem> items;
  final String currentRoute;
  final Function(String) onItemTapped;

  const WaveBottomNavBar({
    super.key,
    required this.items,
    required this.currentRoute,
    required this.onItemTapped,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: AppSpacing.bottomNavHeight + 20,
      child: Stack(
        children: [
          // Fond avec vague
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              height: AppSpacing.bottomNavHeight,
              decoration: BoxDecoration(
                color: AppColors.cardBg,
                borderRadius: AppRadius.bottomNavRadius,
                boxShadow: AppShadows.bottomNavigation,
              ),
            ),
          ),

          // Items de navigation
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Row(
                children: items.map((item) {
                  final isActive = currentRoute == item.id;
                  return Expanded(
                    child: GestureDetector(
                      onTap: () => onItemTapped(item.id),
                      behavior: HitTestBehavior.opaque,
                      child: Container(
                        padding: AppSpacing.symmetric(vertical: AppSpacing.md),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Icône avec animation
                            AnimatedContainer(
                              duration: const Duration(milliseconds: 200),
                              transform: Matrix4.identity()
                                ..translate(0.0, isActive ? -10.0 : 0.0),
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: isActive
                                      ? AppColors.accentPrimary
                                      : Colors.transparent,
                                  borderRadius: AppRadius.circular(16),
                                  boxShadow: isActive ? AppShadows.card : null,
                                ),
                                child: Stack(
                                  clipBehavior: Clip.none,
                                  children: [
                                    Icon(
                                      isActive ? item.activeIcon : item.icon,
                                      size: AppSpacing.iconSize,
                                      color: isActive
                                          ? Colors.white
                                          : AppColors.textMuted,
                                    ),

                                    // Badge
                                    if (item.badgeCount != null &&
                                        item.badgeCount! > 0)
                                      Positioned(
                                        right: -6,
                                        top: -6,
                                        child: Container(
                                          padding: const EdgeInsets.all(4),
                                          decoration: BoxDecoration(
                                            color: AppColors.accentDanger,
                                            borderRadius: AppRadius.circular(
                                              10,
                                            ),
                                          ),
                                          constraints: const BoxConstraints(
                                            minWidth: 18,
                                            minHeight: 18,
                                          ),
                                          child: Text(
                                            item.badgeCount! > 99
                                                ? '99+'
                                                : item.badgeCount.toString(),
                                            style: AppTypography.tiny.copyWith(
                                              color: Colors.white,
                                              fontWeight: FontWeight.bold,
                                            ),
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),

                            const SizedBox(height: AppSpacing.xs),

                            // Label
                            AnimatedOpacity(
                              duration: const Duration(milliseconds: 200),
                              opacity: isActive ? 0.0 : 1.0,
                              child: Text(
                                item.label,
                                style: AppTypography.navLabel.copyWith(
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Items de navigation par défaut pour ProSartisan
class DefaultBottomNavItems {
  static List<BottomNavItem> get items => [
    const BottomNavItem(
      id: 'home',
      label: 'Accueil',
      icon: Icons.home_outlined,
      activeIcon: Icons.home,
    ),
    const BottomNavItem(
      id: 'bookings',
      label: 'Réservations',
      icon: Icons.calendar_today_outlined,
      activeIcon: Icons.calendar_today,
    ),
    const BottomNavItem(
      id: 'categories',
      label: 'Catégories',
      icon: Icons.grid_view_outlined,
      activeIcon: Icons.grid_view,
    ),
    const BottomNavItem(
      id: 'chat',
      label: 'Messages',
      icon: Icons.chat_bubble_outline,
      activeIcon: Icons.chat_bubble,
    ),
    const BottomNavItem(
      id: 'profile',
      label: 'Profil',
      icon: Icons.person_outline,
      activeIcon: Icons.person,
    ),
  ];
}
