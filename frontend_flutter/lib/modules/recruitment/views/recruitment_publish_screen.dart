import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../../services/models/sector_model.dart';
import '../../services/utils/service_icon_helper.dart';
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

String _isoDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

String _displayDate(String isoDate) {
  final parts = isoDate.split('-');
  if (parts.length != 3) return isoDate;
  return '${parts[2]}/${parts[1]}/${parts[0]}';
}

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
    final dateDebut = Rx<DateTime?>(null);
    final dateFin = Rx<DateTime?>(null);

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
        dateDebut: dateDebut.value == null ? null : _isoDate(dateDebut.value!),
        deadlineAt: dateFin.value == null ? null : _isoDate(dateFin.value!),
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
        missionType.value = 'journalier';
        dateDebut.value = null;
        dateFin.value = null;
        controller.selectedSector.value = null;
        controller.selectedTrade.value = null;
      }
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionCard(
            icon: Icons.description_outlined,
            title: "Informations de l'offre",
            children: [
              _Field(
                label: "Titre de l'offre",
                controller: titleCtrl,
                hint: 'Ex : Renfort maçons — chantier Cocody',
              ),
              const SizedBox(height: 14),
              _Field(
                label: 'Description',
                controller: descriptionCtrl,
                hint: "Détaillez le besoin, la durée, l'outillage...",
                maxLines: 4,
              ),
            ],
          ),
          const SizedBox(height: 14),
          _SectionCard(
            icon: Icons.category_outlined,
            title: 'Secteur & métier',
            subtitle:
                "Choisissez d'abord le secteur, puis le métier recherché.",
            children: [
              Obx(() {
                if (controller.sectors.isEmpty) {
                  return const Text(
                    'Chargement des secteurs...',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  );
                }

                final selectedSector = controller.selectedSector.value;

                if (selectedSector != null) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _SelectedSectorBanner(
                        sector: selectedSector,
                        onChange: () {
                          controller.selectedSector.value = null;
                          controller.selectedTrade.value = null;
                        },
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Métier',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textMuted,
                          letterSpacing: 0.3,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Obx(() {
                        if (controller.isLoadingTrades.value) {
                          return const Text(
                            'Chargement des métiers...',
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          );
                        }
                        if (controller.trades.isEmpty) {
                          return const Text(
                            'Aucun métier disponible pour ce secteur.',
                            style: TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          );
                        }
                        return Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: controller.trades
                              .map(
                                (t) => _Chip(
                                  label: t.name,
                                  selected:
                                      controller.selectedTrade.value?.id ==
                                          t.id,
                                  onTap: () => controller.selectTrade(t),
                                ),
                              )
                              .toList(),
                        );
                      }),
                    ],
                  );
                }

                return GridView.count(
                  crossAxisCount: 3,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  childAspectRatio: 0.82,
                  children: controller.sectors
                      .map(
                        (s) => _SectorGridTile(
                          sector: s,
                          onTap: () => controller.selectSector(s),
                        ),
                      )
                      .toList(),
                );
              }),
            ],
          ),
          const SizedBox(height: 14),
          _SectionCard(
            icon: Icons.work_outline_rounded,
            title: 'Type de mission',
            children: [
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
            ],
          ),
          const SizedBox(height: 14),
          _SectionCard(
            icon: Icons.location_on_outlined,
            title: 'Localisation',
            children: [
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
            ],
          ),
          const SizedBox(height: 14),
          _SectionCard(
            icon: Icons.event_outlined,
            title: 'Période de la mission',
            subtitle:
                "L'offre sera automatiquement retirée du front office à la date de fin.",
            children: [
              Row(
                children: [
                  Expanded(
                    child: Obx(
                      () => _DateField(
                        label: 'Date de début',
                        value: dateDebut.value,
                        firstDate: DateTime.now(),
                        onPicked: (picked) {
                          dateDebut.value = picked;
                          if (dateFin.value != null &&
                              dateFin.value!.isBefore(picked)) {
                            dateFin.value = null;
                          }
                        },
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Obx(
                      () => _DateField(
                        label: 'Date de fin',
                        value: dateFin.value,
                        firstDate: dateDebut.value ?? DateTime.now(),
                        onPicked: (picked) => dateFin.value = picked,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          _SectionCard(
            icon: Icons.groups_outlined,
            title: 'Rémunération & effectif',
            children: [
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
            ],
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
                        "Publier l'offre",
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

/// Regroupe visuellement une section du formulaire (titre + icône + carte).
class _SectionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final List<Widget> children;

  const _SectionCard({
    required this.icon,
    required this.title,
    this.subtitle,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
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
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 18, color: AppColors.primary),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Padding(
              padding: const EdgeInsets.only(left: 42),
              child: Text(
                subtitle!,
                style: const TextStyle(
                  fontSize: 11.5,
                  color: AppColors.textMuted,
                ),
              ),
            ),
          ],
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    );
  }
}

/// Carte secteur illustrée (icône + couleur dédiée) — bien plus visible
/// qu'une simple puce de texte pour un choix structurant du formulaire.
class _SectorGridTile extends StatelessWidget {
  final SectorModel sector;
  final VoidCallback onTap;

  const _SectorGridTile({required this.sector, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final color = ServiceIconHelper.getSectorColor(sector.name, sector.color);
    final icon = ServiceIconHelper.getSectorIcon(sector.name, sector.icon);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withValues(alpha: 0.25)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(icon, color: Colors.white, size: 20),
              ),
              const SizedBox(height: 8),
              Text(
                sector.name,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Bandeau de rappel du secteur choisi, avec possibilité de le changer.
class _SelectedSectorBanner extends StatelessWidget {
  final SectorModel sector;
  final VoidCallback onChange;

  const _SelectedSectorBanner({required this.sector, required this.onChange});

  @override
  Widget build(BuildContext context) {
    final color = ServiceIconHelper.getSectorColor(sector.name, sector.color);
    final icon = ServiceIconHelper.getSectorIcon(sector.name, sector.icon);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: 16),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              sector.name,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
          ),
          TextButton(
            onPressed: onChange,
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: const Text(
              'Changer',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  final String label;
  final DateTime? value;
  final DateTime firstDate;
  final ValueChanged<DateTime> onPicked;

  const _DateField({
    required this.label,
    required this.value,
    required this.firstDate,
    required this.onPicked,
  });

  Future<void> _pick(BuildContext context) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: value ?? firstDate,
      firstDate: firstDate,
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) onPicked(picked);
  }

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
        InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => _pick(context),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: AppColors.textMuted.withValues(alpha: 0.25),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.calendar_today_outlined,
                  size: 15,
                  color:
                      value == null ? AppColors.textMuted : AppColors.primary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    value == null
                        ? 'jj/mm/aaaa'
                        : _displayDate(_isoDate(value!)),
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight:
                          value == null ? FontWeight.w500 : FontWeight.w700,
                      color: value == null
                          ? AppColors.textMuted
                          : AppColors.textPrimary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
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
            fillColor: AppColors.background,
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
          color: selected ? AppColors.primary : AppColors.background,
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
    final hasPeriod = offer.dateDebut != null || offer.deadlineAt != null;

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
                if (hasPeriod) ...[
                  const SizedBox(height: 2),
                  Text(
                    '${offer.dateDebut != null ? _displayDate(offer.dateDebut!.substring(0, 10)) : '—'} → '
                    '${offer.deadlineAt != null ? _displayDate(offer.deadlineAt!.substring(0, 10)) : '—'}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
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
