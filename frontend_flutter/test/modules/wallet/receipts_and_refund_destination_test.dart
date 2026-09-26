import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/payments/receipt_opener.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/models/payout_model.dart';
import 'package:frontend_flutter/data/models/transaction_model.dart';
import 'package:frontend_flutter/data/repositories/payout_repository.dart';
import 'package:frontend_flutter/modules/litige/controllers/litige_detail_controller.dart';
import 'package:frontend_flutter/modules/wallet/controllers/driver_cashout_controller.dart';
import 'package:frontend_flutter/modules/wallet/controllers/wallet_controller.dart';
import 'package:frontend_flutter/shared/widgets/refund_destination_dialog.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

/// Chantier 11 côté mobile : reçus PDF (transactions, retraits livreur) et
/// moyen de remboursement choisi par le client après litige.
class _FakePayoutRepository extends PayoutRepository {
  ({int id, String provider, String phone})? updated;

  @override
  Future<List<PayoutModel>> getPayouts({bool forceRefresh = false}) async =>
      const [];

  @override
  Future<({PayoutModel payout, String message})> updateDestination(
    int id, {
    required String provider,
    required String phone,
  }) async {
    updated = (id: id, provider: provider, phone: phone);

    return (
      payout: PayoutModel.fromJson({'id': id}),
      message: 'Moyen de remboursement enregistré.',
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    Get.reset();
    Get.routing.args = null;
    await PayoutRepository.clearCache();
    await TestHelpers.cleanupTestData();
  });

  group('Modèles', () {
    test('un remboursement non abouti permet de changer de destination', () {
      final refund = PayoutModel.fromJson({
        'id': 4,
        'context': 'remboursement_client',
        'statut': 'echoue',
      });
      final paid = PayoutModel.fromJson({
        'context': 'remboursement_client',
        'statut': 'verse',
      });
      final jalon = PayoutModel.fromJson({
        'context': 'jalon',
        'statut': 'echoue',
      });

      expect(refund.canChangeDestination, isTrue);
      expect(paid.canChangeDestination, isFalse);
      expect(jalon.canChangeDestination, isFalse);
    });

    test('le reçu n\'est proposé que si le serveur le déclare disponible', () {
      expect(TransactionModel.fromJson({'id': 1}).receiptAvailable, isFalse);
      expect(
        TransactionModel.fromJson({'id': 1, 'receiptAvailable': true})
            .receiptAvailable,
        isTrue,
      );

      final cashout = DriverCashoutModel.fromJson({
        'id': 2,
        'transaction_id': 55,
        'receipt_available': true,
      });
      expect(cashout.transactionId, 55);
      expect(cashout.receiptAvailable, isTrue);
    });
  });

  group('ReceiptOpener', () {
    test('ouvre le lien signé délivré par le serveur', () async {
      Uri? opened;
      final opener = ReceiptOpener(
        fetchLink: (id) async => 'https://api.test/receipts/$id?signature=x',
        openUrl: (uri) async {
          opened = uri;
          return true;
        },
      );

      expect(await opener.open(9), isNull);
      expect(opened.toString(), 'https://api.test/receipts/9?signature=x');
    });

    test('renvoie un message si le reçu ne peut pas s\'ouvrir', () async {
      final unreachable = ReceiptOpener(
        fetchLink: (_) async => throw const FormatException('absent'),
        openUrl: (_) async => true,
      );
      final noBrowser = ReceiptOpener(
        fetchLink: (_) async => 'https://api.test/r',
        openUrl: (_) async => false,
      );

      expect(await unreachable.open(1), isNotNull);
      expect(await noBrowser.open(1), contains('Impossible'));
    });
  });

