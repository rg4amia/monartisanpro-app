import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_colors.dart';
import '../../data/models/communication_model.dart';

/// Carte d'une vidéo diffusée par l'administration.
///
/// La vidéo est hébergée à l'extérieur (YouTube et consorts) : elle s'ouvre
/// donc dans l'application dédiée ou le navigateur, et non dans un lecteur
/// embarqué. Trois raisons : `video_player` ne sait pas lire une URL YouTube ;
/// l'hébergeur adapte la qualité au réseau, ce qu'un lecteur maison ne fait
/// pas ; et les réglages d'économie de données de l'utilisateur s'appliquent.
///
/// Rien ne se lance tout seul, et la durée est annoncée avant l'ouverture.
class VideoBroadcastCard extends StatefulWidget {
  const VideoBroadcastCard({
    super.key,
    required this.communication,
    this.launcher,
  });

  final CommunicationModel communication;

  /// Injectable pour les tests : évite d'atteindre le canal de plateforme.
  final Future<bool> Function(Uri url)? launcher;

  @override
  State<VideoBroadcastCard> createState() => _VideoBroadcastCardState();
}

class _VideoBroadcastCardState extends State<VideoBroadcastCard> {
  bool _isOpening = false;
  String? _error;

  Future<void> _open() async {
    final raw = widget.communication.mediaUrl;
    if (raw == null || raw.isEmpty) return;

    final uri = Uri.tryParse(raw);
    if (uri == null) {
      setState(() => _error = 'Lien vidéo invalide.');

      return;
    }

    setState(() {
      _isOpening = true;
      _error = null;
    });

    try {
      final launch = widget.launcher ??
          (Uri url) => launchUrl(url, mode: LaunchMode.externalApplication);

      final opened = await launch(uri);

      if (mounted && !opened) {
        setState(
            () => _error = 'Aucune application ne peut ouvrir cette vidéo.',);
      }
    } catch (_) {
      if (mounted) {
        setState(
            () => _error = 'Ouverture impossible. Vérifiez votre connexion.',);
      }
    } finally {
      if (mounted) setState(() => _isOpening = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final comm = widget.communication;
    final duration = comm.formattedDuration;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.play_circle_outline_rounded,
                  color: AppColors.danger,
                  size: 26,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      comm.titre,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14.5,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      duration == null
                          ? 'Vidéo · s\'ouvre hors de l\'application'
                          : 'Vidéo · $duration · s\'ouvre hors de l\'application',
                      style: const TextStyle(
                        color: AppColors.textMuted,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (comm.contenu.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              comm.contenu,
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12.5,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _isOpening ? null : _open,
              icon: _isOpening
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.open_in_new_rounded, size: 18),
              label: const Text('Regarder la vidéo'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primary,
                side: const BorderSide(color: AppColors.border),
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(
              _error!,
              style: const TextStyle(color: AppColors.danger, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }
}
