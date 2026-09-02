import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/devis_model.dart';
import '../../../../data/models/mission_model.dart';
import 'section_container.dart';

/// Carte « Phase en cours » : barre de progression calculée à partir du
/// statut mission / devis, et description textuelle de la phase adaptée au
/// rôle (artisan ou client).
class WorkflowCard extends StatelessWidget {
  const WorkflowCard({
    required this.mission,
    required this.devis,
    required this.role,
    required this.hasReferentPendingValidation,
    super.key,
  });

  final MissionModel mission;
  final DevisModel? devis;
  final String role;
  final bool hasReferentPendingValidation;

  @override
  Widget build(BuildContext context) {
    final progress = _calculateProgress(mission, devis);
    final phase = _describePhase();

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Phase en cours',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Text(
                '$progress%',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress / 100,
              minHeight: 8,
              backgroundColor: AppColors.primary.withValues(alpha: 0.12),
              valueColor: const AlwaysStoppedAnimation(AppColors.primary),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            phase.title,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: phase.color,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            phase.body,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  int _calculateProgress(MissionModel mission, DevisModel? devis) {
    if (mission.status == 'terminee') return 100;
    if (mission.status == 'litige') return 78;
    if (mission.status == 'en_cours') return 72;
    if (mission.status == 'financee') return 48;
    if (devis != null && devis.statut == 'soumis') return 24;
    if (devis != null && devis.statut == 'accepte') return 48;
    return 12;
  }

  _PhaseDescription _describePhase() {
    final isArtisan = role == 'artisan';

    if (mission.status == 'litige') {
      return const _PhaseDescription(
        title: 'Mission en arbitrage',
        body:
            'Un litige a ete ouvert. Les fonds et les validations sont temporairement geles jusqu\'a decision.',
        color: AppColors.danger,
      );
    }

    if (mission.status == 'pending_artisan_acceptance') {
      return _PhaseDescription(
        title: isArtisan
            ? 'Nouvelle demande de devis reçue'
            : 'Demande transmise à l\'artisan',
        body: isArtisan
            ? 'Un client vous a sélectionné pour cette mission. Veuillez accepter ou refuser la demande.'
            : 'L\'artisan a été notifié. En attente de son acceptation pour créer le devis.',
        color: AppColors.accent,
      );
    }

    if (mission.status == 'en_attente') {
      if (devis == null) {
        return _PhaseDescription(
          title: isArtisan ? 'Devis a preparer' : 'Attente du devis artisan',
          body: isArtisan
              ? 'La demande client a ete recue. Vous devez envoyer un devis detaille pour lancer le sequestre.'
              : 'L\'artisan doit chiffrer les materiaux, la main d\'oeuvre et les jalons.',
          color: AppColors.accent,
        );
      }

      if (devis!.statut == 'soumis') {
        return _PhaseDescription(
          title: isArtisan
              ? 'Devis envoye, paiement en attente'
              : 'Devis recu, validation client requise',
          body: isArtisan
              ? 'Le client doit maintenant accepter le devis et financer la mission.'
              : 'Acceptez ou refusez le devis pour passer a la phase de sequestre.',
          color: AppColors.primary,
        );
      }
    }

    if (mission.status == 'financee') {
      return _PhaseDescription(
        title: 'Mission financee',
        body: mission.montantMateriaux > 0
            ? 'Le sequestre est en place. Generez le J-Code materiaux puis demarrez les travaux.'
            : 'Le sequestre est en place. Vous pouvez maintenant demarrer les travaux.',
        color: AppColors.primary,
      );
    }

    if (mission.status == 'en_cours' && hasReferentPendingValidation) {
      return const _PhaseDescription(
        title: 'Validation referent attendue',
        body:
            'Cette mission depasse le seuil de controle. Les jalons valides attendent la visite physique du referent.',
        color: AppColors.accent,
      );
    }

    if (mission.status == 'en_cours') {
      return _PhaseDescription(
        title: isArtisan
            ? 'Travaux en execution'
            : 'Mission en cours de realisation',
        body: isArtisan
            ? 'Soumettez les jalons avec photos geolocalisees. Le client recevra un OTP a chaque validation.'
            : 'Suivez l\'avancement des jalons et validez les OTP recus par SMS lorsque necessaire.',
        color: AppColors.success,
      );
    }

    return const _PhaseDescription(
      title: 'Mission terminee',
      body:
          'La mission est cloturee. Les validations et decaissements principaux ont ete traites.',
      color: AppColors.success,
    );
  }
}

class _PhaseDescription {
  const _PhaseDescription({
    required this.title,
    required this.body,
    required this.color,
  });

  final String title;
  final String body;
  final Color color;
}
