import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/modules/missions/controllers/devis_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;
  late File testAudioFile;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() async {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;

    testAudioFile = File('${Directory.systemTemp.path}/test_audio.m4a');
    await testAudioFile.writeAsBytes([0, 1, 2, 3]);
  });

  tearDown(() async {
    if (await testAudioFile.exists()) {
      await testAudioFile.delete();
    }
    await TestHelpers.cleanupTestData();
    await CacheStore.wipeAll();
  });

  group('DevisController (Missions) — Voice-to-Quote', () {
    testWidgets('parseVoiceQuote transcrit l\'audio et renseigne les lignes et jalons', (tester) async {
      adapter.on(
        'POST',
        '/missions/10/devis/voice-quote',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {
              'transcription': 'Pose de 2 prises et tableau électrique',
              'lignes': [
                {'type': 'mat', 'description': 'Prises étanches', 'montant': 15000},
                {'type': 'mo', 'description': 'Raccordement électrique', 'montant': 20000},
              ],
              'jalons': [
                {'ordre': 1, 'description': 'Livraison matériel', 'montant': 15000, 'date_cible': '2026-03-01'},
                {'ordre': 2, 'description': 'Fin raccordement', 'montant': 20000, 'date_cible': '2026-03-05'},
              ],
            },
          },
        ),
      );
      final controller = DevisController()..prepareDraftForMission(10);

      await runControllerAction(
        tester,
        () => controller.parseVoiceQuote(testAudioFile.path),
      );

      expect(controller.lignes, hasLength(2));
      expect(controller.totalMo, 20000);
      expect(controller.totalMat, 15000);
      expect(controller.totalGeneral, 35000);
      expect(controller.jalons, hasLength(2));
      expect(controller.voiceTranscription.value, 'Pose de 2 prises et tableau électrique');
    });

    testWidgets('parseVoiceQuote gère les erreurs API sans crasher', (tester) async {
      adapter.on(
        'POST',
        '/missions/10/devis/voice-quote',
        const CannedResponse(
          statusCode: 429,
          body: {
            'success': false,
            'message': 'Quota IA atteint',
          },
        ),
      );
      final controller = DevisController()..prepareDraftForMission(10);

      await runControllerAction(
        tester,
        () => controller.parseVoiceQuote(testAudioFile.path),
      );

      expect(controller.lignes, isEmpty);
      expect(controller.isVoiceLoading.value, isFalse);
    });
  });
}
