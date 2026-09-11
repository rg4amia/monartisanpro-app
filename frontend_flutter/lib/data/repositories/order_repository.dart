import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
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
          return data
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
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

  Future<Map<String, dynamic>> verifyPickup(int orderId, String code) async {
    final res = await _client.post(
      ApiEndpoints.orderVerifyPickup(orderId),
      data: {'code': code},
    );
    await _invalidateMyOrders();
    return res.data;
  }

  Future<Map<String, dynamic>> verifyDelivery(int orderId, String code) async {
    final res = await _client.post(
      ApiEndpoints.orderVerifyDelivery(orderId),
      data: {'code': code},
    );
    await _invalidateMyOrders();
    return res.data;
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

  Future<void> _invalidateMyOrders() async {
    await _store.init();
    await _store.invalidate(_myOrdersKey);
  }
}
