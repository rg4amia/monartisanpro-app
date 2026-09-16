import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/recruitment_application_model.dart';
import 'package:frontend_flutter/data/models/recruitment_offer_model.dart';
import 'package:frontend_flutter/modules/recruitment/controllers/recruitment_browse_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

RecruitmentOfferModel _offer({int id = 7}) => RecruitmentOfferModel(
      id: id,
      title: 'Pose carrelage villa Cocody',
      description: 'Chantier 3 jours',
      missionType: 'journalier',
      commune: 'Cocody',
      status: 'active',
    );

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

  group('RecruitmentBrowseController.hasAppliedTo', () {
    test('détecte une candidature déjà déposée sur une offre', () {
      final controller = RecruitmentBrowseController()
        ..myApplications.value = [
          const RecruitmentApplicationModel(id: 1, offerId: 42, status: 'en_attente'),
        ];

      expect(controller.hasAppliedTo(42), isTrue);
      expect(controller.hasAppliedTo(99), isFalse);
    });

    test('aucune candidature ⇒ jamais postulé', () {
      final controller = RecruitmentBrowseController();
      expect(controller.hasAppliedTo(1), isFalse);
    });
  });

  group('RecruitmentBrowseController.load', () {
    test('charge les offres actives, candidatures et engagements en parallèle', () async {
      adapter
        ..on(
          'GET',
          '/recruitment-offers',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'data': [
                  {
                    'id': 7,
                    'title': 'Pose carrelage villa Cocody',
                    'description': 'Chantier 3 jours',
                    'mission_type': 'journalier',
                    'commune': 'Cocody',
                    'status': 'active',
                  },
                ],
              },
            },
          ),
        )
        ..on(
          'GET',
          '/recruitment-applications/mine',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'data': [
                  {'id': 1, 'offer_id': 7, 'status': 'acceptee'},
                ],
              },
            },
          ),
        )
        ..on(
          'GET',
          '/recruitment-engagements/mine',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {'data': []},
            },
          ),
        );

      final controller = RecruitmentBrowseController();
      await controller.load();

      expect(controller.offers, hasLength(1));
      expect(controller.offers.first.title, 'Pose carrelage villa Cocody');
      expect(controller.myApplications, hasLength(1));
      expect(controller.hasAppliedTo(7), isTrue);
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('RecruitmentBrowseController.apply', () {
    testWidgets('ignore une nouvelle candidature si déjà postulé', (tester) async {
      final controller = RecruitmentBrowseController()
        ..myApplications.value = [
          const RecruitmentApplicationModel(id: 1, offerId: 7, status: 'en_attente'),
        ];

      await runControllerAction(tester, () => controller.apply(_offer()));

      expect(adapter.requests, isEmpty);
      expect(controller.applyingOfferId.value, isNull);
    });

    testWidgets('envoie la candidature puis recharge la liste en cas de succès', (tester) async {
      adapter
        ..on('POST', '/recruitment-offers/7/apply', const CannedResponse(statusCode: 200, body: {}))
        ..on(
          'GET',
          '/recruitment-offers',
          const CannedResponse(statusCode: 200, body: {'data': {'data': []}}),
        )
        ..on(
          'GET',
          '/recruitment-applications/mine',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'data': [
                  {'id': 1, 'offer_id': 7, 'status': 'en_attente'},
                ],
              },
            },
          ),
        )
        ..on(
          'GET',
          '/recruitment-engagements/mine',
          const CannedResponse(statusCode: 200, body: {'data': {'data': []}}),
        );

      final controller = RecruitmentBrowseController();

      await runControllerAction(tester, () => controller.apply(_offer()));

      expect(controller.hasAppliedTo(7), isTrue);
      expect(controller.applyingOfferId.value, isNull);
    });

    testWidgets('une panne réseau ne laisse pas applyingOfferId bloqué', (tester) async {
      // Aucune route enregistrée ⇒ connectionError simulée.
      final controller = RecruitmentBrowseController();

      await runControllerAction(tester, () => controller.apply(_offer()));

      expect(controller.applyingOfferId.value, isNull);
    });
  });
}
