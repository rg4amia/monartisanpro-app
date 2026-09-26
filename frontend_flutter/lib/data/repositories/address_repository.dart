import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/address_model.dart';

class AddressRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'addresses_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _ttl = Duration(minutes: 5);

  String get _key => 'list_u${StorageService.getUserId() ?? 0}';

  Future<List<AddressModel>> list({bool forceRefresh = false}) async {
    await _store.init();
    final rows = await _store.readList(
      key: _key,
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.addresses),
        );
        final data = res.data;
        final rawList =
            data is Map && data['data'] is List ? data['data'] as List : [];
        return rawList
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
      },
    );
    return rows.map(AddressModel.fromJson).toList();
  }

  Future<AddressModel> create(AddressModel address) async {
    final response = await _client.post(
      ApiEndpoints.addresses,
      data: address.toRequestJson(),
    );
    await _store.invalidate(_key);
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
    await _store.invalidate(_key);
    final data = response.data;
    final payload =
        data is Map && data['data'] is Map ? data['data'] as Map : {};
    return AddressModel.fromJson(payload.cast<String, dynamic>());
  }

  Future<void> delete(int id) async {
    await _client.delete(ApiEndpoints.address(id));
    await _store.invalidate(_key);
  }

  Future<AddressModel> setDefault(int id) async {
    final response = await _client.post(ApiEndpoints.addressSetDefault(id));
    await _store.invalidate(_key);
    final data = response.data;
    final payload =
        data is Map && data['data'] is Map ? data['data'] as Map : {};
    return AddressModel.fromJson(payload.cast<String, dynamic>());
  }

  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
