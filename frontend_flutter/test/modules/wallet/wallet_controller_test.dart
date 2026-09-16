import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/modules/wallet/controllers/wallet_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

/// `wallet_escrow_livreur` retombait sur une valeur inventée (2700 FCFA)
/// quand l'API ne renvoyait pas ce champ — un livreur sans aucun séquestre
/// voyait quand même un solde fictif affiché comme réel (Règle d'or 29).
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

  group('WalletController.fetchData', () {
    test('charge les soldes matériaux/MO et les transactions', () async {
      adapter
        ..on(
          'GET',
          '/wallets/balance',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {'wallet_materiaux': 150000, 'wallet_mo': 80000},
            },
          ),
        )
        ..on(
          'GET',
          '/transactions',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': [
                {
                  'id': 1,
                  'type': 'liberation_jalon',
                  'montant': 25000,
                  'wallet_source': 'wallet_mo',
                  'wallet_dest': 'mobile_money',
                  'provider': 'wave',
                  'statut': 'confirme',
                  'created_at': '2026-01-10T10:00:00Z',
                },
              ],
            },
          ),
        );

      final controller = WalletController();
      await controller.fetchData();

      expect(controller.walletMateriaux.value, 150000);
      expect(controller.walletMo.value, 80000);
      expect(controller.transactions.length, 1);
      expect(controller.transactions.first.montant, 25000);
      expect(controller.isLoading.value, isFalse);
    });

    test(
      'ne substitue jamais une valeur inventée au séquestre livreur absent',
      () async {
        adapter
          ..on(
            'GET',
            '/wallets/balance',
            const CannedResponse(
              statusCode: 200,
              body: {
                'data': {'wallet_materiaux': 0, 'wallet_mo': 0},
              },
            ),
          )
          ..on(
            'GET',
            '/transactions',
            const CannedResponse(statusCode: 200, body: {'data': []}),
          );

        final controller = WalletController();
        await controller.fetchData();

        expect(controller.walletEscrowLivreur.value, 0);
      },
    );

    test(
      'restitue le séquestre livreur réel quand l\'API le fournit',
      () async {
        adapter
          ..on(
            'GET',
            '/wallets/balance',
            const CannedResponse(
              statusCode: 200,
              body: {
                'data': {
                  'wallet_materiaux': 0,
                  'wallet_mo': 0,
                  'wallet_escrow_livreur': 42000,
                },
              },
            ),
          )
          ..on(
            'GET',
            '/transactions',
            const CannedResponse(statusCode: 200, body: {'data': []}),
          );

        final controller = WalletController();
        await controller.fetchData();

        expect(controller.walletEscrowLivreur.value, 42000);
      },
    );

    testWidgets(
      'une panne réseau laisse isLoading à false et alerte sans lever d\'exception',
      (tester) async {
        // Aucune route enregistrée sur l'adaptateur ⇒ connectionError simulée.
        final controller = WalletController();

        await runControllerAction(tester, controller.fetchData);

        expect(controller.isLoading.value, isFalse);
        expect(tester.takeException(), isNull);
      },
    );
  });
}
