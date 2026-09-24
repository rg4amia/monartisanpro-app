import 'package:flutter/material.dart';
import '../../../../data/repositories/evaluation_repository.dart';
import 'settings_colors.dart';

void showMyEvaluationsDialog(BuildContext context) {
  final evaluationRepo = EvaluationRepository();

  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) {
      return Container(
        height: MediaQuery.of(context).size.height * 0.75,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.star_rounded,
                      size: 22,
                      color: Color(0xFFD97706),
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Mes avis & évaluations',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: SettingsColors.ink,
                          ),
                        ),
                        Text(
                          'Historique des notes attribuées aux intervenants',
                          style: TextStyle(
                            fontSize: 12,
                            color: SettingsColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: SettingsColors.muted),
                    onPressed: () => Navigator.pop(ctx),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: SettingsColors.subtle),
            Expanded(
              child: FutureBuilder<Map<String, dynamic>?>(
                future: evaluationRepo.getMyEvaluations(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }

                  final data = snapshot.data;
                  final given = data != null && data['given'] is List
                      ? List<Map<String, dynamic>>.from(data['given'])
                      : <Map<String, dynamic>>[];

                  if (given.isEmpty) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: const BoxDecoration(
                                color: Color(0xFFF3F4F6),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.rate_review_outlined,
                                size: 40,
                                color: SettingsColors.muted,
                              ),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'Aucun avis donné pour le moment',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                                color: SettingsColors.ink,
                              ),
                            ),
                            const SizedBox(height: 8),
                            const Text(
                              'Vos évaluations de missions et commandes terminées apparaîtront ici.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 13,
                                color: SettingsColors.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  return ListView.separated(
                    padding: const EdgeInsets.all(20),
                    itemCount: given.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, index) {
                      final item = given[index];
                      final evalue = item['evalue'] is Map
                          ? Map<String, dynamic>.from(item['evalue'])
                          : null;
                      final String name = evalue?['name'] ?? 'Intervenant';
                      final String role = evalue?['role'] ?? 'artisan';
                      final int note = item['note'] as int? ?? 0;
                      final String? comment = item['commentaire'] as String?;
                      final int? missionId = item['mission_id'] as int?;
                      final int? orderId = item['order_id'] as int?;

                      return Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: SettingsColors.surface,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: SettingsColors.subtle),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        name,
                                        style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                          color: SettingsColors.ink,
                                        ),
                                      ),
                                      Text(
                                        missionId != null
                                            ? 'Mission #$missionId'
                                            : orderId != null
                                                ? 'Commande #$orderId'
                                                : role.toUpperCase(),
                                        style: const TextStyle(
                                          fontSize: 11,
                                          color: SettingsColors.muted,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Row(
                                  children: List.generate(5, (i) {
                                    return Icon(
                                      i < note
                                          ? Icons.star_rounded
                                          : Icons.star_outline_rounded,
                                      size: 18,
                                      color: const Color(0xFFF59E0B),
                                    );
                                  }),
                                ),
                              ],
                            ),
                            if (comment != null && comment.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Text(
                                '« $comment »',
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontStyle: FontStyle.italic,
                                  color: SettingsColors.ink,
                                ),
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      );
    },
  );
}
