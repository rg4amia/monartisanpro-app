import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/litige_detail_controller.dart';

class LitigeDetailScreen extends GetView<LitigeDetailController> {
  const LitigeDetailScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Details du litige')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final data = controller.litige.value;
        if (data == null) {
          return const Center(child: Text('Erreur de chargement'));
        }

        final isResolved = data['statut'] == 'resolu';
        final proofs = (data['preuves'] as List<dynamic>? ?? const []);
        final evidenceCounts =
            data['evidenceCounts'] as Map<String, dynamic>? ?? const {};

        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _StatusBadge(
                statut: (data['statut'] ?? '').toString(),
                workflowStep: (data['workflowStep'] ?? '').toString(),
              ),
              if (controller.isJuror) ...[
                const SizedBox(height: 16),
                _JurySection(
                  litige: data,
                  review: controller.myJuryReview!,
                  isVoting: controller.isVoting.value,
                  onVote: controller.castJuryVote,
                ),
              ],
              const SizedBox(height: 16),
              _InfoCard(
                title: 'Etape en cours',
                content:
                    (data['nextAction'] ?? 'Dossier en cours de traitement')
                        .toString(),
              ),
              const SizedBox(height: 16),
              _InfoCard(
                title: 'Compteur de preuves',
                content:
                    'Client: ${evidenceCounts['client'] ?? 0}/${evidenceCounts['clientRequired'] ?? 2}  |  Artisan: ${evidenceCounts['artisan'] ?? 0}/${evidenceCounts['artisanRequired'] ?? 1}',
              ),
              const SizedBox(height: 16),
              const Text(
                'Description du signalement',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Text((data['description'] ?? '').toString()),
              const SizedBox(height: 20),
              if (controller.canUploadEvidence) ...[
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: controller.isUploadingEvidence.value
                        ? null
                        : controller.uploadEvidence,
                    icon: controller.isUploadingEvidence.value
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.camera_alt_outlined),
                    label: Text(
                      controller.isUploadingEvidence.value
                          ? 'Envoi en cours...'
                          : 'Ajouter une preuve photo',
                    ),
                  ),
                ),
                const SizedBox(height: 20),
              ],
              if (proofs.isNotEmpty) ...[
                const Text(
                  'Preuves deja deposees',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 12),
                ...proofs.map(
                  (proof) => _ProofTile(data: proof as Map<String, dynamic>),
                ),
                const SizedBox(height: 20),
              ],
              if (isResolved) ...[
                const Divider(),
                const SizedBox(height: 24),
                const Text(
                  'Decision d arbitrage',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 18,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(height: 12),
                _DecisionCard(
                  decision: data['decision']?.toString(),
                  notes: data['adminNotes']?.toString(),
                ),
              ] else ...[
                const Card(
                  color: AppColors.infoLight,
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'Le dossier est en cours d instruction. Vous serez notifie des qu une decision sera prise.',
                      style: TextStyle(fontSize: 13),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      }),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final String title;
  final String content;

  const _InfoCard({required this.title, required this.content});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text(content),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String statut;
  final String workflowStep;

  const _StatusBadge({required this.statut, required this.workflowStep});

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.warning;
    String label = 'En cours';

    if (statut == 'resolu') {
      color = AppColors.success;
      label = 'Resolu';
    } else if (workflowStep == 'preuves') {
      label = 'Collecte des preuves';
    } else if (workflowStep == 'visite_referent') {
      label = 'Visite referent';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        label,
        style:
            TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12),
      ),
    );
  }
}

class _ProofTile extends StatelessWidget {
  final Map<String, dynamic> data;

  const _ProofTile({required this.data});

  @override
  Widget build(BuildContext context) {
    final coordinates = data['coordinates'] as Map<String, dynamic>?;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            (data['partie'] ?? 'preuve').toString().toUpperCase(),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.primary,
            ),
          ),
          if ((data['description'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(data['description'].toString()),
          ],
          if (coordinates != null) ...[
            const SizedBox(height: 6),
            Text(
              'GPS: ${coordinates['lat']}, ${coordinates['lng']}',
              style:
                  const TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
        ],
      ),
    );
  }
}

