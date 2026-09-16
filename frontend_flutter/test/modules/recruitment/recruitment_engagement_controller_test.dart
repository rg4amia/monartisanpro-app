import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/models/recruitment_engagement_model.dart';
import 'package:frontend_flutter/modules/recruitment/controllers/recruitment_engagement_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

RecruitmentEngagementModel _engagement({
  int artisanId = 10,
  int recruiterId = 20,
}) =>
    RecruitmentEngagementModel(
      id: 5,
      offerId: 7,
      applicationId: 1,
      artisanId: artisanId,
      recruiterId: recruiterId,
      dailyRate: 15000,
      totalDays: 3,
      montantTotal: 45000,
      status: 'accepte',
    );

/// `isArtisan`/`isRecruiter` déterminent quelles actions (accepter/refuser vs
/// payer le séquestre) sont proposées sur l'écran d'engagement — une
/// confusion des deux rôles exposerait les actions du recruteur à l'artisan
/// ou inversement.
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

  group('RecruitmentEngagementController — identification du rôle', () {
    test('reconnaît l\'utilisateur connecté comme artisan de l\'engagement', () {
      StorageService.saveUserId(10);
      final controller = RecruitmentEngagementController()
        ..engagement.value = _engagement(artisanId: 10, recruiterId: 20);

      expect(controller.isArtisan, isTrue);
      expect(controller.isRecruiter, isFalse);
    });

    test('reconnaît l\'utilisateur connecté comme recruteur de l\'engagement', () {
      StorageService.saveUserId(20);
      final controller = RecruitmentEngagementController()
        ..engagement.value = _engagement(artisanId: 10, recruiterId: 20);

      expect(controller.isArtisan, isFalse);
      expect(controller.isRecruiter, isTrue);
    });

    test('un tiers étranger à l\'engagement n\'est ni artisan ni recruteur', () {
      StorageService.saveUserId(99);
      final controller = RecruitmentEngagementController()
        ..engagement.value = _engagement(artisanId: 10, recruiterId: 20);

      expect(controller.isArtisan, isFalse);
      expect(controller.isRecruiter, isFalse);
    });

    test('aucun engagement chargé ⇒ ni artisan ni recruteur', () {
      StorageService.saveUserId(10);
      final controller = RecruitmentEngagementController();

      expect(controller.isArtisan, isFalse);
      expect(controller.isRecruiter, isFalse);
    });
  });

  group('RecruitmentEngagementController.load', () {
    test('charge le détail de l\'engagement', () async {
      adapter.on(
        'GET',
        '/recruitment-engagements/5',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 5,
              'offer_id': 7,
              'application_id': 1,
              'artisan_id': 10,
              'recruiter_id': 20,
              'daily_rate': 15000,
              'total_days': 3,
              'montant_total': 45000,
              'status': 'accepte',
            },
          },
        ),
      );

      final controller = RecruitmentEngagementController()..engagementId = 5;
      await controller.load();

      expect(controller.engagement.value?.id, 5);
      expect(controller.engagement.value?.status, 'accepte');
      expect(controller.isLoading.value, isFalse);
    });
  });
}
