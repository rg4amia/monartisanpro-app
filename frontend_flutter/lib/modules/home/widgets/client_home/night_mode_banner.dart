import 'package:flutter/material.dart';

/// Bandeau « Mode Nuit Actif (18h - 7h) » affiché quand seules les interventions
/// d'urgence de nuit sont proposées.
class NightModeBanner extends StatelessWidget {
  const NightModeBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1E1E2F), Color(0xFF0F0C1B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.indigo.withValues(alpha: 0.4)),
        boxShadow: [
          BoxShadow(
            color: Colors.indigo.withValues(alpha: 0.15),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.indigo.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.dark_mode_rounded,
              color: Colors.indigoAccent,
              size: 24,
            ),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Mode Nuit Actif (18h - 7h)',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Seuls les artisans équipés pour les interventions de nuit sont affichés.',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.white70,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
