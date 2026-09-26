import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/jury_dossier_model.dart';
import '../controllers/jury_dossier_detail_controller.dart';

/// Dossier anonymisé d'un litige et vote motivé du juré (Chantier 12).
class JuryDossierDetailScreen extends GetView<JuryDossierDetailController> {
  const JuryDossierDetailScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Dossier d\'arbitrage'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.dossier.value == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final dossier = controller.dossier.value;
        if (dossier == null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    controller.errorMsg.value ?? 'Dossier indisponible.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: controller.load,
                    child: const Text('Réessayer'),
                  ),
                ],
              ),
            ),
          );
        }

        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            _Summary(dossier: dossier),
            const SizedBox(height: 20),
            const _Title('Preuves déposées'),
            if (dossier.preuves.isEmpty)
              const Text(
                'Aucune preuve n\'a été déposée.',
                style:
                    TextStyle(fontSize: 12.5, color: AppColors.textSecondary),
              )
            else
              for (final preuve in dossier.preuves) _EvidenceTile(preuve),
            if (dossier.jalons.isNotEmpty) ...[
              const SizedBox(height: 20),
              const _Title('Étapes du chantier'),
              for (final jalon in dossier.jalons)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(
                    'Étape ${jalon.ordre} · ${jalon.description ?? ''} — '
                    '${Formatters.fcfa(jalon.montant)}',
                    style: const TextStyle(fontSize: 12.5),
                  ),
                ),
            ],
            const SizedBox(height: 24),
            if (dossier.canVote)
              _VoteForm(controller: controller)
            else
              _VerdictCard(dossier: dossier),
            const SizedBox(height: 24),
          ],
        );
      }),
    );
  }
}

class _Title extends StatelessWidget {
  const _Title(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: AppColors.textPrimary,
          ),
        ),
      );
}

class _Summary extends StatelessWidget {
  const _Summary({required this.dossier});

  final JuryDossierDetail dossier;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Dossier #${dossier.litigeId} · ${dossier.interventionType}',
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text('Mission de ${Formatters.fcfa(dossier.montantMission)}'),
          if (dossier.motif != null) Text('Motif : ${dossier.motif}'),
          if (dossier.ouvertPar != null)
            Text(
              'Litige ouvert par ${dossier.ouvertPar == 'artisan' ? 'l\'artisan' : 'le client'}',
            ),
          if (dossier.description != null) ...[
            const SizedBox(height: 8),
            Text(dossier.description!),
          ],
          const SizedBox(height: 8),
          const Text(
            'Dossier anonyme : l\'identité des parties ne vous est pas communiquée, '
            'et la vôtre ne leur est pas révélée.',
            style: TextStyle(fontSize: 11.5, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

class _EvidenceTile extends StatelessWidget {
  const _EvidenceTile(this.preuve);

  final JuryEvidence preuve;

  @override
  Widget build(BuildContext context) {
    final url = preuve.mediaUrl;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: preuve.tampered ? AppColors.danger : AppColors.border,
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (url != null && url.isNotEmpty)
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.network(
                url,
                width: 72,
                height: 72,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox(
                  width: 72,
                  height: 72,
                  child: Icon(Icons.broken_image_outlined),
                ),
              ),
            ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Preuve de la partie ${preuve.partieLabel.toLowerCase()}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                if (preuve.description != null) Text(preuve.description!),
                const SizedBox(height: 4),
                Text(
                  preuve.tampered
                      ? 'Altérée depuis son dépôt : ne pas en tenir compte.'
                      : (preuve.isCertified
                          ? 'Certifiée intègre (empreinte SHA-256).'
                          : 'Non certifiée.'),
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: preuve.tampered
                        ? AppColors.danger
                        : (preuve.isCertified
                            ? AppColors.success
                            : AppColors.textSecondary),
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

class _VoteForm extends StatelessWidget {
  const _VoteForm({required this.controller});

  final JuryDossierDetailController controller;

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final selected = controller.verdict.value;
      final busy = controller.isVoting.value;

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const _Title('Votre avis'),
          const Text(
            'L\'artisan a-t-il réalisé les travaux dans les règles de l\'art ? '
            'Votre avis est définitif.',
            style: TextStyle(fontSize: 12.5, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 10),
          for (final verdict in JuryVerdict.all)
            ListTile(
              key: ValueKey('verdict-$verdict'),
              enabled: !busy,
              contentPadding: EdgeInsets.zero,
              leading: Icon(
                selected == verdict
                    ? Icons.radio_button_checked
                    : Icons.radio_button_unchecked,
                color: selected == verdict
                    ? AppColors.primary
                    : AppColors.textSecondary,
              ),
              title: Text(JuryVerdict.label(verdict)),
              onTap: () => controller.verdict.value = verdict,
            ),
          if (selected == JuryVerdict.partage) ...[
            Text(
              'Part des travaux imputable au bon travail de l\'artisan : '
              '${controller.splitArtisanPercentage.value} %',
              style: const TextStyle(fontSize: 12.5),
            ),
            Slider(
              value: controller.splitArtisanPercentage.value.toDouble(),
              max: 100,
              divisions: 20,
              label: '${controller.splitArtisanPercentage.value} %',
              onChanged: busy
                  ? null
                  : (v) => controller.splitArtisanPercentage.value = v.round(),
            ),
          ],
          TextField(
            enabled: !busy,
            maxLines: 3,
            maxLength: 1000,
            onChanged: (v) => controller.technicalComment.value = v,
            decoration: InputDecoration(
              labelText: 'Avis technique (recommandé)',
              hintText: 'Ce que montrent les preuves, les défauts constatés…',
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          if (controller.voteError.value != null) ...[
            const SizedBox(height: 6),
            Text(
              controller.voteError.value!,
              style: const TextStyle(
                color: AppColors.danger,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          const SizedBox(height: 12),
          FilledButton(
            onPressed: busy
                ? null
                : () async {
                    final message = await controller.submitVote();
                    if (message != null) {
                      Get.snackbar(
                        'Avis enregistré',
                        message,
                        snackPosition: SnackPosition.BOTTOM,
                      );
                    }
                  },
            child: busy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Envoyer mon avis'),
          ),
        ],
      );
    });
  }
}

class _VerdictCard extends StatelessWidget {
  const _VerdictCard({required this.dossier});

  final JuryDossierDetail dossier;

  @override
  Widget build(BuildContext context) {
    final voted = dossier.verdict != null;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: voted ? AppColors.success.withValues(alpha: 0.08) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            voted
                ? 'Votre avis : ${JuryVerdict.label(dossier.verdict)}'
                : 'Le délai pour rendre votre avis est dépassé ; le dossier a été confié à un autre juré.',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          if (dossier.verdict == JuryVerdict.partage &&
              dossier.splitArtisanPercentage != null)
            Text('Part de l\'artisan : ${dossier.splitArtisanPercentage} %'),
          if (dossier.technicalComment != null) Text(dossier.technicalComment!),
          if (voted)
            Text(
              'Indemnité : ${Formatters.fcfa(dossier.compensation)}',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
        ],
      ),
    );
  }
}
