import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/constants/spacing.dart';

/// Animated role selector tab — Client | Artisan | Fournisseur
///
/// Stateless: the parent drives selection via [selectedRole] + [onRoleChanged].
class RoleSelectorWidget extends StatelessWidget {
  final String selectedRole;
  final ValueChanged<String> onRoleChanged;

  const RoleSelectorWidget({
    super.key,
    required this.selectedRole,
    required this.onRoleChanged,
  });

  static const _roles = [
    _RoleOption(
      role: 'client',
      label: 'Client',
      icon: Icons.person_outline,
      activeColor: AppColors.lightAccentPrimary,
    ),
    _RoleOption(
      role: 'artisan',
      label: 'Artisan',
      icon: Icons.build_outlined,
      activeColor: AppColors.lightAccentSecondary,
    ),
    _RoleOption(
      role: 'fournisseur',
      label: 'Fournisseur',
      icon: Icons.store_outlined,
      activeColor: AppColors.lightAccentHighlight,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 52,
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusLg),
        border: Border.all(
          color: AppColors.lightTextTertiary.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        children: _roles.map((option) {
          final isActive = selectedRole == option.role;
          return Expanded(
            child: GestureDetector(
              onTap: () => onRoleChanged(option.role),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                curve: Curves.easeInOut,
                margin: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: isActive ? option.activeColor : Colors.transparent,
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  boxShadow: isActive
                      ? [
                          BoxShadow(
                            color: option.activeColor.withValues(alpha: 0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      option.icon,
                      size: 16,
                      color: isActive
                          ? Colors.white
                          : AppColors.lightTextSecondary,
                    ),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        option.label,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: isActive
                              ? FontWeight.w600
                              : FontWeight.w400,
                          color: isActive
                              ? Colors.white
                              : AppColors.lightTextSecondary,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _RoleOption {
  final String role;
  final String label;
  final IconData icon;
  final Color activeColor;

  const _RoleOption({
    required this.role,
    required this.label,
    required this.icon,
    required this.activeColor,
  });
}
