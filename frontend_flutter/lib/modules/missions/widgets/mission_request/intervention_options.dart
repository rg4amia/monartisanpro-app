import 'package:flutter/material.dart';

import '../../../services/models/intervention_type_model.dart';
import 'mission_request_colors.dart';

class InterventionTypeSelector extends StatelessWidget {
  final List<InterventionTypeModel> types;
  final bool isLoading;
  final int? selectedId;
  final ValueChanged<int> onSelected;

  const InterventionTypeSelector({
    super.key,
    required this.types,
    required this.isLoading,
    required this.selectedId,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: SizedBox(
          height: 20,
          width: 20,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      );
    }

    if (types.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Que souhaitez-vous demander à l\'artisan ?',
          style: TextStyle(fontSize: 13, color: MissionRequestColors.muted),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: types.map((type) {
            final selected = type.id == selectedId;
            return GestureDetector(
              onTap: () => onSelected(type.id),
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: selected
                      ? MissionRequestColors.primary
                      : MissionRequestColors.surface,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: selected
                        ? MissionRequestColors.primary
                        : MissionRequestColors.subtle,
                  ),
                ),
                child: Text(
                  type.name,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: selected ? Colors.white : MissionRequestColors.ink,
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }
}

class NightInterventionCard extends StatelessWidget {
  final bool enabled;
  final ValueChanged<bool> onChanged;

  const NightInterventionCard({
    super.key,
    required this.enabled,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MissionRequestColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: MissionRequestColors.subtle),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: MissionRequestColors.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.nightlight_round,
              color: MissionRequestColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'Intervention de nuit',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: MissionRequestColors.ink,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Affiche seulement les artisans qui acceptent les demandes entre 18h et 7h.',
                  style: TextStyle(
                    fontSize: 13,
                    color: MissionRequestColors.muted,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: enabled,
            activeThumbColor: MissionRequestColors.primary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}
