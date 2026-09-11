import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../controllers/devis_controller.dart';

class VoiceQuoteDialog extends StatefulWidget {
  const VoiceQuoteDialog({required this.controller, super.key});

  final DevisController controller;

  static Future<void> show(BuildContext context, DevisController controller) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => VoiceQuoteDialog(controller: controller),
    );
  }

  @override
  State<VoiceQuoteDialog> createState() => _VoiceQuoteDialogState();
}

class _VoiceQuoteDialogState extends State<VoiceQuoteDialog> {
  late final AudioRecorder _audioRecorder;
  bool _isRecording = false;
  String? _recordedFilePath;
  int _recordingDuration = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _audioRecorder = AudioRecorder();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _audioRecorder.dispose();
    super.dispose();
  }

  Future<void> _toggleRecording() async {
    if (_isRecording) {
      await _stopRecording();
    } else {
      await _startRecording();
    }
  }

  Future<void> _startRecording() async {
    try {
      if (await _audioRecorder.hasPermission()) {
        final tempDir = await getTemporaryDirectory();
        final path =
            '${tempDir.path}/quote_${DateTime.now().millisecondsSinceEpoch}.m4a';

        await _audioRecorder.start(
          const RecordConfig(encoder: AudioEncoder.aacLc),
          path: path,
        );

        setState(() {
          _isRecording = true;
          _recordedFilePath = null;
          _recordingDuration = 0;
        });

        _timer?.cancel();
        _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
          setState(() {
            _recordingDuration++;
          });
          // Limite max à 2 minutes de dictée
          if (_recordingDuration >= 120) {
            _stopRecording();
          }
        });
      } else {
        Get.snackbar(
          'Microphone requis',
          'Veuillez autoriser l\'accès au micro pour enregistrer.',
          snackPosition: SnackPosition.TOP,
          backgroundColor: const Color(0xFFFEE2E2),
          colorText: const Color(0xFF991B1B),
        );
      }
    } catch (e) {
      Get.snackbar(
        'Erreur Enregistrement',
        'Impossible de démarrer l\'enregistrement : $e',
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _stopRecording() async {
    _timer?.cancel();
    try {
      final path = await _audioRecorder.stop();
      setState(() {
        _isRecording = false;
        _recordedFilePath = path;
      });
    } catch (e) {
      setState(() {
        _isRecording = false;
      });
    }
  }

  Future<void> _submitVoiceQuote() async {
    if (_recordedFilePath == null) return;

    final success = await widget.controller.parseVoiceQuote(_recordedFilePath!);
    if (success && mounted) {
      Navigator.of(context).pop();
    }
  }

  String _formatDuration(int seconds) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Obx(() {
      final isLoading = widget.controller.isVoiceLoading.value;

      return Container(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 24,
          bottom: 24 + bottomInset,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Barre de tirage
            Center(
              child: Container(
                width: 48,
                height: 5,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Titre avec badge
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEEF2FF),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.mic,
                    color: Color(0xFF4F46E5),
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Dictée Vocale du Devis',
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF111827),
                        ),
                      ),
                      Text(
                        'Alimenté par Google Gemini',
                        style: TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6B7280),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Conseil d'élocution
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, size: 18, color: Color(0xFF6B7280)),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Dictez comme vous parlez sur le chantier : "Il me faut 3 sacs de ciment à 5 000, 2 coudes PVC à 1 500 et 20 000 pour 2 jours de travail."',
                      style: TextStyle(fontSize: 12, color: Color(0xFF4B5563)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Zone centrale d'enregistrement
            if (isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 28),
                child: Column(
                  children: [
                    CircularProgressIndicator(color: Color(0xFF4F46E5)),
                    SizedBox(height: 16),
                    Text(
                      'Gemini transcrit et structure votre devis...',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF374151),
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Extraction de la main d\'œuvre et des fournitures',
                      style: TextStyle(fontSize: 12, color: Color(0xFF9CA3AF)),
                    ),
                  ],
                ),
              )
            else
              Column(
                children: [
                  // Durée
                  Text(
                    _isRecording
                        ? _formatDuration(_recordingDuration)
                        : (_recordedFilePath != null
                            ? 'Prêt à analyser (${_formatDuration(_recordingDuration)})'
                            : 'Appuyez pour enregistrer'),
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: _isRecording ? Colors.red : const Color(0xFF374151),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Bouton micro
                  GestureDetector(
                    onTap: _toggleRecording,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: _isRecording
                            ? Colors.red
                            : const Color(0xFF4F46E5),
                        boxShadow: [
                          BoxShadow(
                            color: (_isRecording
                                    ? Colors.red
                                    : const Color(0xFF4F46E5))
                                .withValues(alpha: 0.35),
                            blurRadius: _isRecording ? 20 : 10,
                            spreadRadius: _isRecording ? 4 : 1,
                          ),
                        ],
                      ),
                      child: Icon(
                        _isRecording ? Icons.stop : Icons.mic,
                        color: Colors.white,
                        size: 38,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _isRecording
                        ? 'Appuyez pour terminer'
                        : (_recordedFilePath != null
                            ? 'Réenregistrer'
                            : 'Parlez distinctement'),
                    style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                  ),
                ],
              ),
            const SizedBox(height: 24),

            // Actions
            if (!isLoading && _recordedFilePath != null) ...[
              ElevatedButton.icon(
                onPressed: _submitVoiceQuote,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4F46E5),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: const Icon(Icons.auto_awesome, size: 20),
                label: const Text(
                  'Générer le Devis avec l\'IA',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(height: 8),
            ],

            TextButton(
              onPressed: isLoading ? null : () => Navigator.of(context).pop(),
              child: const Text(
                'Fermer',
                style: TextStyle(color: Color(0xFF6B7280)),
              ),
            ),
          ],
        ),
      );
    });
  }
}
