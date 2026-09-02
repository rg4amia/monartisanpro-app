import 'package:flutter/material.dart';

/// Action contextuelle résolue pour une mission de la file de traitement
/// (libellé du bouton, sous-titre explicatif, couleur, callback).
class MissionAction {
  const MissionAction({
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback? onTap;
}
