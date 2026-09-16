import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/recruitment_offer_model.dart';
import 'package:frontend_flutter/modules/recruitment/controllers/recruitment_applicants_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

RecruitmentOfferModel _offer({int id = 7}) => RecruitmentOfferModel(
      id: id,
      title: 'Pose carrelage villa Cocody',
      description: 'Chantier 3 jours',
      missionType: 'journalier',
      commune: 'Cocody',
      status: 'active',
    );

/// La liste des postulants d'une offre reste verrouillée tant que le
/// recruteur n'a pas payé le séquestre d'accès (HTTP 402) — ce test verrouille
/// cette distinction entre « verrouillé, paiement requis » et « vraie panne »,
/// pour qu'un recruteur non payeur voie l'écran de déverrouillage et non un
/// message d'erreur générique.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  group('RecruitmentApplicantsController.load', () {
    test('déverrouille la liste et la peuple quand le séquestre est payé', () async {
      adapter.on(
        'GET',
        '/recruitment-offers/7/applications',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {'id': 1, 'offer_id': 7, 'status': 'en_attente'},
            ],
          },
        ),
      );

      final controller = RecruitmentApplicantsController()..offer = _offer();
      await controller.load();

      expect(controller.applicantsUnlocked.value, isTrue);
      expect(controller.applications, hasLength(1));
      expect(controller.isLoading.value, isFalse);
    });

    test('une réponse 402 verrouille la liste sans la traiter comme une panne', () async {
      adapter.on(
        'GET',
        '/recruitment-offers/7/applications',
        const CannedResponse(statusCode: 402, body: {'message': 'Séquestre requis'}),
      );

      final controller = RecruitmentApplicantsController()..offer = _offer();
      await controller.load();

      expect(controller.applicantsUnlocked.value, isFalse);
      expect(controller.applications, isEmpty);
      expect(controller.isLoading.value, isFalse);
    });
  });
}
