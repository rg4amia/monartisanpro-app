import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/modules/recruitment/controllers/recruitment_publish_controller.dart';
import 'package:frontend_flutter/modules/services/models/sector_model.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

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

  group('RecruitmentPublishController.loadMyOffers', () {
    test('charge les offres déjà publiées par le recruteur', () async {
      adapter.on(
        'GET',
        '/recruitment-offers/mine',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'data': [
                {
                  'id': 3,
                  'title': 'Coffrage villa Riviera',
                  'description': 'Coffrage complet',
                  'mission_type': 'forfait',
                  'commune': 'Riviera',
                  'status': 'active',
                },
              ],
            },
          },
        ),
      );

      final controller = RecruitmentPublishController();
      await controller.loadMyOffers();

      expect(controller.myOffers, hasLength(1));
      expect(controller.myOffers.first.title, 'Coffrage villa Riviera');
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('RecruitmentPublishController.loadSectors / selectSector', () {
    test('charge les secteurs puis les métiers du secteur sélectionné', () async {
      adapter
        ..on(
          'GET',
          '/sectors',
          const CannedResponse(
            statusCode: 200,
            body: {
              'success': true,
              'data': [
                {'id': 1, 'name': 'Bâtiment'},
              ],
            },
          ),
        )
        ..on(
          'GET',
          '/sectors/1/trades',
          const CannedResponse(
            statusCode: 200,
            body: {
              'success': true,
              'data': [
                {'id': 10, 'name': 'Maçon', 'sectorId': 1},
              ],
            },
          ),
        );

      final controller = RecruitmentPublishController();
      await controller.loadSectors();

      expect(controller.sectors, hasLength(1));
      expect(controller.sectors.first.name, 'Bâtiment');

      await controller.selectSector(controller.sectors.first);

      expect(controller.trades, hasLength(1));
      expect(controller.trades.first.name, 'Maçon');
      expect(controller.selectedSector.value?.name, 'Bâtiment');
      expect(controller.isLoadingTrades.value, isFalse);
    });

    test('changer de secteur réinitialise le métier déjà choisi', () async {
      adapter
        ..on(
          'GET',
          '/sectors/1/trades',
          const CannedResponse(
            statusCode: 200,
            body: {
              'success': true,
              'data': [
                {'id': 10, 'name': 'Maçon', 'sectorId': 1},
              ],
            },
          ),
        )
        ..on(
          'GET',
          '/sectors/2/trades',
          const CannedResponse(statusCode: 200, body: {'success': true, 'data': []}),
        );

      final controller = RecruitmentPublishController();
      await controller.selectSector(SectorModel(id: 1, name: 'Bâtiment'));
      controller.selectTrade(controller.trades.first);
      expect(controller.selectedTrade.value?.name, 'Maçon');

      await controller.selectSector(SectorModel(id: 2, name: 'Second œuvre'));

      expect(controller.selectedTrade.value, isNull);
      expect(controller.trades, isEmpty);
    });
  });
}
