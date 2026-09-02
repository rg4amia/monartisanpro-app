import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../controllers/devis_controller.dart';

/// Carte de l'assistant IA : déclenche `fetchAiSuggestion` pour
/// pré-remplir main d'œuvre, matériaux et jalons.
class AiAssistantCard extends StatelessWidget {
  const AiAssistantCard({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final isLoading = controller.isAiLoading.value;

      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [
              Color(0xFFEFF6FF),
              Color(0xFFF5F3FF),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFC7D2FE)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                ShaderMask(
                  shaderCallback: (bounds) => const LinearGradient(
                    colors: [Color(0xFF3B82F6), Color(0xFF8B5CF6)],
                  ).createShader(bounds),
                  child: const Icon(
                    Icons.auto_awesome,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 10),
                const Text(
                  'Assistant Devis IA',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF1E1B4B),
                  ),
                ),
                const Spacer(),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEEF2F6),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    'Recommandé',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF4F46E5),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            const Text(
              'L\'assistant IA analyse la description de la mission et suggère automatiquement une répartition optimale de la main d\'œuvre, des matériaux et des jalons de paiement adaptés.',
              style: TextStyle(
                fontSize: 13,
                color: Color(0xFF4B5563),
                height: 1.4,
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed:
                    isLoading ? null : () => controller.fetchAiSuggestion(),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4F46E5),
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: const Color(0xFFEEF2F6),
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: isLoading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor:
                              AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : const Icon(Icons.bolt, size: 18),
                label: Text(
                  isLoading
                      ? 'Génération en cours...'
                      : 'Pré-remplir le devis avec l\'IA',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
      );
    });
  }
}