class _JurySection extends StatelessWidget {
  final Map<String, dynamic> litige;
  final Map<String, dynamic> review;
  final bool isVoting;
  final ValueChanged<String> onVote;

  const _JurySection({
    required this.litige,
    required this.review,
    required this.isVoting,
    required this.onVote,
  });

  @override
  Widget build(BuildContext context) {
    final verdict = review['verdict']?.toString();
    final compensation = review['compensation'];
    final parties = litige['parties'] as Map<String, dynamic>? ?? const {};
    final client = parties['client'] as Map<String, dynamic>?;
    final artisan = parties['artisan'] as Map<String, dynamic>?;
    final motif = (litige['motif'] ?? '').toString();
    final description = (litige['description'] ?? '').toString();
    final evidenceCounts =
        litige['evidenceCounts'] as Map<String, dynamic>? ?? const {};
    final proofs = (litige['preuves'] as List<dynamic>? ?? const []);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primary, width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.balance_outlined, color: AppColors.primary),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Vous êtes juré sur ce dossier',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (motif.isNotEmpty) ...[
            Text(
              'Motif : $motif',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 6),
          ],
          Text('Client : ${client?['name'] ?? 'Inconnu'}'),
          Text('Artisan : ${artisan?['name'] ?? 'Inconnu'}'),
          const SizedBox(height: 8),
          if (description.isNotEmpty) Text(description),
          const SizedBox(height: 8),
          Text(
            'Preuves — Client : ${evidenceCounts['client'] ?? 0}/${evidenceCounts['clientRequired'] ?? 2}  |  Artisan : ${evidenceCounts['artisan'] ?? 0}/${evidenceCounts['artisanRequired'] ?? 1}',
            style:
                const TextStyle(fontSize: 12, color: AppColors.textSecondary),
          ),
          if (proofs.isEmpty) ...[
            const SizedBox(height: 6),
            const Text(
              'Aucune preuve deposee pour le moment.',
              style: TextStyle(
                fontSize: 12,
                fontStyle: FontStyle.italic,
                color: AppColors.textSecondary,
              ),
            ),
          ],
          const SizedBox(height: 16),
          if (verdict == null) ...[
            const Text(
              'L\'artisan a-t-il rempli ses obligations ?',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    onPressed:
                        isVoting ? null : () => onVote('CONFORME'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.success,
                    ),
                    child: isVoting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Text('CONFORME'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed:
                        isVoting ? null : () => onVote('NON_CONFORME'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.danger,
                    ),
                    child: isVoting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Text('NON CONFORME'),
                  ),
                ),
              ],
            ),
          ] else ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: verdict == 'CONFORME'
                    ? AppColors.success.withValues(alpha: 0.1)
                    : AppColors.danger.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(
                    verdict == 'CONFORME'
                        ? Icons.check_circle
                        : Icons.cancel,
                    color: verdict == 'CONFORME'
                        ? AppColors.success
                        : AppColors.danger,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Vous avez voté : ${verdict == 'CONFORME' ? 'CONFORME' : 'NON CONFORME'}',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            if (compensation is num) ...[
              const SizedBox(height: 8),
              Text(
                'Compensation retenue : ${compensation.toInt()} FCFA',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _DecisionCard extends StatelessWidget {
  final String? decision;
  final String? notes;

  const _DecisionCard({this.decision, this.notes});

  @override
  Widget build(BuildContext context) {
    String label = 'Arbitrage effectue';
    if (decision == 'client') label = 'Remboursement client';
    if (decision == 'artisan') label = 'Paiement artisan';
    if (decision == 'mixte') label = 'Decision mixte';
    if (decision == 'gel') label = 'Gel et visite referent';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primary, width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          if (notes != null && notes!.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text(
              'Notes administrateur :',
              style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
            const SizedBox(height: 4),
            Text(notes!, style: const TextStyle(fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}
