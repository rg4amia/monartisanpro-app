import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/payout_model.dart';
import 'package:frontend_flutter/data/repositories/payout_repository.dart';
import 'package:frontend_flutter/modules/orders/controllers/order_follow_up_controller.dart';
import 'package:frontend_flutter/modules/wallet/controllers/driver_cashout_controller.dart';
import 'package:get/get.dart';

import '../../helpers/test_helpers.dart';

class _FakePayoutRepository extends PayoutRepository {
  _FakePayoutRepository();

  int requested = 0;
  String? requestedMode;

  @override
  Future<({DriverCashoutStats stats, List<DriverCashoutModel> cashouts})>
      getDriverCashouts({bool forceRefresh = false}) async => (
            stats: DriverCashoutStats(
              availableBalance: 20000 - requested,
              commissionRate: 0.02,
            ),
            cashouts: <DriverCashoutModel>[],
          );

  @override
  Future<({DriverCashoutModel cashout, String message})> requestDriverCashout({
    required int montant,
    required String mode,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
  }) async {
    requested += montant;
    requestedMode = mode;

    return (
      cashout: DriverCashoutModel.fromJson({'montant_brut': montant}),
      message: 'Demande de retrait enregistrée.',
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(Get.reset);

  group('DriverCashoutController', () {
    test('valide le montant sur le solde retirable', () async {
      final controller =
          DriverCashoutController(repository: _FakePayoutRepository());
      await controller.load();

      expect(controller.validateAmount(null), isNotNull);
      expect(controller.validateAmount(100), contains('Minimum'));
      expect(controller.validateAmount(25000), contains('solde'));
      expect(controller.validateAmount(15000), isNull);
      expect(controller.netFor(10000), 9800);
    });

    test('transmet la demande et recharge le solde', () async {
      final repo = _FakePayoutRepository();
      final controller = DriverCashoutController(repository: repo);
      await controller.load();
      controller.mode.value = 'orange_money';

      final message = await controller.submit(amount: 5000);

      expect(message, 'Demande de retrait enregistrée.');
      expect(repo.requestedMode, 'orange_money');
      expect(controller.stats.value.availableBalance, 15000);
    });
  });

  group('OrderFollowUpController', () {
    test('une commande livrée dont la course reste à régler reste active', () {
      final controller = OrderFollowUpController()..asSupplier = false;
      controller.orders.value = [
        {
          'id': 1,
          'status': 'delivered',
          'delivery_fare': {'status': 'a_payer', 'total': 3500, 'due': 3500},
        },
        {
          'id': 2,
          'status': 'delivered',
          'delivery_fare': {'status': 'paye', 'total': 3000},
        },
        {'id': 3, 'status': 'driver_assigned'},
      ];

      expect(controller.activeOrders.map((o) => o['id']), [1, 3]);
      expect(controller.pastOrders.map((o) => o['id']), [2]);
    });
  });
}