  group('Contrôleurs', () {
    test('le portefeuille enregistre le moyen de remboursement choisi',
        () async {
      final repo = _FakePayoutRepository();
      final controller = WalletController(payoutRepository: repo);
      final refund = PayoutModel.fromJson({
        'id': 4,
        'context': 'remboursement_client',
        'statut': 'echoue',
      });

      final message = await controller.changePayoutDestination(
        refund,
        provider: 'orange_money',
        phone: '+2250701020304',
      );

      expect(message, 'Moyen de remboursement enregistré.');
      expect(
        repo.updated,
        (id: 4, provider: 'orange_money', phone: '+2250701020304'),
      );
      expect(controller.updatingDestinationPayoutId.value, isNull);
    });

    test('le retrait livreur n\'ouvre le reçu qu\'une fois versé', () async {
      final openedIds = <int>[];
      final controller = DriverCashoutController(
        repository: _FakePayoutRepository(),
        receiptOpener: ReceiptOpener(
          fetchLink: (id) async {
            openedIds.add(id);
            return 'https://api.test/r';
          },
          openUrl: (_) async => true,
        ),
      );

      final pending = DriverCashoutModel.fromJson({'id': 1});
      final paid = DriverCashoutModel.fromJson({
        'id': 2,
        'transaction_id': 55,
        'receipt_available': true,
      });

      expect(await controller.openReceipt(pending), contains('versé'));
      expect(await controller.openReceipt(paid), isNull);
      expect(openedIds, [55]);
    });
  });

  group('Litige — moyen de remboursement', () {
    late FakeHttpClientAdapter adapter;

    setUp(() {
      adapter = FakeHttpClientAdapter();
      ApiClient().dio.httpClientAdapter = adapter;
    });

    CannedResponse litigeFor(int clientId) => CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 5,
              'statut': 'ouvert',
              'parties': {
                'client': {'id': clientId},
              },
              'refundDestination': clientId == 10
                  ? {'provider': 'wave', 'phone': '+2250701020304'}
                  : null,
            },
          },
        );

    test('seul le client de la mission choisit le remboursement', () async {
      StorageService.saveUserId(10);
      adapter.on('GET', '/litiges/5', litigeFor(10));
      final controller = LitigeDetailController()..litigeId = 5;
      await controller.loadLitige();
      expect(controller.isClient, isTrue);
      expect(controller.refundDestination?['provider'], 'wave');

      adapter.on('GET', '/litiges/5', litigeFor(99));
      await controller.loadLitige();
      expect(controller.isClient, isFalse);
    });

    test('enregistre la préférence puis recharge le litige', () async {
      StorageService.saveUserId(10);
      adapter
        ..on('GET', '/litiges/5', litigeFor(10))
        ..on(
          'PUT',
          '/litiges/5/refund-destination',
          const CannedResponse(
            statusCode: 200,
            body: {
              'success': true,
              'message': 'Moyen de remboursement enregistré.',
            },
          ),
        );
      final controller = LitigeDetailController()..litigeId = 5;

      final message = await controller.saveRefundDestination(
        provider: 'orange_money',
        phone: '+2250705060708',
      );

      expect(message, 'Moyen de remboursement enregistré.');
      final put = adapter.requests.firstWhere((r) => r.method == 'PUT');
      expect(put.data, {'provider': 'orange_money', 'phone': '+2250705060708'});
      expect(adapter.requests.last.method, 'GET');
    });
  });

  group('Saisie du moyen de remboursement', () {
    test('normalise un numéro ivoirien au format du serveur', () {
      expect(normalizeIvorianPhone('07 01 02 03 04'), '+2250701020304');
      expect(normalizeIvorianPhone('+225 0701020304'), '+2250701020304');
      expect(normalizeIvorianPhone('0701'), isNull);
    });

    testWidgets('renvoie l\'opérateur et le numéro choisis', (tester) async {
      RefundDestination? result;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => TextButton(
              onPressed: () async =>
                  result = await showRefundDestinationDialog(context),
              child: const Text('ouvrir'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('ouvrir'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Enregistrer'));
      await tester.pump();
      expect(find.text('Saisissez un numéro à 10 chiffres.'), findsOneWidget);

      await tester.tap(find.text('Orange Money'));
      await tester.enterText(find.byType(TextField), '0705060708');
      await tester.tap(find.text('Enregistrer'));
      await tester.pumpAndSettle();

      expect(result, (provider: 'orange_money', phone: '+2250705060708'));
    });
  });
}
