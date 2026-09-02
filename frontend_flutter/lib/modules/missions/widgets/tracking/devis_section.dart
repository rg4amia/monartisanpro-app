import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/devis_model.dart';
import '../../../../data/models/mission_model.dart';
import 'open_devis_creation.dart';
import 'section_container.dart';
import 'status_pill.dart';
import 'tracking_info_row.dart';

/// Section devis : soit l'invite/CTA de création (artisan, aucun devis),
/// soit le récapitulatif du devis existant avec accès au détail (client).
class DevisSection extends StatelessWidget {
  const DevisSection({
    required this.role,
    required this.mission,
    required this.devis,
    super.key,
  });

  final String role;
  final MissionModel mission;
  final DevisModel? devis;

  @override
  Widget build(BuildContext context) {
    final isArtisan = role == 'artisan';

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Devis',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 14),
          if (devis == null)
            _EmptyDevis(mission: mission, isArtisan: isArtisan)
          else
            _DevisRecap(
              devis: devis!,
              isArtisan: isArtisan,
            ),
        ],
      ),
    );
  }
}

class _EmptyDevis extends StatelessWidget {
  const _EmptyDevis({required this.mission, required this.isArtisan});

  final MissionModel mission;
  final bool isArtisan;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          isArtisan
              ? 'Aucun devis n\'a encore ete envoye pour cette mission.'
              : 'L\'artisan n\'a pas encore soumis de devis.',
          style: const TextStyle(
            fontSize: 13,
            color: AppColors.textSecondary,
            height: 1.4,
          ),
        ),
        if (isArtisan) ...[
          const SizedBox(height: 14),
          ElevatedButton.icon(
            onPressed: () => openDevisCreation(mission),
            icon: const Icon(Icons.receipt_long_outlined, size: 18),
            label: const Text('Creer le devis'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.accent,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ],
    );
  }
}

class _DevisRecap extends StatelessWidget {
  const _DevisRecap({required this.devis, required this.isArtisan});

  final DevisModel devis;
  final bool isArtisan;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            StatusPill(
              label: _devisLabel(devis.statut),
              color: _devisColor(devis.statut),
            ),
            StatusPill(
              label:
                  '${devis.lignes.length} ligne${devis.lignes.length > 1 ? 's' : ''}',
              color: AppColors.textSecondary,
            ),
            StatusPill(
              label:
                  '${devis.jalons.length} jalon${devis.jalons.length > 1 ? 's' : ''}',
              color: AppColors.primary,
            ),
          ],
        ),
        const SizedBox(height: 14),
        TrackingInfoRow(
          label: 'Total general',
          value: Formatters.fcfa(devis.totalGeneral),
        ),
        TrackingInfoRow(
          label: 'Main d\'oeuvre',
          value: Formatters.fcfa(devis.totalMo),
        ),
        TrackingInfoRow(
          label: 'Materiaux',
          value: Formatters.fcfa(devis.totalMat),
        ),
        if (devis.ratioMateriaux != null)
          TrackingInfoRow(
            label: 'Ratio materiaux',
            value: '${(devis.ratioMateriaux! * 100).round()}%',
          ),
        const SizedBox(height: 10),
        Text(
          isArtisan
              ? devis.statut == 'soumis'
                  ? 'Votre devis a ete transmis. Il sera finance apres acceptation client.'
                  : 'Le devis de cette mission a deja ete enregistre.'
              : devis.statut == 'soumis'
                  ? 'Ouvrez le detail pour accepter ou refuser ce devis.'
                  : 'Le devis a deja ete traite pour cette mission.',
          style: const TextStyle(
            fontSize: 12,
            color: AppColors.textSecondary,
            height: 1.35,
          ),
        ),
        if (!isArtisan && devis.statut == 'soumis') ...[
          const SizedBox(height: 14),
          ElevatedButton.icon(
            onPressed: () =>
                Get.toNamed(Routes.devisReview, arguments: devis.id),
            icon: const Icon(Icons.visibility_outlined, size: 18),
            label: const Text('Voir le devis'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ],
    );
  }

  String _devisLabel(String statut) {
    switch (statut) {
      case 'soumis':
        return 'Soumis';
      case 'accepte':
        return 'Accepte';
      case 'refuse':
        return 'Refuse';
      default:
        return 'Brouillon';
    }
  }

  Color _devisColor(String statut) {
    switch (statut) {
      case 'soumis':
        return AppColors.accent;
      case 'accepte':
        return AppColors.success;
      case 'refuse':
        return AppColors.danger;
      default:
        return AppColors.textSecondary;
    }
  }
}
