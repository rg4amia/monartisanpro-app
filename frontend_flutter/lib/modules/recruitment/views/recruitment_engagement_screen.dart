import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_engagement_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/recruitment_engagement_controller.dart';

const _engagementStatusLabels = {
  'pending_artisan_acceptance': "En attente d'acceptation",
  'pending_payment': 'En attente de paiement',
  'active': 'En cours',
  'completed': 'Terminé',
  'cancelled': 'Annulé',
};

const _workdayStatusLabels = {
  'awaiting_payment': 'En attente de paiement',
  'pending': 'À valider',
  'validated': 'Validée',
};

String _formatFcfa(int value) {
  final s = value.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i > 0 && (s.length - i) % 3 == 0) buffer.write(' ');
    buffer.write(s[i]);
  }
  return '${buffer.toString()} FCFA';
}

class RecruitmentEngagementScreen
    extends GetView<RecruitmentEngagementController> {
  const RecruitmentEngagementScreen({super.key});

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
        title: const Text(
          'Engagement de recrutement',
          style: TextStyle(
            color: Colors.black87,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: controller.load,
        child: Obx(() {
          final engagement = controller.engagement.value;
          if (controller.isLoading.value && engagement == null) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [LoadingShimmer.list(count: 4)],
            );
          }
          if (engagement == null) {
            return ListView(
              padding: const EdgeInsets.all(24),
              children: const [
                SizedBox(height: 60),
                Center(child: Text('Engagement introuvable.')),
              ],
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              _HeaderCard(engagement: engagement),
              const SizedBox(height: 14),
              if (controller.isArtisan &&
                  engagement.status == 'pending_artisan_acceptance')
                _AcceptDeclineRow(),
              if (controller.isRecruiter && engagement.unpaidAmount > 0) ...[
                _PayEscrowButton(amount: engagement.unpaidAmount),
                const SizedBox(height: 14),
              ],
              if (controller.isRecruiter &&
                  ['pending_payment', 'active'].contains(engagement.status))
                _ExtendButton(),
              const SizedBox(height: 20),
              const Text(
                'Journées de travail',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              ...engagement.workdays.map((w) => _WorkdayTile(workday: w)),
            ],
          );
        }),
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  final RecruitmentEngagementModel engagement;

  const _HeaderCard({required this.engagement});

  @override
  Widget build(BuildContext context) {
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
              Expanded(
                child: Text(
                  engagement.offerTitle ?? 'Offre de recrutement',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _engagementStatusLabels[engagement.status] ??
                      engagement.status,
                  style: const TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _InfoRow(
            label: 'Artisan',
            value:
                '${engagement.artisanName ?? '—'} · ${engagement.artisanPhone ?? '—'}',
          ),
          _InfoRow(
            label: 'Recruteur',
            value:
                '${engagement.recruiterName ?? '—'} · ${engagement.recruiterPhone ?? '—'}',
          ),
          _InfoRow(
            label: 'Taux journalier',
            value: _formatFcfa(engagement.dailyRate),
          ),
          _InfoRow(
            label: 'Durée',
            value:
                '${engagement.totalDays} jour(s) · ${engagement.validatedDays} validé(s)',
          ),
          _InfoRow(
            label: 'Montant total séquestré',
            value: _formatFcfa(engagement.montantTotal),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        children: [
          SizedBox(
            width: 130,
            child: Text(
              label,
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AcceptDeclineRow extends GetView<RecruitmentEngagementController> {
  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Row(
          children: [
            Expanded(
              child: ElevatedButton(
                onPressed:
                    controller.isProcessing.value ? null : controller.accept,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.success,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text("J'accepte la mission"),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton(
                onPressed:
                    controller.isProcessing.value ? null : controller.decline,
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.danger,
                  side: const BorderSide(color: AppColors.danger),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('Refuser'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PayEscrowButton extends GetView<RecruitmentEngagementController> {
  final int amount;

  const _PayEscrowButton({required this.amount});

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed:
              controller.isProcessing.value ? null : controller.payEscrow,
          icon: const Icon(Icons.lock_outline, size: 18),
          label: Text('Payer le séquestre (${_formatFcfa(amount)})'),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 13),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
      ),
    );
  }
}

class _ExtendButton extends GetView<RecruitmentEngagementController> {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: () => _showExtendDialog(context),
        icon: const Icon(Icons.add_circle_outline, size: 18),
        label: const Text('Prolonger le contrat'),
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primary,
          side: const BorderSide(color: AppColors.primary),
          padding: const EdgeInsets.symmetric(vertical: 13),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }

  void _showExtendDialog(BuildContext context) {
    final daysCtrl = TextEditingController();
    Get.dialog(
      AlertDialog(
        title: const Text('Prolonger le contrat'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              "Le séquestre devra être complété pour les jours ajoutés avant qu'ils ne soient validables.",
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: daysCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Jours supplémentaires',
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
            onPressed: () {
              final days = int.tryParse(daysCtrl.text.trim());
              if (days == null || days <= 0) return;
              Get.back();
              Get.find<RecruitmentEngagementController>().extend(days);
            },
            child: const Text('Confirmer'),
          ),
        ],
      ),
    );
  }
}

class _WorkdayTile extends GetView<RecruitmentEngagementController> {
  final RecruitmentWorkdayModel workday;

  const _WorkdayTile({required this.workday});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final validating = controller.validatingWorkdayId.value == workday.id;
      final canValidate = controller.isRecruiter && workday.status == 'pending';

      return Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: AppColors.cardShadow,
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Jour ${workday.dayNumber}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    _formatFcfa(workday.montant),
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
            if (canValidate)
              ElevatedButton(
                onPressed: validating
                    ? null
                    : () => controller.validateWorkday(workday),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.success,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: Text(
                  validating ? '...' : 'Valider',
                  style: const TextStyle(fontSize: 12),
                ),
              )
            else
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: AppColors.textMuted.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _workdayStatusLabels[workday.status] ?? workday.status,
                  style: const TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
          ],
        ),
      );
    });
  }
}
