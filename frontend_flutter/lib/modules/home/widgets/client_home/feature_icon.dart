import 'package:flutter/material.dart';

/// Vignette d'icône arrondie (par défaut blanche translucide sur fond dégradé).
class FeatureIcon extends StatelessWidget {
  const FeatureIcon({
    super.key,
    required this.icon,
    this.color = Colors.white,
    this.background = const Color(0x33FFFFFF),
  });

  final IconData icon;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, color: color),
    );
  }
}
