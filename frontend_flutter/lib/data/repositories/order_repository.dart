import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class OrderRepository {
  final ApiClient _client = ApiClient();

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
    return res.data;
  }

  Future<List<Map<String, dynamic>>> getAvailableDeliveries() async {
    try {
      final res = await _client.get(ApiEndpoints.deliveriesAvailable);
      if (res.data is Map && res.data['data'] is List) {
        return List<Map<String, dynamic>>.from(res.data['data']);
      }
    } catch (_) {}
    return [];
  }

  Future<List<Map<String, dynamic>>> getMyOrders() async {
    try {
      final res = await _client.get(ApiEndpoints.orders);
      if (res.data is Map && res.data['data'] is List) {
        return List<Map<String, dynamic>>.from(res.data['data']);
      }
    } catch (_) {}
    return [];
  }

  Future<Map<String, dynamic>> acceptDelivery(int orderId) async {
    final res = await _client.post(ApiEndpoints.acceptDelivery(orderId));
    return res.data;
  }

  Future<Map<String, dynamic>> verifyPickup(int orderId, String code) async {
    final res = await _client.post(
      ApiEndpoints.orderVerifyPickup(orderId),
      data: {'code': code},
    );
    return res.data;
  }

  Future<Map<String, dynamic>> verifyDelivery(int orderId, String code) async {
    final res = await _client.post(
      ApiEndpoints.orderVerifyDelivery(orderId),
      data: {'code': code},
    );
    return res.data;
  }
}
