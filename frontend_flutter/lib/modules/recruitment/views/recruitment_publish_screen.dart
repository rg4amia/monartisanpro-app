import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/recruitment_publish_controller.dart';

const _missionTypes = [
  {'value': 'tacheron_brigade', 'label': 'Brigade / tâcheron'},
  {'value': 'journalier', 'label': 'Journalier'},
  {'value': 'longue_duree', 'label': 'Longue durée'},
  {'value': 'urgence', 'label': 'Urgence'},
];

const _statusLabels = {
  'draft': 'Brouillon',
  'pending_review': 'En attente de modération',
  'active': 'Active',
  'filled': 'Pourvue',
  'expired': 'Expirée',
  'cancelled': 'Rejetée',
};

class RecruitmentPublishScreen extends GetView<RecruitmentPublishController> {
  const RecruitmentPublishScreen({super.key});

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
              fontSize: 20,
              fontWeight: FontWeight.w600,
            ),
          ),
          bottom: const TabBar(
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textMuted,
            indicatorColor: AppColors.primary,
            tabs: [
              Tab(text: 'Publier une offre'),
              Tab(text: 'Mes offres'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [_PublishForm(), _MyOffersList()],
        ),
      ),
    );
  }
}

class _PublishForm extends GetView<RecruitmentPublishController> {
  const _PublishForm();

  @override
  Widget build(BuildContext context) {
    final titleCtrl = TextEditingController();
    final descriptionCtrl = TextEditingController();
    final communeCtrl = TextEditingController();
    final sousQuartierCtrl = TextEditingController();
    final rateMinCtrl = TextEditingController();
    final rateMaxCtrl = TextEditingController();
    final openingsCtrl = TextEditingController(text: '1');
    final missionType = 'journalier'.obs;

    Future<void> submit() async {
      if (titleCtrl.text.trim().isEmpty ||
          descriptionCtrl.text.trim().isEmpty ||
          communeCtrl.text.trim().isEmpty) {
        Get.snackbar(
          'Champs manquants',
          'Titre, description et commune sont obligatoires.',
        );
        return;
      }

      final ok = await controller.publish(
        title: titleCtrl.text.trim(),
        description: descriptionCtrl.text.trim(),
        missionType: missionType.value,
        commune: communeCtrl.text.trim(),
        sousQuartier: sousQuartierCtrl.text.trim().isEmpty
            ? null
            : sousQuartierCtrl.text.trim(),
        dailyRateMin: int.tryParse(rateMinCtrl.text.trim()),
        dailyRateMax: int.tryParse(rateMaxCtrl.text.trim()),
        openingsCount: int.tryParse(openingsCtrl.text.trim()) ?? 1,
      );

      if (ok) {
        Get.snackbar(
          'Offre publiée',
          'Votre offre de recrutement a été enregistrée.',
        );
        titleCtrl.clear();
        descriptionCtrl.clear();
        communeCtrl.clear();
        sousQuartierCtrl.clear();
        rateMinCtrl.clear();
        rateMaxCtrl.clear();
        openingsCtrl.text = '1';
        controller.selectedSector.value = null;
        controller.selectedTrade.value = null;
      }
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Field(
            label: 'Titre de l\'offre',
            controller: titleCtrl,
            hint: 'Ex : Renfort maçons — chantier Cocody',
          ),
          const SizedBox(height: 14),
          _Field(
            label: 'Description',
            controller: descriptionCtrl,
            hint: 'Détaillez le besoin, la durée, l\'outillage...',
            maxLines: 4,
          ),
          const SizedBox(height: 14),
          const Text(
            'Secteur & métier',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 6),
          Obx(() {
            if (controller.sectors.isEmpty) {
              return const Text(
                'Chargement des secteurs...',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              );
            }
            return Wrap(
              spacing: 8,
              runSpacing: 8,
              children: controller.sectors
                  .map(
                    (s) => _Chip(
                      label: s.name,
                      selected: controller.selectedSector.value?.id == s.id,
                      onTap: () => controller.selectSector(s),
                    ),
                  )
                  .toList(),
            );
          }),
          const SizedBox(height: 10),
          Obx(() {
            if (controller.selectedSector.value == null) {
              return const SizedBox.shrink();
            }
            if (controller.isLoadingTrades.value) {
              return const Text(
                'Chargement des métiers...',
                style: TextStyle(fontSize: 12, color: AppColors.textMuted),
              );
            }
            return Wrap(
              spacing: 8,
              runSpacing: 8,
              children: controller.trades
                  .map(
                    (t) => _Chip(
                      label: t.name,
                      selected: controller.selectedTrade.value?.id == t.id,
                      onTap: () => controller.selectTrade(t),
                    ),
                  )
                  .toList(),
            );
          }),
          const SizedBox(height: 14),
          const Text(
            'Type de mission',
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 6),
          Obx(
            () => Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _missionTypes
                  .map(
                    (m) => _Chip(
                      label: m['label']!,
                      selected: missionType.value == m['value'],
                      onTap: () => missionType.value = m['value']!,
                    ),
                  )
                  .toList(),
            ),
          ),
          const SizedBox(height: 14),
          _Field(
            label: 'Commune',
            controller: communeCtrl,
            hint: 'Ex : Cocody',
          ),
          const SizedBox(height: 14),
          _Field(
            label: 'Quartier (optionnel)',
            controller: sousQuartierCtrl,
            hint: 'Ex : Angré 8e Tranche',
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _Field(
                  label: 'Rémunération min (FCFA/j)',
                  controller: rateMinCtrl,
                  keyboardType: TextInputType.number,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _Field(
                  label: 'Rémunération max (FCFA/j)',
                  controller: rateMaxCtrl,
                  keyboardType: TextInputType.number,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _Field(
            label: 'Nombre de profils recherchés',
            controller: openingsCtrl,
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 20),
          Obx(
            () => SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: controller.isSubmitting.value ? null : submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: controller.isSubmitting.value
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text(
                        'Publier l\'offre',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Field extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String? hint;
  final int maxLines;
  final TextInputType? keyboardType;

  const _Field({
    required this.label,
    required this.controller,
    this.hint,
    this.maxLines = 1,
    this.keyboardType,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          maxLines: maxLines,
          keyboardType: keyboardType,
          decoration: InputDecoration(
            hintText: hint,
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide.none,
            ),
          ),
        ),
      ],
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: selected
                ? AppColors.primary
                : AppColors.textMuted.withValues(alpha: 0.3),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }
}

class _MyOffersList extends GetView<RecruitmentPublishController> {
  const _MyOffersList();

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: controller.loadMyOffers,
      child: Obx(() {
        if (controller.isLoading.value && controller.myOffers.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [LoadingShimmer.list(count: 4)],
          );
        }

        if (controller.myOffers.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(24),
            children: const [
              SizedBox(height: 60),
              Center(
                child: Text(
                  "Vous n'avez publié aucune offre pour l'instant.",
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
          itemCount: controller.myOffers.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) =>
              _MyOfferTile(offer: controller.myOffers[index]),
        );
      }),
    );
  }
}

class _MyOfferTile extends StatelessWidget {
  final RecruitmentOfferModel offer;

  const _MyOfferTile({required this.offer});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
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
                  offer.title,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${offer.applicationsCount} candidature(s) reçue(s)',
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              _statusLabels[offer.status] ?? offer.status,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
                color: AppColors.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
