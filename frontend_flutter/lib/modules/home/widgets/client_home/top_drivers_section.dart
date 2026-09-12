import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';

/// Liste des livreurs les mieux notés (données de `HomeController`).
class TopDriversSection extends StatelessWidget {
  const TopDriversSection({super.key, required this.controller});

  final HomeController controller;

  /// Lecture sûre d'un champ texte du payload.
  ///
  /// Ces cartes sont alimentées par du JSON : transtyper directement en
  /// `String` fait lever « type 'Null' is not a subtype of type 'String' »
  /// dès qu'une clé manque, et Flutter remplace alors toute la section par
  /// une zone grise. C'est ce qui arrivait avec `vehicle`, que le backend ne
  /// produisait pas.
  static String _text(Map<String, dynamic> source, String key) =>
      source[key]?.toString().trim() ?? '';

  @override
  Widget build(BuildContext context) {
    return Column(
      children: controller.topDrivers.map((driver) {
        final vehicle = _text(driver, 'vehicle');
        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.accent.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.local_shipping_rounded,
                  color: AppColors.accent,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(driver, 'name'),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14.5,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    // Ligne omise plutôt que vide quand le véhicule est inconnu.
                    if (vehicle.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        vehicle,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,
                          color: Colors.amber,
                          size: 16,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${driver['rating']}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '•  ${driver['trips']} livraisons',
                          style: const TextStyle(
                            color: AppColors.textMuted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}
