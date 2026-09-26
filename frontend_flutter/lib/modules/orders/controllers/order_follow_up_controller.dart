import 'package:get/get.dart';

import '../../../core/payments/operator_payment_runner.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/delivery_fare_model.dart';
import '../../../data/models/payment_model.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../data/repositories/payment_repository.dart';

/// Suivi des commandes e-commerce, côté fournisseur comme côté client.
///
/// Les deux espaces observent la même commande à des moments différents : le
/// fournisseur la prépare puis la remet, le client la suit puis la réceptionne.
/// Un seul contrôleur, deux sources de liste.
class OrderFollowUpController extends GetxController {
  OrderFollowUpController({
    OrderRepository? repository,
    PaymentRepository? paymentRepository,
    Future<bool> Function(Uri uri)? openPaymentUrl,
    Duration pollInterval = const Duration(seconds: 2),
  })  : _repo = repository ?? OrderRepository(),
        _paymentRepo = paymentRepository ?? PaymentRepository(),
        _runner = OperatorPaymentRunner(
          paymentRepository: paymentRepository,
          openPaymentUrl: openPaymentUrl,
          pollInterval: pollInterval,
        );

  final OrderRepository _repo;
  final PaymentRepository _paymentRepo;
  final OperatorPaymentRunner _runner;

  /// Commande dont le paiement (course ou commande) est en cours.
  final payingOrderId = RxnInt();

  final orders = <Map<String, dynamic>>[].obs;
  final isLoading = false.obs;
  final errorMsg = RxnString();

  /// Commande dont l'action « préparer » est en cours, pour n'afficher le
  /// chargement que sur la ligne concernée.
  final preparingOrderId = RxnInt();

  /// Commande dont la confirmation de remise / réception est en cours.
  final confirmingOrderId = RxnInt();

  /// Commande dont l'ouverture de litige est en cours.
  final disputingOrderId = RxnInt();

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
  Future<bool> confirmFromCounterparty(
    int orderId, {
    required bool isPickup,
  }) async {
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

  /// Ouverture d'un litige par le client sur une commande livrée.
  ///
  /// Le backend est seul juge de l'éligibilité (statut `delivered`, fenêtre
  /// temporelle paramétrable en backoffice après réception) : en cas de refus
  /// son message exact est relayé tel quel dans [errorMsg] plutôt que d'être
  /// remplacé par un message générique.
  Future<bool> disputeOrder(int orderId, String reason) async {
    disputingOrderId.value = orderId;
    errorMsg.value = null;

    try {
      final res = await _repo.disputeOrder(orderId, reason);
      if (res['success'] != true) {
        errorMsg.value =
            res['message'] as String? ?? 'Le litige n\'a pas pu être ouvert.';

        return false;
      }

      await load(silent: true);

      return true;
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);

      return false;
    } finally {
      disputingOrderId.value = null;
    }
  }

  /// Règlement de la course d'une commande livrée (modèle « à la Yango »).
  ///
  /// Ouvre la page de paiement de l'opérateur puis interroge le statut ; le
  /// serveur crédite le livreur à la confirmation. Renvoie `true` si le
  /// paiement est confirmé pendant l'attente.
  Future<bool> payDeliveryFare(int orderId, {required String provider}) =>
      _payThroughOperator(
        orderId,
        provider,
        (phone) => _paymentRepo.initiateDeliveryFarePayment(
          orderId: orderId,
          provider: provider,
          phone: phone,
        ),
      );

  /// Règle une commande de matériaux en attente de paiement — tout le panier
  /// multi-quincailleries si elle en fait partie (Chantier 11).
  Future<bool> payOrder(int orderId, {required String provider}) =>
      _payThroughOperator(
        orderId,
        provider,
        (phone) => _paymentRepo.initiateOrderPayment(
          orderId: orderId,
          provider: provider,
          phone: phone,
        ),
      );

  Future<bool> _payThroughOperator(
    int orderId,
    String provider,
    Future<PaymentInitiationModel> Function(String phone) initiate,
  ) async {
    payingOrderId.value = orderId;
    errorMsg.value = null;

    try {
      final phone = StorageService.getPhone() ?? '';
      if (provider != 'virement_bancaire' && phone.trim().isEmpty) {
        errorMsg.value =
            'Numéro Mobile Money introuvable. Renseignez-le dans vos paramètres.';

        return false;
      }

      final payment = await initiate(phone);

      switch (await _runner.run(payment)) {
        case OperatorPaymentOutcome.confirmed:
          await load(silent: true);

          return true;
        case OperatorPaymentOutcome.failed:
          errorMsg.value = 'Le paiement a échoué ou a été annulé.';

          return false;
        case OperatorPaymentOutcome.pending:
          errorMsg.value =
              'Paiement en attente de confirmation. Tirez pour actualiser dans un instant.';

          return false;
      }
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);

      return false;
    } finally {
      payingOrderId.value = null;
    }
  }

  static bool _awaitsFarePayment(Map<String, dynamic> order) =>
      DeliveryFare.tryParse(order['delivery_fare'])?.isAwaitingPayment ?? false;

  static bool _isClosed(Map<String, dynamic> order) =>
      const ['delivered', 'cancelled'].contains(order['status']) &&
      !_awaitsFarePayment(order);

  /// Commandes encore en cours, celles sur lesquelles l'utilisateur peut agir
  /// — y compris une commande livrée dont la course reste à régler.
  List<Map<String, dynamic>> get activeOrders =>
      orders.where((o) => !_isClosed(o)).toList();

  /// Commandes closes, conservées pour l'historique.
  List<Map<String, dynamic>> get pastOrders => orders.where(_isClosed).toList();
}
