import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../data/models/communication_model.dart';
import 'video_broadcast_card.dart';
import 'voice_broadcast_card.dart';

/// Messages vocaux et vidéos diffusés par l'administration au rôle courant.
///
/// Le même bloc sert les quatre espaces (client, artisan, fournisseur,
/// livreur) : le ciblage est décidé côté serveur, l'application affiche ce
/// qu'elle reçoit. Rien n'est rendu quand il n'y a rien à diffuser — un
/// en-tête de section suivi du vide se lirait comme une panne.
class BroadcastMediaSection extends StatelessWidget {
  const BroadcastMediaSection({
    super.key,
    required this.voice,
    required this.video,
    this.padding = EdgeInsets.zero,
  });

  final List<CommunicationModel> voice;
  final List<CommunicationModel> video;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    // Une publication dont le média a disparu n'est pas diffusée par l'API,
    // mais un cache Hive écrit avant cette règle peut encore en contenir.
    final playableVoice = voice.where((c) => c.hasMedia).toList();
    final playableVideo = video.where((c) => c.hasMedia).toList();

    if (playableVoice.isEmpty && playableVideo.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: padding,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (playableVoice.isNotEmpty) ...[
            const _SectionLabel(
              icon: Icons.graphic_eq_rounded,
              label: 'Messages vocaux',
            ),
            const SizedBox(height: 10),
            ...playableVoice.map(
              (c) => VoiceBroadcastCard(communication: c),
            ),
            const SizedBox(height: 14),
          ],
          if (playableVideo.isNotEmpty) ...[
            const _SectionLabel(
              icon: Icons.smart_display_outlined,
              label: 'Vidéos',
            ),
            const SizedBox(height: 10),
            ...playableVideo.map(
              (c) => VideoBroadcastCard(communication: c),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 18, color: AppColors.primary),
        const SizedBox(width: 8),
        Text(
          label,
          style: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 15,
            color: AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
