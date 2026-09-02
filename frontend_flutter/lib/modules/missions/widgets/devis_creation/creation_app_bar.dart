import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';

/// Barre de titre de l'écran de création de devis.
class CreationAppBar extends StatelessWidget {
  const CreationAppBar({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.border),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Créer un devis',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
          ),
          const SizedBox(width: 40),
        ],
      ),
    );
  }
}
