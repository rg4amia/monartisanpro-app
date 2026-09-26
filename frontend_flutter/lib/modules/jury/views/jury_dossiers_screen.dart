import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/jury_dossier_model.dart';
import '../controllers/jury_dossiers_controller.dart';

/// Espace juré : dossiers d'arbitrage confiés à l'artisan (Chantier 12).
class JuryDossiersScreen extends GetView<JuryDossiersController> {
  const JuryDossiersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Espace juré'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final error = controller.errorMsg.value;
        if (error != null) {
          return _ErrorState(message: error, onRetry: controller.load);
        }

        final open = controller.openDossiers;
        final closed = controller.closedDossiers;

        return RefreshIndicator(
          onRefresh: controller.load,
          color: AppColors.primary,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            children: [
              const Text(
                'Vous êtes sollicité comme expert de votre métier pour départager '
                'un client et un artisan. Les dossiers sont anonymes : vous '
                'jugez les travaux et les preuves, jamais les personnes.',
                style: TextStyle(
                  fontSize: 12.5,
                  height: 1.4,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              if (controller.dossiers.isEmpty)
                const Text(
                  'Aucun dossier ne vous a été confié. Vous serez notifié dès '
                  'qu\'un litige de votre métier aura besoin de votre avis.',
                  style:
                      TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
              if (open.isNotEmpty) ...[
                const _SectionTitle('À instruire'),
                for (final dossier in open) _DossierCard(dossier: dossier),
                const SizedBox(height: 12),
              ],
              if (closed.isNotEmpty) ...[
                const _SectionTitle('Dossiers traités'),
                for (final dossier in closed) _DossierCard(dossier: dossier),
              ],
            ],
          ),
        );
      }),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w800,
          color: AppColors.textPrimary,
        ),
      ),
    );
  }
}

class _DossierCard extends StatelessWidget {
  const _DossierCard({required this.dossier});

  final JuryDossierSummary dossier;

  String _deadline(DateTime value) {
    try {
      return DateFormat("dd MMM 'à' HH:mm", 'fr_FR').format(value.toLocal());
    } catch (_) {
      return DateFormat('dd/MM HH:mm').format(value.toLocal());
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = dossier.isOpen
        ? AppColors.primary
        : (dossier.status == 'voted'
            ? AppColors.success
            : AppColors.textSecondary);

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: Colors.white,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.border),
        ),
        child: InkWell(
          onTap: () async {
            await Get.toNamed(
              Routes.juryDossierDetail,
              arguments: {'litigeId': dossier.litigeId},
            );
            if (Get.isRegistered<JuryDossiersController>()) {
              await Get.find<JuryDossiersController>().load();
            }
          },
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Dossier #${dossier.litigeId} · ${dossier.interventionType}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                    Text(
                      juryReviewStatusLabel(dossier.status),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: color,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                if (dossier.motif != null)
                  Text(
                    'Motif : ${dossier.motif}',
                    style: const TextStyle(fontSize: 12.5),
                  ),
                Text(
                  'Mission de ${Formatters.fcfa(dossier.montantMission)} · '
                  '${dossier.preuvesCount} preuve(s)',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 6),
                if (dossier.isOpen && dossier.expiresAt != null)
                  Text(
                    'Avis attendu avant le ${_deadline(dossier.expiresAt!)} · '
                    'indemnité ${Formatters.fcfa(dossier.compensation)}',
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary,
                    ),
                  )
                else if (dossier.verdict != null)
                  Text(
                    'Votre avis : ${JuryVerdict.label(dossier.verdict)}',
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
