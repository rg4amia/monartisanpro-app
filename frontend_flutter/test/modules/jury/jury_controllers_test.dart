import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/jury_dossier_model.dart';
import 'package:frontend_flutter/modules/jury/controllers/jury_dossier_detail_controller.dart';
import 'package:frontend_flutter/modules/jury/controllers/jury_dossiers_controller.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Espace juré (Chantier 12) : le juré instruit des dossiers anonymisés
/// (`/jury/dossiers`), jamais la fiche litige des parties.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  const detailBody = {
    'success': true,
    'data': {
      'review': {
        'id': 3,
        'status': 'assigned',
        'compensation': 5000,
        'expires_at': '2026-09-28T10:00:00Z',
        'verdict': null,
      },
      'litige': {
        'id': 7,
        'intervention_type': 'Carrelage',
        'montant_mission': 150000,
        'motif': 'malfaçon',
        'description': 'Carreaux décollés.',
        'ouvert_par': 'client',
        'preuves': [
          {
            'id': 1,
            'partie': 'client',
            'media_url': null,
            'is_certified': true,
            'tampered': false,
          },
        ],
        'jalons_summary': [
          {'ordre': 1, 'description': 'Pose', 'montant': 100000},
        ],
      },
    },
  };

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
  });

  group('JuryDossiersController', () {
    test('lit la liste paginée et place les dossiers ouverts à part', () async {
      adapter.on(
        'GET',
        '/jury/dossiers',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'current_page': 1,
              'data': [
                {
                  'review_id': 1,
                  'litige_id': 7,
                  'status': 'assigned',
                  'compensation': 5000,
                  'dossier': {
                    'intervention_type': 'Carrelage',
                    'montant_mission': 150000,
                    'motif': 'malfaçon',
                    'preuves_count': 3,
                  },
                },
                {
                  'review_id': 2,
                  'litige_id': 8,
                  'status': 'voted',
                  'verdict': 'CONFORME',
                  'dossier': {},
                },
              ],
            },
          },
        ),
      );

      final controller = JuryDossiersController();
      await controller.load();

      expect(controller.errorMsg.value, isNull);
      expect(controller.openDossiers.single.litigeId, 7);
      expect(controller.openDossiers.single.preuvesCount, 3);
      expect(controller.closedDossiers.single.verdict, JuryVerdict.conforme);
    });

    test('une panne s\'affiche comme telle, jamais comme une liste vide',
        () async {
      adapter.on(
        'GET',
        '/jury/dossiers',
        const CannedResponse(statusCode: 403, body: {'message': 'Interdit'}),
      );

      final controller = JuryDossiersController();
      await controller.load();

      expect(controller.errorMsg.value, isNotNull);
      expect(controller.dossiers, isEmpty);
    });
  });

  group('JuryDossierDetailController', () {
    test('charge le dossier anonymisé depuis l\'identifiant de notification',
        () async {
      Get.routing.args = {'litigeId': 7};
      adapter.on(
        'GET',
        '/jury/dossiers/7',
        const CannedResponse(statusCode: 200, body: detailBody),
      );

      final controller = JuryDossierDetailController()..onInit();
      await controller.load();

      final dossier = controller.dossier.value!;
      expect(controller.litigeId, 7);
      expect(dossier.canVote, isTrue);
      expect(dossier.motif, 'malfaçon');
      expect(dossier.ouvertPar, 'client');
      expect(dossier.preuves.single.isCertified, isTrue);
      expect(dossier.jalons.single.montant, 100000);
      Get.routing.args = null;
    });

    test('exige un avis avant l\'envoi', () async {
      adapter.on(
        'GET',
        '/jury/dossiers/7',
        const CannedResponse(statusCode: 200, body: detailBody),
      );
      final controller = JuryDossierDetailController(litigeId: 7)..onInit();
      await controller.load();

      expect(await controller.submitVote(), isNull);
      expect(controller.voteError.value, contains('avis'));
      expect(
        adapter.requests.where((r) => r.method == 'POST'),
        isEmpty,
      );
    });

    test('n\'envoie la part de l\'artisan que pour un avis partagé', () async {
      adapter
        ..on(
          'GET',
          '/jury/dossiers/7',
          const CannedResponse(statusCode: 200, body: detailBody),
        )
        ..on(
          'POST',
          '/jury/dossiers/7/vote',
          const CannedResponse(
            statusCode: 200,
            body: {'success': true, 'message': 'Avis enregistré.'},
          ),
        );
      final controller = JuryDossierDetailController(litigeId: 7)..onInit();
      await controller.load();

      controller.verdict.value = JuryVerdict.conforme;
      controller.splitArtisanPercentage.value = 30;
      expect(await controller.submitVote(), 'Avis enregistré.');
      var sent = adapter.requests.lastWhere((r) => r.method == 'POST').data;
      expect(sent, {'verdict': 'CONFORME'});

      controller.verdict.value = JuryVerdict.partage;
      controller.technicalComment.value = '  Support mal préparé. ';
      await controller.submitVote();
      sent = adapter.requests.lastWhere((r) => r.method == 'POST').data;
      expect(jsonDecode(jsonEncode(sent)), {
        'verdict': 'RESPONSABILITE_PARTAGEE',
        'split_artisan_percentage': 30,
        'technical_comment': 'Support mal préparé.',
      });
    });

    test('remonte le refus du serveur (délai dépassé)', () async {
      adapter
        ..on(
          'GET',
          '/jury/dossiers/7',
          const CannedResponse(statusCode: 200, body: detailBody),
        )
        ..on(
          'POST',
          '/jury/dossiers/7/vote',
          const CannedResponse(
            statusCode: 422,
            body: {'message': 'Votre délai imparti de 48h a expiré.'},
          ),
        );
      final controller = JuryDossierDetailController(litigeId: 7)..onInit();
      await controller.load();
      controller.verdict.value = JuryVerdict.nonConforme;

      expect(await controller.submitVote(), isNull);
      expect(controller.voteError.value, contains('48h'));
    });
  });

  test('libellés français des statuts et verdicts', () {
    expect(juryReviewStatusLabel('assigned'), 'À instruire');
    expect(juryReviewStatusLabel('expired'), 'Délai dépassé');
    expect(
      JuryVerdict.label('RESPONSABILITE_PARTAGEE'),
      'Responsabilité partagée',
    );
  });
}
