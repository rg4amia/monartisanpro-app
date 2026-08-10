import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../core/theme/app_colors.dart';
import '../../data/services/app_settings_service.dart';

class MaintenanceOverlay extends StatelessWidget {
  final String role;
  final VoidCallback onRefresh;

  const MaintenanceOverlay({
    super.key,
    required this.role,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final appSettings = Get.find<AppSettingsService>();
    final themeColor = role.toLowerCase() == 'client'
        ? AppColors.client
        : role.toLowerCase() == 'artisan'
            ? AppColors.accent
            : role.toLowerCase() == 'fournisseur'
                ? AppColors.success
                : AppColors.driver;

    final title = role.toLowerCase() == 'client'
        ? 'Espace Client en Maintenance'
        : role.toLowerCase() == 'artisan'
            ? 'Espace Artisan en Maintenance'
            : role.toLowerCase() == 'fournisseur'
                ? 'Espace Fournisseur en Maintenance'
                : 'Espace Livreur en Maintenance';

    final message = appSettings.getDisabledMessage(role);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 40.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: themeColor.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.construction_rounded,
                  size: 64,
                  color: themeColor,
                ),
              ),
              const SizedBox(height: 32),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontFamily: 'Outfit',
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.shadowColor.withOpacity(0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Text(
                  message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 14,
                    color: AppColors.textSecondary,
                    height: 1.5,
                  ),
                ),
              ),
              const SizedBox(height: 40),
              ElevatedButton.icon(
                onPressed: onRefresh,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Vérifier à nouveau'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: themeColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  elevation: 0,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
