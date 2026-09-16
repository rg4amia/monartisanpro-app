import 'package:get/get.dart';

import '../../../data/repositories/order_repository.dart';

/// Suivi des commandes e-commerce, côté fournisseur comme côté client.
///
/// Les deux espaces observent la même commande à des moments différents : le
/// fournisseur la prépare puis la remet, le client la suit puis la réceptionne.
/// Un seul contrôleur, deux sources de liste.
class OrderFollowUpController extends GetxController {
  OrderFollowUpController({OrderRepository? repository})
      : _repo = repository ?? OrderRepository();

  final OrderRepository _repo;

  final orders = <Map<String, dynamic>>[].obs;
  final isLoading = false.obs;
  final errorMsg = RxnString();

  /// Commande dont l'action « préparer » est en cours, pour n'afficher le
  /// chargement que sur la ligne concernée.
  final preparingOrderId = RxnInt();

  /// Commande dont la confirmation de remise / réception est en cours.
  final confirmingOrderId = RxnInt();

  /// `true` pour l'espace fournisseur, `false` pour l'espace client.
  bool asSupplier = true;

  Future<void> load({bool silent = false}) async {
    if (!silent) isLoading.value = true;
    errorMsg.value = null;

    try {
      orders.value = asSupplier
          ? await _repo.getSupplierOrders()
          : await _repo.getMyOrders(forceRefresh: true);
    } catch (_) {
      errorMsg.value =
          'Impossible de charger les commandes. Vérifiez votre connexion.';
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> markPrepared(int orderId) async {
    preparingOrderId.value = orderId;
    errorMsg.value = null;

    try {
      await _repo.markPrepared(orderId);
      await load(silent: true);

      return true;
    } catch (_) {
      errorMsg.value = 'La commande n\'a pas pu être marquée préparée.';

      return false;
    } finally {
      preparingOrderId.value = null;
    }
  }

  Future<String?> revealCode(int orderId) =>
      _repo.fetchVerificationCode(orderId);

  /// Confirmation par la contrepartie : le fournisseur atteste avoir remis la
  /// marchandise, le client avoir reçu son colis.
  ///
  /// C'est le second chemin de la cascade. Quand le livreur n'a pas de réseau
  /// au comptoir ou sur le pas de la porte, sa validation part en file
  /// d'attente ; celle-ci, faite depuis un appareil connecté, fait avancer la
  /// commande immédiatement. Le rejeu tardif du livreur sera alors sans effet,
  /// le backend étant idempotent.
  Future<bool> confirmFromCounterparty(int orderId,
      {required bool isPickup,}) async {
    confirmingOrderId.value = orderId;
    errorMsg.value = null;

    try {
      final code = await _repo.fetchVerificationCode(orderId);
      if (code == null || code.isEmpty) {
        errorMsg.value = 'Code indisponible : impossible de confirmer.';

        return false;
      }

      final res = isPickup
          ? await _repo.verifyPickup(orderId, code)
          : await _repo.verifyDelivery(orderId, code);

      if (res['success'] != true) {
        errorMsg.value =
            res['message'] as String? ?? 'La confirmation a été refusée.';

        return false;
      }

      await load(silent: true);

      return true;
    } catch (_) {
      errorMsg.value =
          'Confirmation impossible. Vérifiez votre connexion et réessayez.';

      return false;
    } finally {
      confirmingOrderId.value = null;
    }
  }

  /// Commandes encore en cours, celles sur lesquelles l'utilisateur peut agir.
  List<Map<String, dynamic>> get activeOrders => orders
      .where((o) => !const ['delivered', 'cancelled'].contains(o['status']))
      .toList();

  /// Commandes closes, conservées pour l'historique.
  List<Map<String, dynamic>> get pastOrders => orders
      .where((o) => const ['delivered', 'cancelled'].contains(o['status']))
      .toList();
}
