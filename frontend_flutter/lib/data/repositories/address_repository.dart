import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/address_model.dart';

class AddressRepository {
  final ApiClient _client = ApiClient();

  Future<List<AddressModel>> list() async {
    final response = await _client.get(ApiEndpoints.addresses);
    final data = response.data;
    final rawList =
        data is Map && data['data'] is List ? data['data'] as List : [];
    return rawList
        .whereType<Map>()
        .map((item) => AddressModel.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  Future<AddressModel> create(AddressModel address) async {
    final response = await _client.post(
      ApiEndpoints.addresses,
      data: address.toRequestJson(),
    );
    final data = response.data;
    final payload =
        data is Map && data['data'] is Map ? data['data'] as Map : {};
    return AddressModel.fromJson(payload.cast<String, dynamic>());
  }

  Future<AddressModel> update(int id, AddressModel address) async {
    final response = await _client.put(
      ApiEndpoints.address(id),
      data: address.toRequestJson(),
    );
    final data = response.data;
    final payload =
        data is Map && data['data'] is Map ? data['data'] as Map : {};
    return AddressModel.fromJson(payload.cast<String, dynamic>());
  }

  Future<void> delete(int id) async {
    await _client.delete(ApiEndpoints.address(id));
  }

  Future<AddressModel> setDefault(int id) async {
    final response = await _client.post(ApiEndpoints.addressSetDefault(id));
    final data = response.data;
    final payload =
        data is Map && data['data'] is Map ? data['data'] as Map : {};
    return AddressModel.fromJson(payload.cast<String, dynamic>());
  }
}
