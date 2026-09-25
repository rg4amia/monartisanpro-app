import 'package:flutter/material.dart';

import 'mission_request_colors.dart';

class GeminiEstimateCard extends StatelessWidget {
  final bool isLoading;
  final Map<String, dynamic>? estimate;
  final Map<String, dynamic>? preDiagnostic;
  final VoidCallback onAnalyze;

  const GeminiEstimateCard({
    super.key,
    required this.isLoading,
    required this.estimate,
    this.preDiagnostic,
    required this.onAnalyze,
  });

  @override
  Widget build(BuildContext context) {
    final diag = preDiagnostic;
    final pricing =
        diag != null && diag['pricing'] is Map ? diag['pricing'] as Map : null;
    final effectiveEstimate = estimate ??
        (diag != null
            ? {
                'category': diag['recommended_trade'] ?? 'Général',
                'urgency': diag['severity'] ?? 'moyen',
                'explanation': diag['diagnostic_summary'],
                'price_min': pricing?['total_min'],
                'price_max': pricing?['total_max'],
              }
            : null);

    final category = effectiveEstimate?['category']?.toString();
    final urgency = effectiveEstimate?['urgency']?.toString();
    final explanation = effectiveEstimate?['explanation']?.toString();
    final priceMin = effectiveEstimate?['price_min'];
    final priceMax = effectiveEstimate?['price_max'];

    final precautions = (preDiagnostic?['urgency_precautions'] as List?)
            ?.map((e) => e.toString())
            .toList() ??
        const [];

    final materials = (preDiagnostic?['estimated_materials'] as List?)
            ?.map((e) => e is Map ? Map<String, dynamic>.from(e) : null)
            .whereType<Map<String, dynamic>>()
            .toList() ??
        const [];

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
                  'Pré-analyse Gemini 3.6 Flash',
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
            effectiveEstimate == null
                ? 'Lance une analyse IA avec tes photos ou ta vidéo pour identifier la panne, les pièces nécessaires et une estimation budgétaire.'
                : 'Diagnostic établi. Tu peux maintenant choisir ton artisan en toute sérénité.',
            style: const TextStyle(
              fontSize: 13,
              color: MissionRequestColors.muted,
              height: 1.4,
            ),
          ),
          if (effectiveEstimate != null) ...[
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                EstimateBadge(
                  label: 'Métier conseillé',
                  value: category ?? 'Non défini',
                ),
                EstimateBadge(
                  label: 'Sévérité',
                  value: urgency != null ? urgency.toUpperCase() : 'Non définie',
                ),
                EstimateBadge(
                  label: 'Budget estimé',
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
                  fontWeight: FontWeight.w500,
                  color: MissionRequestColors.ink,
                  height: 1.4,
                ),
              ),
            ],
            if (precautions.isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFFECACA)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.shield_outlined, color: Color(0xFFDC2626), size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Mesures de sécurité immédiates :',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF991B1B),
                            ),
                          ),
                          const SizedBox(height: 4),
                          ...precautions.map(
                            (p) => Text(
                              '• $p',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF7F1D1D),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (materials.isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Fournitures & Pièces probables :',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: MissionRequestColors.ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    ...materials.map(
                      (m) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                '• ${m['name']}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: MissionRequestColors.ink,
                                ),
                              ),
                            ),
                            Text(
                              formatEstimateFcfa(m['estimated_price']),
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: MissionRequestColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
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
