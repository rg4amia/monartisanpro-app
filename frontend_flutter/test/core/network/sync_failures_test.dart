import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:frontend_flutter/data/repositories/order_repository.dart';
import 'package:frontend_flutter/shared/widgets/offline_banner.dart';
import 'package:get/get.dart' hide Response;

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Refus du serveur sur une validation de code (Chantier 33).
///
/// Trois défauts : un code faux s'affichait « Vérifiez votre connexion » ; une
/// validation mise en file hors connexion puis refusée au rejeu disparaissait
/// sans un mot ; un bon code rejoué pendant une suspension était abandonné.

DioException _refusal(int status, {Object? body, Map<String, List<String>>? headers}) {
  final options = RequestOptions(path: '/orders/7/verify-delivery');

  return DioException(
    requestOptions: options,
    type: DioExceptionType.badResponse,
    response: Response(
      requestOptions: options,
      statusCode: status,
      data: body,
      headers: Headers.fromMap(headers ?? const {}),
    ),
  );
}

QueuedRequest _queued(String url) => QueuedRequest(
      id: 'req-1',
      method: 'POST',
      url: url,
      data: const {'code': '7390'},
      timestamp: DateTime.parse('2026-10-05T10:00:00Z'),
    );

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('rejeu d\'une requête en file', () {
    test('une saisie suspendue (429) reste en file, un code faux (400) en sort', () {
      expect(classifyReplayError(_refusal(429)), ReplayOutcome.retryLater);
      expect(classifyReplayError(_refusal(400)), ReplayOutcome.rejected);
      expect(classifyReplayError(_refusal(403)), ReplayOutcome.rejected);
      expect(classifyReplayError(_refusal(422)), ReplayOutcome.rejected);
      expect(classifyReplayError(_refusal(503)), ReplayOutcome.retryLater);
    });

    test('le délai annoncé par le serveur est suivi, dans des bornes', () {
      expect(
        retryDelayOf(_refusal(429, headers: {'retry-after': ['300']})),
        const Duration(minutes: 5),
      );
      // À défaut d'en-tête, la valeur du corps de la réponse.
      expect(
        retryDelayOf(_refusal(429, body: {'retry_after': 1800})),
        const Duration(minutes: 30),
      );
      expect(
        retryDelayOf(_refusal(429, headers: {'retry-after': ['2']})),
        const Duration(seconds: 30),
      );
      expect(
        retryDelayOf(_refusal(429, headers: {'retry-after': ['86400']})),
        const Duration(hours: 1),
      );
      expect(retryDelayOf(_refusal(429)), isNull);
      expect(retryDelayOf(_refusal(429, body: 'illisible')), isNull);
    });

    test('le motif du refus est celui du serveur, sous ses deux clés', () {
      expect(
        rejectionReasonOf(_refusal(400, body: {'message': 'Le code de réception de livraison est incorrect.'})),
        'Le code de réception de livraison est incorrect.',
      );
      expect(
        rejectionReasonOf(_refusal(400, body: {'error': 'Non autorisé à valider cette livraison.'})),
        'Non autorisé à valider cette livraison.',
      );
      expect(rejectionReasonOf(_refusal(400)), 'Le serveur a refusé cette action.');
      expect(rejectionReasonOf(_refusal(400, body: [1, 2])), 'Le serveur a refusé cette action.');
    });

    test('l\'action en file est nommée pour l\'utilisateur', () {
      expect(
        describeQueuedAction('/orders/42/verify-pickup'),
        'Validation du retrait de la commande #42',
      );
      expect(
        describeQueuedAction('/orders/42/verify-delivery'),
        'Validation de la livraison de la commande #42',
      );
      expect(
        describeQueuedAction('/jalons/12/photos'),
        "Envoi des photos d'une étape de chantier",
      );
      expect(describeQueuedAction('/autre'), 'Action enregistrée hors connexion');
    });
  });

  group('actions non abouties', () {
    test('une action refusée est consignée, puis retirée une fois lue', () async {
      final service = SyncService();

      await service.recordFailure(
        _queued('/orders/7/verify-delivery'),
        'Le code de réception de livraison est incorrect.',
      );

      expect(service.failures, hasLength(1));
      expect(service.failures.first.label, 'Validation de la livraison de la commande #7');

      await service.dismissFailure('req-1');
      expect(service.failures, isEmpty);
    });

    test('une trace relue du stockage local survit, une trace abîmée est ignorée', () {
      final original = SyncFailure(
        id: 'req-1',
        label: 'Validation du retrait de la commande #7',
        reason: 'Le code de retrait ou de prise en charge est incorrect.',
        at: DateTime.parse('2026-10-05T10:00:00Z'),
      );

      // Hive rend une Map<dynamic, dynamic>.
      final restored = SyncFailure.tryParse(Map<dynamic, dynamic>.from(original.toJson()));

      expect(restored?.id, 'req-1');
      expect(restored?.reason, original.reason);
      expect(restored?.at, original.at);
      expect(SyncFailure.tryParse({'id': 'req-2'}), isNull);
      expect(SyncFailure.tryParse('illisible'), isNull);
    });
  });

  group('bandeau', () {
    tearDown(Get.reset);

    testWidgets('annonce l\'action non aboutie avec son motif, jusqu\'à « Compris »', (tester) async {
      final service = Get.put(SyncService());
      await service.recordFailure(
        _queued('/orders/7/verify-delivery'),
        'Le code de réception de livraison est incorrect.',
      );
      await service.recordFailure(
        QueuedRequest(
          id: 'req-2',
          method: 'POST',
          url: '/orders/8/verify-pickup',
          timestamp: DateTime.parse('2026-10-05T10:05:00Z'),
        ),
        'Le code de retrait ou de prise en charge est incorrect.',
      );

      await tester.pumpWidget(const MaterialApp(home: Scaffold(body: OfflineBanner())));

      expect(find.text('Validation de la livraison de la commande #7 : non aboutie'), findsOneWidget);
      expect(find.textContaining('Le code de réception de livraison est incorrect.'), findsOneWidget);
      expect(find.text('1 autre action non aboutie.'), findsOneWidget);

      await tester.tap(find.text('Compris'));
      await tester.pumpAndSettle();

      expect(find.text('Validation du retrait de la commande #8 : non aboutie'), findsOneWidget);
      expect(find.textContaining('autre action'), findsNothing);

      await tester.tap(find.text('Compris'));
      await tester.pumpAndSettle();

      expect(find.textContaining('non aboutie'), findsNothing);
    });

    testWidgets('n\'affiche rien quand tout a abouti', (tester) async {
      Get.put(SyncService());

      await tester.pumpWidget(const MaterialApp(home: Scaffold(body: OfflineBanner())));

      expect(find.textContaining('non aboutie'), findsNothing);
      expect(find.textContaining('Hors-ligne'), findsNothing);
    });
  });

  group('validation d\'un code refusée par le serveur', () {
    late FakeHttpClientAdapter adapter;
    late OrderRepository repository;

    setUpAll(() async {
      await TestHelpers.initializeTestEnvironment();
    });

    setUp(() {
      adapter = FakeHttpClientAdapter();
      ApiClient().dio.httpClientAdapter = adapter;
      repository = OrderRepository();
    });

    tearDown(() async {
      await TestHelpers.cleanupTestData();
    });

    test('un code faux rend le message du serveur, pas une panne de connexion', () async {
      adapter.on(
        'POST',
        '/orders/7/verify-delivery',
        const CannedResponse(
          statusCode: 400,
          body: {'success': false, 'message': 'Le code de réception de livraison est incorrect.'},
        ),
      );

      final res = await repository.verifyDelivery(7, '0000');

      expect(res['success'], isFalse);
      expect(res['queued'], isNull);
      expect(res['message'], 'Le code de réception de livraison est incorrect.');
    });

    test('une saisie suspendue rend le message de suspension', () async {
      adapter.on(
        'POST',
        '/orders/7/verify-pickup',
        const CannedResponse(
          statusCode: 429,
          body: {
            'success': false,
            'message': 'Trop de codes incorrects : la validation est suspendue pendant 5 minutes.',
            'retry_after': 300,
          },
        ),
      );

      final res = await repository.verifyPickup(7, '4821');

      expect(res['success'], isFalse);
      expect(res['status'], 429);
      expect(res['message'], contains('suspendue pendant 5 minutes'));
    });

    test('une erreur du serveur reste une exception', () async {
      adapter.on(
        'POST',
        '/orders/7/verify-delivery',
        const CannedResponse(statusCode: 500, body: {'message': 'Erreur interne'}),
      );

      expect(() => repository.verifyDelivery(7, '7390'), throwsA(isA<DioException>()));
    });
  });
}
