import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/recruitment_application_model.dart';

void main() {
  group('RecruitmentApplicationModel — note vocale', () {
    test("lit l'URL, la transcription, la durée et le statut", () {
      final model = RecruitmentApplicationModel.fromJson({
        'id': 3,
        'offer_id': 7,
        'status': 'submitted',
        'voice_note_url':
            'https://api.test/recruitment/voice-notes/3/file?signature=x',
        'voice_transcription': "Maçon, dix ans d'expérience.",
        'voice_note_duration': '15',
        'voice_status': 'approved',
      });

      expect(model.voiceNoteUrl, contains('/voice-notes/3/file'));
      expect(model.voiceTranscription, "Maçon, dix ans d'expérience.");
      expect(model.voiceNoteDuration, 15);
      expect(model.voiceStatus, 'approved');
    });

    test('une candidature sans note vocale reste lisible', () {
      final model =
          RecruitmentApplicationModel.fromJson({'id': 4, 'offer_id': 7});

      expect(model.voiceNoteUrl, isNull);
      expect(model.voiceTranscription, isNull);
      expect(model.voiceNoteDuration, isNull);
      expect(model.voiceStatus, isNull);
      expect(model.status, 'submitted');
    });

    test("une note retenue (coordonnées détectées) n'expose rien", () {
      final model = RecruitmentApplicationModel.fromJson({
        'id': 5,
        'offer_id': 7,
        'voice_note_url': null,
        'voice_transcription': null,
        'voice_status': 'contact_detected',
      });

      expect(model.voiceNoteUrl, isNull);
      expect(model.voiceStatus, 'contact_detected');
    });
  });
}
