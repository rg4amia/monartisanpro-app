import 'dart:async';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';

import '../../core/theme/app_colors.dart';
import '../../data/models/communication_model.dart';

/// Lecteur partagé entre toutes les cartes vocales.
///
/// Un lecteur par carte ferait jouer plusieurs messages en même temps et
/// ouvrirait autant de sessions audio natives. Une seule instance garantit
/// qu'écouter un message interrompt le précédent.
class BroadcastAudioPlayer {
  BroadcastAudioPlayer._();

  static AudioPlayer? _player;

  /// Identifiant de la communication en cours de lecture, `null` si aucune.
  static final ValueNotifier<int?> playingId = ValueNotifier<int?>(null);

  /// Créé à la première lecture seulement : construire un lecteur natif au
  /// simple affichage d'un écran coûterait pour rien, et rendrait les tests
  /// de widget dépendants des canaux de plateforme.
  static AudioPlayer get instance => _player ??= AudioPlayer();

  static bool get isInitialised => _player != null;

  static Future<void> stop() async {
    if (_player != null) await _player!.stop();
    playingId.value = null;
  }
}

/// Carte d'un message vocal diffusé par l'administration.
///
/// Rien ne démarre tout seul : la durée et le poids sont annoncés, et
/// l'utilisateur décide. Sur un forfait mobile ivoirien, lancer un
/// téléchargement sans consentement se paie en francs CFA.
class VoiceBroadcastCard extends StatefulWidget {
  const VoiceBroadcastCard({super.key, required this.communication});

  final CommunicationModel communication;

  @override
  State<VoiceBroadcastCard> createState() => _VoiceBroadcastCardState();
}

class _VoiceBroadcastCardState extends State<VoiceBroadcastCard> {
  StreamSubscription<PlayerState>? _stateSubscription;
  bool _isLoading = false;
  bool _isPlaying = false;
  String? _error;

  @override
  void dispose() {
    unawaited(_stateSubscription?.cancel());

    // Le lecteur est partagé : on ne le libère pas, on arrête seulement la
    // lecture si c'est bien ce message qui joue.
    if (BroadcastAudioPlayer.playingId.value == widget.communication.id) {
      unawaited(BroadcastAudioPlayer.stop());
    }

    super.dispose();
  }

  Future<void> _toggle() async {
    final url = widget.communication.mediaUrl;
    if (url == null || url.isEmpty) return;

    final player = BroadcastAudioPlayer.instance;

    if (_isPlaying) {
      await player.pause();
      if (mounted) setState(() => _isPlaying = false);

      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      // Un autre message jouait : on repart de zéro sur celui-ci.
      if (BroadcastAudioPlayer.playingId.value != widget.communication.id) {
        await player.setUrl(url);
        BroadcastAudioPlayer.playingId.value = widget.communication.id;

        await _stateSubscription?.cancel();
        _stateSubscription = player.playerStateStream.listen((state) {
          if (!mounted) return;
          if (state.processingState == ProcessingState.completed) {
            setState(() => _isPlaying = false);
          }
        });
      }

      await player.play();
      if (mounted) setState(() => _isPlaying = true);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Lecture impossible. Vérifiez votre connexion.');
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  /// « 1:20 · 480 Ko », ou l'un des deux, ou rien si l'API ne dit rien.
  String? get _weightLabel {
    final parts = [
      widget.communication.formattedDuration,
      widget.communication.formattedSize,
    ].whereType<String>().toList();

    return parts.isEmpty ? null : parts.join('  ·  ');
  }

  @override
  Widget build(BuildContext context) {
    final comm = widget.communication;
    final weight = _weightLabel;

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
              _PlayButton(
                isLoading: _isLoading,
                isPlaying: _isPlaying,
                color: AppColors.primary,
                onTap: _toggle,
                semanticLabel: _isPlaying
                    ? 'Mettre en pause le message vocal'
                    : 'Écouter le message vocal',
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
                    if (weight != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        weight,
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),

          // Le texte reste lisible sans dépenser un octet de données : il
          // porte l'essentiel pour qui ne veut pas écouter.
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

class _PlayButton extends StatelessWidget {
  const _PlayButton({
    required this.isLoading,
    required this.isPlaying,
    required this.color,
    required this.onTap,
    required this.semanticLabel,
  });

  final bool isLoading;
  final bool isPlaying;
  final Color color;
  final VoidCallback onTap;
  final String semanticLabel;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: semanticLabel,
      child: InkWell(
        onTap: isLoading ? null : onTap,
        borderRadius: BorderRadius.circular(26),
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.10),
            shape: BoxShape.circle,
          ),
          child: isLoading
              ? Padding(
                  padding: const EdgeInsets.all(13),
                  child: CircularProgressIndicator(strokeWidth: 2, color: color),
                )
              : Icon(
                  isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded,
                  color: color,
                  size: 26,
                ),
        ),
      ),
    );
  }
}
