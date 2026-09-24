import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_engagement_model.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/recruitment_browse_controller.dart';
import '../widgets/voice_application_sheet.dart';

const _missionTypeLabels = {
  'tacheron_brigade': 'Brigade / tâcheron',
  'journalier': 'Journalier',
  'longue_duree': 'Longue durée',
  'urgence': 'Urgence',
};

const _engagementStatusLabels = {
  'pending_artisan_acceptance': "En attente d'acceptation",
  'pending_payment': 'En attente de paiement',
  'active': 'En cours',
  'completed': 'Terminé',
  'cancelled': 'Annulé',
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

class RecruitmentBrowseScreen extends GetView<RecruitmentBrowseController> {
  const RecruitmentBrowseScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.black87),
            onPressed: () => Get.back(),
          ),
          title: const Text(
            'Recrutement',
            style: TextStyle(
              color: Colors.black87,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
          ),
          bottom: const TabBar(
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textMuted,
            indicatorColor: AppColors.primary,
            tabs: [
              Tab(text: 'Offres'),
              Tab(text: 'Mes engagements'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [_OffersList(), _MyEngagementsList()],
        ),
      ),
    );
  }
}

class _OffersList extends GetView<RecruitmentBrowseController> {
  const _OffersList();

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: controller.load,
      child: Obx(() {
        if (controller.isLoading.value && controller.offers.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [LoadingShimmer.list(count: 5)],
          );
        }

        if (controller.offers.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(24),
            children: const [
              SizedBox(height: 60),
              Icon(Icons.work_outline, size: 48, color: AppColors.textMuted),
              SizedBox(height: 12),
              Center(
                child: Text(
                  "Aucune offre de recrutement disponible pour l'instant.",
                  textAlign: TextAlign.center,
                  style:
                      TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
              ),
            ],
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          itemCount: controller.offers.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (context, index) =>
              _OfferCard(offer: controller.offers[index]),
        );
      }),
    );
  }
}

class _MyEngagementsList extends GetView<RecruitmentBrowseController> {
  const _MyEngagementsList();

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: controller.load,
      child: Obx(() {
        if (controller.isLoading.value && controller.myEngagements.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [LoadingShimmer.list(count: 3)],
          );
        }

        if (controller.myEngagements.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(24),
            children: const [
              SizedBox(height: 60),
              Icon(
                Icons.handshake_outlined,
                size: 48,
                color: AppColors.textMuted,
              ),
              SizedBox(height: 12),
              Center(
                child: Text(
                  "Vous n'avez aucun engagement pour l'instant.",
                  textAlign: TextAlign.center,
                  style:
                      TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
              ),
            ],
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          itemCount: controller.myEngagements.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) =>
              _EngagementTile(engagement: controller.myEngagements[index]),
        );
      }),
    );
  }
}

class _EngagementTile extends StatelessWidget {
  final RecruitmentEngagementModel engagement;

  const _EngagementTile({required this.engagement});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => Get.toNamed(
          Routes.recruitmentEngagement,
          arguments: engagement.id,
        ),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            boxShadow: AppColors.cardShadow,
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      engagement.offerTitle ?? 'Offre de recrutement',
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${_formatFcfa(engagement.dailyRate)} / jour · ${engagement.totalDays} jour(s)',
                      style: const TextStyle(
                        fontSize: 11.5,
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
              const SizedBox(width: 4),
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: AppColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfferCard extends GetView<RecruitmentBrowseController> {
  final RecruitmentOfferModel offer;

  const _OfferCard({required this.offer});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final applied = controller.hasAppliedTo(offer.id);
      final applying = controller.applyingOfferId.value == offer.id;

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
                    offer.title,
                    style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _missionTypeLabels[offer.missionType] ?? offer.missionType,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
            ),
            if (offer.tradeName != null) ...[
              const SizedBox(height: 4),
              Text(
                offer.tradeName!,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(
                  Icons.location_on_outlined,
                  size: 14,
                  color: AppColors.textMuted,
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    [
                      offer.commune,
                      offer.sousQuartier,
                    ].whereType<String>().join(' — '),
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ],
            ),
            if (offer.dailyRateMin != null || offer.dailyRateMax != null) ...[
              const SizedBox(height: 4),
              Text(
                offer.dailyRateMin != null && offer.dailyRateMax != null
                    ? '${_formatFcfa(offer.dailyRateMin!)} — ${_formatFcfa(offer.dailyRateMax!)} / jour'
                    : _formatFcfa((offer.dailyRateMin ?? offer.dailyRateMax)!),
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: AppColors.success,
                ),
              ),
            ],
            const SizedBox(height: 12),
            Text(
              offer.description,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12.5,
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: applied || applying
                    ? null
                    : () =>
                        VoiceApplicationSheet.show(context, offer, controller),
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      applied ? AppColors.textMuted : AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: applying
                    ? const SizedBox(
                        height: 16,
                        width: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(
                        applied ? 'Candidature envoyée' : 'Je suis prêt',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
              ),
            ),
          ],
        ),
      );
    });
  }
}
