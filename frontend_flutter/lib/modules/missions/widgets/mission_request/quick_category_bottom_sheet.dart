import 'package:flutter/material.dart';

import '../../../services/utils/service_icon_helper.dart';
import 'mission_request_colors.dart';

class QuickCategoryBottomSheet extends StatelessWidget {
  final ValueChanged<String> onSelected;
  final VoidCallback onOpenFullServices;

  const QuickCategoryBottomSheet({
    super.key,
    required this.onSelected,
    required this.onOpenFullServices,
  });

  static const List<String> popularCategories = [
    'Plomberie',
    'Électricité',
    'Maçonnerie',
    'Menuiserie',
    'Peinture & Revêtements',
    'Climatisation & Froid',
    'Serrurerie',
    'Mécanique Auto & Moto',
    'Soudure & Métallerie',
    'Sécurité & Domotique',
    'Nettoyage & Entretien',
    'Jardinage & Espaces verts',
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 12),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text(
                        'Sélectionner une catégorie',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: MissionRequestColors.ink,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Choisissez le domaine correspondant à vos travaux',
                        style: TextStyle(
                          fontSize: 13,
                          color: MissionRequestColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.close,
                      size: 18,
                      color: MissionRequestColors.muted,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 2.2,
              ),
              itemCount: popularCategories.length,
              itemBuilder: (context, index) {
                final category = popularCategories[index];
                final color = ServiceIconHelper.getSectorColor(category);
                final icon = ServiceIconHelper.getSectorIcon(category);

                return InkWell(
                  onTap: () => onSelected(category),
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: color.withValues(alpha: 0.25)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 38,
                          height: 38,
                          decoration: BoxDecoration(
                            color: color,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(icon, color: Colors.white, size: 20),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            category,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: color,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            child: SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onOpenFullServices,
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  side: const BorderSide(color: MissionRequestColors.primary),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: const Icon(
                  Icons.explore_outlined,
                  color: MissionRequestColors.primary,
                  size: 20,
                ),
                label: const Text(
                  'Explorer tous les métiers & spécialités',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: MissionRequestColors.primary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
