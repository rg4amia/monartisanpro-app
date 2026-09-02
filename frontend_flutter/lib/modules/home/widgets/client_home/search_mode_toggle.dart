import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Bascule « Ma zone (local) » / « Artisans éloignés » de la liste d'artisans,
/// pilotée par `HomeController.searchDistant`.
class SearchModeToggle extends StatelessWidget {
  const SearchModeToggle({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          children: [
            Expanded(
              child: _ToggleOption(
                icon: Icons.my_location,
                label: 'Ma zone (local)',
                selected: !controller.searchDistant.value,
                onTap: () {
                  if (controller.searchDistant.value) {
                    controller.toggleSearchDistant();
                  }
                },
              ),
            ),
            Expanded(
              child: _ToggleOption(
                icon: Icons.public,
                label: 'Artisans éloignés',
                selected: controller.searchDistant.value,
                onTap: () {
                  if (!controller.searchDistant.value) {
                    controller.toggleSearchDistant();
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ToggleOption extends StatelessWidget {
  const _ToggleOption({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? Colors.white : AppColors.textSecondary;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.client : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: fg),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: fg,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
