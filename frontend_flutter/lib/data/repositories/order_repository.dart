import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/network/sync_service.dart';
import '../../core/storage/storage_service.dart';

class OrderRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'orders_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _myOrdersTtl = Duration(minutes: 2);

  String get _myOrdersKey => 'mine_u${StorageService.getUserId() ?? 0}';

  Future<Map<String, dynamic>> createOrder({
    required int supplierId,
    required String deliveryMode,
    required List<Map<String, dynamic>> items,
    String? vehicleClass,
    double? surgeMultiplier,
    String? promoCode,
  }) async {
    final res = await _client.post(
      ApiEndpoints.orders,
      data: {
        'supplier_id': supplierId,
        'delivery_mode': deliveryMode,
        'items': items,
        if (vehicleClass != null) 'vehicle_class': vehicleClass,
        if (surgeMultiplier != null) 'surge_multiplier': surgeMultiplier,
        if (promoCode != null && promoCode.isNotEmpty) 'promo_code': promoCode,
      },
    );
    await _invalidateMyOrders();
    return res.data;
  }

  Future<Map<String, dynamic>> createMultiOrders({
    required List<Map<String, dynamic>> packages,
    String? promoCode,
  }) async {
    final res = await _client.post(
      ApiEndpoints.ordersMultiStore,
      data: {
        'packages': packages,
        if (promoCode != null && promoCode.isNotEmpty) 'promo_code': promoCode,
      },
    );
    await _invalidateMyOrders();
    return res.data;
  }

  Future<Map<String, dynamic>> estimateMultiDelivery({
    required List<Map<String, dynamic>> packages,
    double? clientLatitude,
    double? clientLongitude,
  }) async {
    final res = await _client.post(
      ApiEndpoints.ordersMultiEstimate,
      data: {
        'packages': packages,
        if (clientLatitude != null) 'client_latitude': clientLatitude,
        if (clientLongitude != null) 'client_longitude': clientLongitude,
      },
    );
    return res.data;
  }

  /// Missions livreur disponibles : donnée temps réel, jamais mise en cache
  /// (un créneau déjà pris ne doit pas rester affiché comme disponible).
  Future<List<Map<String, dynamic>>> getAvailableDeliveries() async {
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(ApiEndpoints.deliveriesAvailable),
      );
      final data = (res.data as Map<String, dynamic>)['data'];
      if (data is List) {
        return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      }
    } catch (_) {}
    return [];
  }

  Future<List<Map<String, dynamic>>> getMyOrders({
    bool forceRefresh = false,
  }) async {
    try {
      await _store.init();
      return await _store.readList(
        key: _myOrdersKey,
        ttl: _myOrdersTtl,
        policy:
            forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
        fetch: () async {
          final res = await NetworkExecutor.run(
            () => _client.get(ApiEndpoints.orders),
          );
          final data = (res.data as Map<String, dynamic>)['data'];
          if (data is! List) return const [];
          return data.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        },
      );
    } catch (_) {
      return [];
    }
  }

  Future<Map<String, dynamic>> acceptDelivery(int orderId) async {
    final res = await _client.post(ApiEndpoints.acceptDelivery(orderId));
    await _invalidateMyOrders();
    return res.data;
  }

  Future<Map<String, dynamic>> verifyPickup(int orderId, String code) =>
      _validate(ApiEndpoints.orderVerifyPickup(orderId), code);

  Future<Map<String, dynamic>> verifyDelivery(int orderId, String code) =>
      _validate(ApiEndpoints.orderVerifyDelivery(orderId), code);

  /// Envoie une validation de code, et la met en file d'attente si le réseau
  /// fait défaut.
  ///
  /// Le code prouve la présence physique — le fournisseur ou le client vient de
  /// le communiquer en main propre. Rien n'impose de transmettre cette preuve à
  /// l'instant même : seulement de ne pas la perdre. On rejoue donc plus tard,
  /// ce que le backend accepte sans double versement grâce à l'idempotence.
  ///
  /// La bascule se décide sur un **échec de joignabilité réel**, jamais sur
  /// `connectivity_plus` : être accroché à une antenne sans données utilisables
  /// est le cas courant hors des grandes villes.
  Future<Map<String, dynamic>> _validate(String url, String code) async {
    try {
      final res = await _client.post(url, data: {'code': code});
      await _invalidateMyOrders();

      return res.data is Map<String, dynamic>
          ? res.data as Map<String, dynamic>
          : <String, dynamic>{'success': true};
    } on DioException catch (e) {
      if (!_isNetworkFailure(e) || !Get.isRegistered<SyncService>()) rethrow;

      await Get.find<SyncService>().enqueueRequest(
        'POST',
        url,
        data: {'code': code},
      );

      return <String, dynamic>{
        'success': true,
        'queued': true,
        'message': 'Validation enregistrée : elle sera transmise dès le retour '
            'du réseau. Demandez à votre interlocuteur de confirmer de son côté '
            'pour un traitement immédiat.',
      };
    }
  }

  bool _isNetworkFailure(DioException e) =>
      e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      e.type == DioExceptionType.receiveTimeout ||
      e.type == DioExceptionType.connectionError;

  /// Ouverture d'un litige par le client sur une commande livrée.
  ///
  /// Le backend reste seul juge de l'éligibilité (statut `delivered`, fenêtre
  /// temporelle paramétrable après réception) : cette méthode se contente de
  /// relayer la demande et de laisser remonter l'erreur du serveur telle
  /// quelle en cas de refus, plutôt que de la deviner côté mobile.
  Future<Map<String, dynamic>> disputeOrder(int orderId, String reason) async {
    final res = await _client.post(
      ApiEndpoints.orderDispute(orderId),
      data: {'reason': reason},
    );
    await _invalidateMyOrders();

    return res.data is Map<String, dynamic>
        ? res.data as Map<String, dynamic>
        : <String, dynamic>{};
  }

  /// Émission télémétrique périodique GPS du livreur en course (Option 3 / Lot 4)
  Future<Map<String, dynamic>> sendDriverLocation(
    int orderId, {
    required double latitude,
    required double longitude,
    double? speedKmh,
    double? heading,
    int? batteryLevel,
  }) async {
    try {
      final res = await _client.post(
        ApiEndpoints.orderLocation(orderId),
        data: {
          'latitude': latitude,
          'longitude': longitude,
          if (speedKmh != null) 'speed_kmh': speedKmh,
          if (heading != null) 'heading': heading,
          if (batteryLevel != null) 'battery_level': batteryLevel,
        },
      );
      if (res.data is Map<String, dynamic>) {
        return res.data as Map<String, dynamic>;
      }
    } catch (_) {
      // Ignorer l'erreur réseau ponctuelle de télémétrie pour ne pas interrompre le trajet
    }
    return <String, dynamic>{};
  }

  /// Émission télémétrique par lot (Batching GPS) pour économiser la batterie et le forfait data
  Future<Map<String, dynamic>> sendDriverBatchLocations(
    int orderId,
    List<Map<String, dynamic>> points,
  ) async {
    if (points.isEmpty) return <String, dynamic>{};
    try {
      final res = await _client.post(
        ApiEndpoints.orderLocation(orderId),
        data: {
          'points': points,
        },
      );
      if (res.data is Map<String, dynamic>) {
        return res.data as Map<String, dynamic>;
      }
    } catch (_) {}
    return <String, dynamic>{};
  }

  /// Récupération de la position en temps réel et tracé pour suivi 360°
  Future<Map<String, dynamic>> getOrderTracking(int orderId) async {
    try {
      final res = await _client.get(ApiEndpoints.orderTracking(orderId));
      if (res.data is Map<String, dynamic>) {
        return res.data as Map<String, dynamic>;
      }
    } catch (_) {}
    return <String, dynamic>{};
  }

  /// Commandes e-commerce du fournisseur connecté.
  ///
  /// Non mise en cache : le fournisseur agit sur cette liste (préparation,
  /// remise) et une vue périmée l'induirait en erreur au comptoir.
  Future<List<Map<String, dynamic>>> getSupplierOrders() async {
    final res = await _client.get(ApiEndpoints.supplierOrders);

    final body = res.data;
    if (body is! Map) return [];

    final data = body['data'];
    if (data is! List) return [];

    return data.whereType<Map>().map(Map<String, dynamic>.from).toList();
  }

  /// Marque une commande comme préparée. En retrait magasin elle attend le
  /// client ; en livraison elle part sur le marché des livreurs.
  Future<Map<String, dynamic>> markPrepared(int orderId) async {
    final res = await _client.post(ApiEndpoints.orderPrepared(orderId));
    await _invalidateMyOrders();

    return res.data is Map<String, dynamic>
        ? res.data as Map<String, dynamic>
        : <String, dynamic>{};
  }

  /// Signale un temps d'attente du livreur (retrait ou livraison) : le
  /// backend majore les frais de livraison de la commande (100 FCFA / 5 min)
  /// et reste seul juge de l'autorisation (livreur assigné à la commande, ou
  /// admin) et du calcul du montant. Cette méthode relaie simplement la
  /// demande et laisse remonter l'erreur du serveur telle quelle en cas de
  /// refus (ex. livreur non assigné, minutes invalides), pour affichage direct
  /// à l'écran.
  Future<Map<String, dynamic>> applyWaitingSurge(
    int orderId,
    int waitingMinutes,
  ) async {
    final res = await _client.post(
      ApiEndpoints.orderWaitingSurge(orderId),
      data: {'waiting_minutes': waitingMinutes},
    );
    await _invalidateMyOrders();

    return res.data is Map<String, dynamic>
        ? res.data as Map<String, dynamic>
        : <String, dynamic>{};
  }

  /// Récupère le code de vérification que le backend juge visible par
  /// l'utilisateur courant : code de retrait pour le fournisseur, code de
  /// réception pour le client en livraison, code de retrait pour le client en
  /// retrait magasin. Le livreur n'en reçoit aucun.
  ///
  /// Volontairement appelé à la demande, au moment de la révélation, et jamais
  /// mis en cache : la liste des commandes est persistée dans Hive, et un code
  /// de validation n'a pas à se retrouver sur le disque de l'appareil.
  Future<String?> fetchVerificationCode(int orderId) async {
    final res = await _client.get(ApiEndpoints.order(orderId));

    final body = res.data;
    if (body is! Map) return null;

    final data = body['data'];
    if (data is! Map) return null;

    for (final key in ['reception_code', 'pickup_code']) {
      final value = data[key];
      if (value is String && value.trim().isNotEmpty) {
        return value.trim();
      }
    }

    return null;
  }

  Future<void> _invalidateMyOrders() async {
    await _store.init();
    await _store.invalidate(_myOrdersKey);
  }
}
