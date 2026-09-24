import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../controllers/recruitment_browse_controller.dart';

/// Durée maximale d'une note vocale de candidature (contrôlée aussi côté serveur).
const int kVoiceApplicationMaxSeconds = 20;

/// Feuille de candidature : l'artisan peut joindre une note vocale de
/// 20 secondes au plus, transcrite côté serveur pour le recruteur, ou
/// postuler sans note.
class VoiceApplicationSheet extends StatefulWidget {
  const VoiceApplicationSheet({
    required this.offer,
    required this.controller,
    super.key,
  });

  final RecruitmentOfferModel offer;
  final RecruitmentBrowseController controller;

  static Future<void> show(
    BuildContext context,
    RecruitmentOfferModel offer,
    RecruitmentBrowseController controller,
  ) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) =>
          VoiceApplicationSheet(offer: offer, controller: controller),
    );
  }

  @override
  State<VoiceApplicationSheet> createState() => _VoiceApplicationSheetState();
}

class _VoiceApplicationSheetState extends State<VoiceApplicationSheet> {
  final AudioRecorder _recorder = AudioRecorder();
  Timer? _timer;
  bool _isRecording = false;
  String? _recordedPath;
  int _seconds = 0;

  @override
  void dispose() {
    _timer?.cancel();
    _recorder.dispose();
    super.dispose();
  }

  Future<void> _startRecording() async {
    try {
      if (!await _recorder.hasPermission()) {
        Get.snackbar(
          'Microphone requis',
          'Autorisez l\'accès au micro pour enregistrer votre note vocale.',
        );
        return;
      }

      final dir = await getTemporaryDirectory();
      final path =
          '${dir.path}/candidature_${DateTime.now().millisecondsSinceEpoch}.m4a';
      await _recorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc),
        path: path,
      );

      setState(() {
        _isRecording = true;
        _recordedPath = null;
        _seconds = 0;
      });

      _timer?.cancel();
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        setState(() => _seconds++);
        if (_seconds >= kVoiceApplicationMaxSeconds) {
          _stopRecording();
        }
      });
    } catch (_) {
      Get.snackbar(
        'Enregistrement impossible',
        'Le micro n\'a pas pu être démarré. Vous pouvez postuler sans note.',
      );
    }
  }

  Future<void> _stopRecording() async {
    _timer?.cancel();
    try {
      final path = await _recorder.stop();
      setState(() {
        _isRecording = false;
        _recordedPath = _seconds > 0 ? path : null;
      });
    } catch (_) {
      setState(() => _isRecording = false);
    }
  }

  Future<void> _submit({required bool withVoiceNote}) async {
    final navigator = Navigator.of(context);
    if (_isRecording) await _stopRecording();
    final path = withVoiceNote ? _recordedPath : null;
    await widget.controller.apply(
      widget.offer,
      voiceNotePath: path,
      voiceNoteDuration:
          path == null ? null : _seconds.clamp(1, kVoiceApplicationMaxSeconds),
    );
    if (mounted) navigator.pop();
  }

  @override
  Widget build(BuildContext context) {
    final remaining = kVoiceApplicationMaxSeconds - _seconds;
    final hasRecording = _recordedPath != null && !_isRecording;

    return Container(
      padding: EdgeInsets.fromLTRB(
        20,
        20,
        20,
        20 + MediaQuery.of(context).viewInsets.bottom,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.offer.title,
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            const Text(
              'Présentez-vous en 20 secondes : votre métier, votre expérience, '
              'vos disponibilités. Ne donnez pas votre numéro : le recruteur vous '
              'contactera par la plateforme.',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 20),
            Center(
              child: GestureDetector(
                onTap: _isRecording ? _stopRecording : _startRecording,
                child: CircleAvatar(
                  radius: 36,
                  backgroundColor:
                      _isRecording ? AppColors.danger : AppColors.primary,
                  child: Icon(
                    _isRecording ? Icons.stop : Icons.mic,
                    color: Colors.white,
                    size: 32,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              _isRecording
                  ? 'Enregistrement… $remaining s restantes'
                  : hasRecording
                      ? 'Note enregistrée ($_seconds s). Touchez le micro pour recommencer.'
                      : 'Touchez le micro pour enregistrer (facultatif).',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 13),
            ),
            const SizedBox(height: 20),
            Obx(() {
              final applying =
                  widget.controller.applyingOfferId.value == widget.offer.id;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ElevatedButton(
                    onPressed: applying || !hasRecording
                        ? null
                        : () => _submit(withVoiceNote: true),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    child: const Text('Postuler avec la note vocale'),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed:
                        applying ? null : () => _submit(withVoiceNote: false),
                    child: const Text('Postuler sans note'),
                  ),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }
}
