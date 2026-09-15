import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_application_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/recruitment_applicants_controller.dart';

const _applicationStatusLabels = {
  'submitted': 'Nouvelle',
  'shortlisted': 'Présélectionné',
  'contacted': 'Contacté',
  'rejected': 'Rejeté',
  'confirmed': 'Retenu',
};

const _applicationStatusColors = {
  'submitted': AppColors.textMuted,
  'shortlisted': AppColors.info,
  'contacted': Color(0xFFD97706),
  'rejected': AppColors.danger,
  'confirmed': AppColors.success,
};

const _statusActions = [
  {'value': 'shortlisted', 'label': 'Présélectionner'},
  {'value': 'contacted', 'label': 'Marquer contacté'},
  {'value': 'rejected', 'label': 'Rejeter'},
];

class RecruitmentApplicantsScreen
    extends GetView<RecruitmentApplicantsController> {
  const RecruitmentApplicantsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black87),
          onPressed: () => Get.back(),
        ),
        title: Text(
          controller.offer.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.black87,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: controller.load,
        child: Obx(() {
          if (controller.isLoading.value &&
              controller.applicantsUnlocked.value == null) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [LoadingShimmer.list(count: 4)],
            );
          }

          if (controller.applicantsUnlocked.value == false) {
            return ListView(
              padding: const EdgeInsets.all(24),
              children: const [
                SizedBox(height: 40),
                _EscrowPaywall(),
              ],
            );
          }

          if (controller.applications.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(24),
              children: const [
                SizedBox(height: 60),
                Icon(
                  Icons.people_outline,
                  size: 48,
                  color: AppColors.textMuted,
                ),
                SizedBox(height: 12),
                Center(
                  child: Text(
                    "Aucun artisan n'a encore postulé à cette offre.",
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            itemCount: controller.applications.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) =>
                _ApplicantCard(application: controller.applications[index]),
          );
        }),
      ),
    );
  }
}

class _ApplicantCard extends GetView<RecruitmentApplicantsController> {
  final RecruitmentApplicationModel application;

  const _ApplicantCard({required this.application});

  @override
  Widget build(BuildContext context) {
    final artisan = application.artisan;

    return Obx(() {
      final updating = controller.updatingId.value == application.id;

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: AppColors.cardShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                  child: Text(
                    (artisan?.name.isNotEmpty ?? false)
                        ? artisan!.name[0].toUpperCase()
                        : '?',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        artisan?.name ?? 'Artisan indisponible',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const Text(
                        'Numéro masqué — contact via demande de rappel',
                        style: TextStyle(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: (_applicationStatusColors[application.status] ??
                            AppColors.textMuted)
                        .withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    _applicationStatusLabels[application.status] ??
                        application.status,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: _applicationStatusColors[application.status] ??
                          AppColors.textMuted,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _ScorePill(
                  icon: Icons.verified_outlined,
                  label: 'Score ${artisan?.scoreProsartisan ?? 0}/1000',
                ),
                const SizedBox(width: 8),
                if (application.matchingScore != null)
                  _ScorePill(
                    icon: Icons.insights_outlined,
                    label:
                        'Matching ${application.matchingScore!.toStringAsFixed(0)}/100',
                  ),
              ],
            ),
            const SizedBox(height: 14),
            if (artisan != null)
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed:
                      controller.requestingCallbackId.value == application.id
                          ? null
                          : () => controller.requestCallback(application),
                  icon: const Icon(Icons.phone_callback_outlined, size: 16),
                  label: Text(
                    controller.requestingCallbackId.value == application.id
                        ? 'Envoi en cours…'
                        : 'Demander un rappel',
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.primary,
                    side: const BorderSide(color: AppColors.primary),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _statusActions.map((action) {
                final isCurrent = application.status == action['value'];
                return OutlinedButton(
                  onPressed: (updating || isCurrent)
                      ? null
                      : () => controller.updateStatus(
                            application,
                            action['value']!,
                          ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.textSecondary,
                    side: BorderSide(
                      color: AppColors.textMuted.withValues(alpha: 0.3),
                    ),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: Text(
                    action['label']!,
                    style: const TextStyle(fontSize: 11.5),
                  ),
                );
              }).toList(),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: application.engagementId != null
                  ? OutlinedButton.icon(
                      onPressed: () => Get.toNamed(
                        Routes.recruitmentEngagement,
                        arguments: application.engagementId,
                      ),
                      icon: const Icon(Icons.description_outlined, size: 16),
                      label: const Text("Voir l'engagement"),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.success,
                        side: const BorderSide(color: AppColors.success),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    )
                  : ElevatedButton.icon(
                      onPressed: updating
                          ? null
                          : () => _showEngageDialog(context, application),
                      icon: const Icon(Icons.handshake_outlined, size: 16),
                      label: const Text('Retenir & proposer un engagement'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.success,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    ),
            ),
          ],
        ),
      );
    });
  }

