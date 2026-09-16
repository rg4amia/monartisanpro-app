import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/repositories/order_repository.dart';
import 'package:frontend_flutter/modules/orders/controllers/order_follow_up_controller.dart';
import 'package:frontend_flutter/modules/orders/widgets/order_status_badge.dart';

/// Dépôt simulé : on veut éprouver le tri et la gestion d'erreur du
/// contrôleur, pas la couche réseau.
class _FakeOrderRepository extends OrderRepository {
  _FakeOrderRepository(
      {this.supplierOrders = const [], this.shouldFail = false,});

  final List<Map<String, dynamic>> supplierOrders;
  final bool shouldFail;
  int revealCalls = 0;

  @override
  Future<List<Map<String, dynamic>>> getSupplierOrders() async {
    if (shouldFail) throw Exception('réseau indisponible');

    return supplierOrders;
  }

  @override
  Future<String?> fetchVerificationCode(int orderId) async {
    revealCalls++;

    return codeToReturn;
  }

  String? codeToReturn = 'LIVREUR-4821';
  final validated = <String>[];

  @override
  Future<Map<String, dynamic>> verifyPickup(int orderId, String code) async {
    validated.add('pickup:$orderId:$code');

    return {'success': true};
  }

  @override
  Future<Map<String, dynamic>> verifyDelivery(int orderId, String code) async {
    validated.add('delivery:$orderId:$code');

    return {'success': true};
  }
}

/// Le badge traduit les statuts techniques du backend. Une commande dont le
/// statut n'est pas traduit afficherait un libellé anglais brut au fournisseur
/// ou au client — c'est exactement le défaut qu'on avait corrigé côté
/// backoffice.
void main() {
  group('OrderFollowUpController', () {
    final sample = [
      {'id': 1, 'status': 'paid'},
      {'id': 2, 'status': 'driver_assigned'},
      {'id': 3, 'status': 'delivered'},
      {'id': 4, 'status': 'cancelled'},
    ];

    test('sépare les commandes à traiter de l historique', () async {
      final controller = OrderFollowUpController(
        repository: _FakeOrderRepository(supplierOrders: sample),
      )..asSupplier = true;

      await controller.load();

      expect(controller.activeOrders.map((o) => o['id']), [1, 2]);
      expect(controller.pastOrders.map((o) => o['id']), [3, 4]);
    });

    test('signale une panne sans vider la liste affichée', () async {
      final controller = OrderFollowUpController(
        repository: _FakeOrderRepository(shouldFail: true),
      )..asSupplier = true;

      await controller.load();

      expect(controller.errorMsg.value, contains('Vérifiez votre connexion'));
      expect(controller.isLoading.value, isFalse);
    });

    test('le code n est demandé qu à la révélation', () async {
      final repo = _FakeOrderRepository(supplierOrders: sample);
      final controller = OrderFollowUpController(repository: repo)
        ..asSupplier = true;

      await controller.load();

      // Charger la liste ne doit récupérer aucun code : ils ne doivent pas
      // transiter par les données mises en cache.
      expect(repo.revealCalls, 0);

      final code = await controller.revealCode(1);

      expect(code, 'LIVREUR-4821');
      expect(repo.revealCalls, 1);
    });
  });

  group('confirmation par la contrepartie', () {
    test('le fournisseur valide le retrait avec le code récupéré', () async {
      final repo = _FakeOrderRepository(
        supplierOrders: [
          {'id': 7, 'status': 'driver_assigned'},
        ],
      );
      final controller = OrderFollowUpController(repository: repo)
        ..asSupplier = true;

      final ok = await controller.confirmFromCounterparty(7, isPickup: true);

      expect(ok, isTrue);
      // Le code n'est pas saisi : il est récupéré puis transmis, le
      // fournisseur atteste simplement la remise.
      expect(repo.validated, ['pickup:7:LIVREUR-4821']);
    });

    test('le client confirme la réception', () async {
      final repo = _FakeOrderRepository();
      final controller = OrderFollowUpController(repository: repo)
        ..asSupplier = false;

      final ok = await controller.confirmFromCounterparty(9, isPickup: false);

      expect(ok, isTrue);
      expect(repo.validated, ['delivery:9:LIVREUR-4821']);
    });

    test('sans code disponible, aucune validation n est envoyée', () async {
      final repo = _FakeOrderRepository()..codeToReturn = null;
      final controller = OrderFollowUpController(repository: repo)
        ..asSupplier = true;

      final ok = await controller.confirmFromCounterparty(7, isPickup: true);

      expect(ok, isFalse);
      expect(repo.validated, isEmpty);
      expect(controller.errorMsg.value, contains('Code indisponible'));
    });
  });

  group('OrderStatusBadge.describe', () {
    test('traduit tous les statuts du cycle de vie e-commerce', () {
      // Statuts émis par OrderService, du paiement à la livraison.
      const lifecycle = [
        'paid',
        'prepared',
        'searching_driver',
        'driver_assigned',
        'driver_picked_up',
        'delivered',
        'disputed',
        'cancelled',
      ];

      for (final status in lifecycle) {
        final tone = OrderStatusBadge.describe(status);

        expect(
          tone.label,
          isNot(equals(status)),
          reason: 'Le statut « $status » doit être traduit en français.',
        );
        expect(tone.label.trim(), isNotEmpty);
      }
    });

    test('retombe sur le statut brut plutôt que sur du vide', () {
      // Si le backend introduit un statut, mieux vaut l'afficher tel quel que
      // de laisser un badge vide.
      final tone = OrderStatusBadge.describe('un_statut_inconnu');

      expect(tone.label, 'un_statut_inconnu');
    });

    test('distingue visuellement les étapes clés', () {
      final paid = OrderStatusBadge.describe('paid');
      final delivered = OrderStatusBadge.describe('delivered');
      final disputed = OrderStatusBadge.describe('disputed');

      // « À préparer », « Livrée » et « Litige » ne doivent pas se confondre
      // d'un coup d'œil au comptoir.
      expect(paid.color, isNot(equals(delivered.color)));
      expect(disputed.color, isNot(equals(delivered.color)));
    });
  });
}
