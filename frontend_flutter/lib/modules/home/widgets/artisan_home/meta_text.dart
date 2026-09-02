import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Petit libellé icône + texte tronqué (client, adresse…) d'une carte mission.
class MetaText extends StatelessWidget {
  const MetaText({
    super.key,
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 4),
        SizedBox(
          width: 130,
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
        ),
      ],
    );
  }
}
