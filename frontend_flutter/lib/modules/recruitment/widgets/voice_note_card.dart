import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:just_audio/just_audio.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_application_model.dart';

/// Note vocale d'un candidat, côté recruteur : écoute et transcription.
///
/// Le serveur ne fournit l'URL (signée, 15 min) et la transcription qu'une
/// fois la note validée sans coordonnées ; la carte ne s'affiche pas sinon.
class VoiceNoteCard extends StatefulWidget {
  const VoiceNoteCard({required this.application, super.key});

  final RecruitmentApplicationModel application;

  @override
  State<VoiceNoteCard> createState() => _VoiceNoteCardState();
}

class _VoiceNoteCardState extends State<VoiceNoteCard> {
  final AudioPlayer _player = AudioPlayer();
  bool _loading = false;

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  Future<void> _togglePlayback() async {
    final url = widget.application.voiceNoteUrl;
    if (url == null) return;

    if (_player.playing) {
      await _player.pause();
      return;
    }

    try {
      setState(() => _loading = true);
      if (_player.audioSource == null) {
        await _player.setUrl(url);
      }
      setState(() => _loading = false);
      await _player.play();
      if (_player.processingState == ProcessingState.completed) {
        await _player.seek(Duration.zero);
        await _player.pause();
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
      Get.snackbar(
        'Lecture impossible',
        'La note vocale n\'a pas pu être lue. Rouvrez la liste des candidats pour la recharger.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final application = widget.application;
    final transcription = application.voiceTranscription;
    final duration = application.voiceNoteDuration;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.primary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (application.voiceNoteUrl != null)
                StreamBuilder<bool>(
                  stream: _player.playingStream,
                  builder: (context, snapshot) {
                    final playing = snapshot.data ?? false;
                    return IconButton(
                      onPressed: _loading ? null : _togglePlayback,
                      icon: _loading
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Icon(
                              playing ? Icons.pause_circle : Icons.play_circle,
                              color: AppColors.primary,
                              size: 32,
                            ),
                      tooltip: playing ? 'Pause' : 'Écouter la note vocale',
                    );
                  },
                ),
              Expanded(
                child: Text(
                  duration != null
                      ? 'Note vocale du candidat ($duration s)'
                      : 'Note vocale du candidat',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          if (transcription != null && transcription.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              '« $transcription »',
              style: const TextStyle(
                fontSize: 12.5,
                fontStyle: FontStyle.italic,
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
