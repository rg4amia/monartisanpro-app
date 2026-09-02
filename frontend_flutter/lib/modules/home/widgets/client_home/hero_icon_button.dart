import 'package:flutter/material.dart';

/// Bouton icône translucide de l'en-tête client (cloche de notifications…).
class HeroIconButton extends StatelessWidget {
  const HeroIconButton({
    super.key,
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Icon(icon, color: Colors.white),
      ),
    );
  }
}