  void _showEngageDialog(
    BuildContext context,
    RecruitmentApplicationModel application,
  ) {
    final rateCtrl = TextEditingController();
    final daysCtrl = TextEditingController();

    Get.dialog(
      AlertDialog(
        title: const Text('Proposer un engagement'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Le nombre de jours est calculé à partir de la période de '
              "l'offre si elle est renseignée, sinon précisez-le.",
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: rateCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Taux journalier (FCFA)',
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: daysCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Nombre de jours (optionnel)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () async {
              final rate = int.tryParse(rateCtrl.text.trim());
              if (rate == null || rate <= 0) {
                Get.snackbar(
                  'Taux invalide',
                  'Saisissez un taux journalier valide.',
                );
                return;
              }
              final days = int.tryParse(daysCtrl.text.trim());
              Get.back();

              final controller = Get.find<RecruitmentApplicantsController>();
              final engagementId = await controller.createEngagement(
                application,
                rate,
                days,
              );
              if (engagementId != null) {
                await Get.toNamed(
                  Routes.recruitmentEngagement,
                  arguments: engagementId,
                );
              }
            },
            child: const Text('Confirmer'),
          ),
        ],
      ),
    );
  }
}

class _EscrowPaywall extends GetView<RecruitmentApplicantsController> {
  const _EscrowPaywall();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: AppColors.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.lock_outline, color: AppColors.primary),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Candidatures verrouillées',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Text(
            'Payez le séquestre pour consulter et contacter les postulants '
            'de cette offre. Ce montant sera automatiquement déduit du '
            "paiement de l'engagement une fois un candidat retenu.",
            style: TextStyle(
              fontSize: 12.5,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),
          Obx(
            () => SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: controller.isUnlocking.value
                    ? null
                    : () => _showUnlockDialog(context),
                icon: controller.isUnlocking.value
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.lock_open_outlined, size: 18),
                label: Text(
                  controller.isUnlocking.value
                      ? 'Paiement en cours…'
                      : 'Payer le séquestre',
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showUnlockDialog(BuildContext context) {
    final offer = controller.offer;
    final rateCtrl = TextEditingController(
      text: (offer.dailyRateMax ?? offer.dailyRateMin)?.toString() ?? '',
    );
    final daysCtrl = TextEditingController();
    final hasOfferDates = offer.dateDebut != null && offer.deadlineAt != null;

    Get.dialog(
      AlertDialog(
        title: const Text('Payer le séquestre'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              hasOfferDates
                  ? "Le nombre de jours est calculé à partir de la période de l'offre."
                  : 'Précisez le nombre de jours estimé.',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: rateCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Taux journalier estimé (FCFA)',
              ),
            ),
            if (!hasOfferDates) ...[
              const SizedBox(height: 10),
              TextField(
                controller: daysCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nombre de jours estimé',
                ),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              final rate = int.tryParse(rateCtrl.text.trim());
              if (rate == null || rate <= 0) {
                Get.snackbar(
                  'Taux invalide',
                  'Saisissez un taux journalier valide.',
                );
                return;
              }

              int? days;
              if (!hasOfferDates) {
                days = int.tryParse(daysCtrl.text.trim());
                if (days == null || days <= 0) {
                  Get.snackbar(
                    'Durée invalide',
                    'Saisissez un nombre de jours valide.',
                  );
                  return;
                }
              }

              Get.back();
              Get.find<RecruitmentApplicantsController>()
                  .unlockApplicants(rate, days);
            },
            child: const Text('Payer'),
          ),
        ],
      ),
    );
  }
}

class _ScorePill extends StatelessWidget {
  final IconData icon;
  final String label;

  const _ScorePill({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: AppColors.textMuted),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }
}
