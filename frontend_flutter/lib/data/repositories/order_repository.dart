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
  }) async {
    final res = await _client.post(
      ApiEndpoints.orders,
      data: {
        'supplier_id': supplierId,
        'delivery_mode': deliveryMode,
        'items': items,
        if (vehicleClass != null) 'vehicle_class': vehicleClass,
        if (surgeMultiplier != null) 'surge_multiplier': surgeMultiplier,
      },
    );
    return res.data;
  }
}
