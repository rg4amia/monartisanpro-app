import 'package:flutter/material.dart';

import 'mission_request_colors.dart';

class GeminiEstimateCard extends StatelessWidget {
  final bool isLoading;
  final Map<String, dynamic>? estimate;
  final VoidCallback onAnalyze;

  const GeminiEstimateCard({
    super.key,
    required this.isLoading,
    required this.estimate,
    required this.onAnalyze,
  });

  @override
  Widget build(BuildContext context) {
    final category = estimate?['category']?.toString();
    final urgency = estimate?['urgency']?.toString();
    final explanation = estimate?['explanation']?.toString();
    final priceMin = estimate?['price_min'];
    final priceMax = estimate?['price_max'];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MissionRequestColors.primaryLight,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFC7D2FE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: MissionRequestColors.primary,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.auto_awesome_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Pré-analyse Gemini',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: MissionRequestColors.ink,
                  ),
                ),
              ),
              FilledButton(
                onPressed: isLoading ? null : onAnalyze,
                style: FilledButton.styleFrom(
                  backgroundColor: MissionRequestColors.primary,
                  foregroundColor: Colors.white,
                ),
                child: Text(isLoading ? 'Analyse...' : 'Analyser'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            estimate == null
                ? 'Lance une estimation IA pour obtenir une catégorie, un niveau d’urgence et une fourchette de prix avant de choisir un artisan.'
                : 'Analyse disponible. Tu peux maintenant comparer les artisans avec une meilleure vision du besoin.',
            style: const TextStyle(
              fontSize: 13,
              color: MissionRequestColors.muted,
              height: 1.4,
            ),
          ),
          if (estimate != null) ...[
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                EstimateBadge(
                  label: 'Catégorie',
                  value: category ?? 'Non définie',
                ),
                EstimateBadge(
                  label: 'Urgence',
                  value: urgency ?? 'Non définie',
                ),
                EstimateBadge(
                  label: 'Budget',
                  value:
                      '${formatEstimateFcfa(priceMin)} - ${formatEstimateFcfa(priceMax)}',
                ),
              ],
            ),
            if (explanation != null && explanation.trim().isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                explanation,
                style: const TextStyle(
                  fontSize: 13,
                  color: MissionRequestColors.ink,
                  height: 1.4,
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class EstimateBadge extends StatelessWidget {
  final String label;
  final String value;

  const EstimateBadge({
    super.key,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: RichText(
        text: TextSpan(
          style:
              const TextStyle(fontSize: 12, color: MissionRequestColors.muted),
          children: [
            TextSpan(text: '$label\n'),
            TextSpan(
              text: value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: MissionRequestColors.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String formatEstimateFcfa(dynamic amount) {
  final value =
      amount is int ? amount : int.tryParse(amount?.toString() ?? '') ?? 0;

  final formatted = value.toString().replaceAllMapped(
        RegExp(r'\B(?=(\d{3})+(?!\d))'),
        (_) => ' ',
      );

  return '$formatted FCFA';
}
