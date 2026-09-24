import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../data/services/app_settings_service.dart';
import 'login_tokens.dart';

Widget buildLoginProfileCard(
  String label,
  IconData icon,
  String emoji,
  bool isSelected,
  bool isBlocked,
  VoidCallback onTap,
) {
  final appSettings = Get.find<AppSettingsService>();
  final accent = loginRoleColor(label);
  return GestureDetector(
    onTap: isBlocked
        ? () {
            Get.snackbar(
              'Accès désactivé',
              appSettings.getDisabledMessage(label),
              snackPosition: SnackPosition.BOTTOM,
              backgroundColor: Colors.red.withValues(alpha: 0.9),
              colorText: Colors.white,
              margin: const EdgeInsets.all(16),
            );
          }
        : onTap,
    child: AnimatedScale(
      scale: isSelected ? 1.03 : 1.0,
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeOutBack,
      child: Opacity(
        opacity: isBlocked ? 0.4 : 1.0,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOutCubic,
          transform: isSelected
              ? Matrix4.translationValues(0, -5, 0)
              : Matrix4.identity(),
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? accent.withValues(alpha: 0.10)
                : LoginTokens.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isSelected ? accent : LoginTokens.border,
              width: isSelected ? 2.5 : 1.5,
            ),
            boxShadow: isSelected
                ? [
                    BoxShadow(
                      color: accent.withValues(alpha: 0.20),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ]
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.center,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 280),
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      gradient: isSelected
                          ? LinearGradient(
                              colors: [
                                accent,
                                accent.withValues(alpha: 0.82),
                              ],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            )
                          : null,
                      color: isSelected ? null : LoginTokens.bg,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Center(
                      child: Text(
                        emoji,
                        style: const TextStyle(fontSize: 28),
                      ),
                    ),
                  ),
                  if (isSelected)
                    Positioned(
                      top: -6,
                      right: -6,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: accent,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: accent.withValues(alpha: 0.35),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.check_rounded,
                          color: Colors.white,
                          size: 12,
                        ),
                      ),
                    ),
                  if (isBlocked)
                    Positioned(
                      top: -6,
                      right: -6,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: Colors.red.shade400,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.red.withValues(alpha: 0.3),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.lock_outline_rounded,
                          color: Colors.white,
                          size: 14,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w900,
                  color: isSelected ? accent : LoginTokens.ink,
                  letterSpacing: 0.4,
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

Color loginRoleColor(String? label) {
  if (label == null) return LoginTokens.primary;
  switch (label) {
    case 'ARTISAN':
      return LoginTokens.artisan;
    case 'FOURNISSEUR':
      return LoginTokens.fournisseur;
    case 'LIVREUR':
      return LoginTokens.driver;
    case 'CLIENT':
    default:
      return LoginTokens.client;
  }
}
