import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';

class ParrainageRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'parrainages_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _ttl = Duration(minutes: 5);

  String get _key => 'filleuls_u${StorageService.getUserId() ?? 0}';

  Future<List<Map<String, dynamic>>> getFilleuls({
    bool forceRefresh = false,
  }) async {
    await _store.init();

    return _store.readList(
      key: _key,
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get('/parrainages'),
        );
        final data = (res.data as Map<String, dynamic>)['data'] as List?;
        return (data ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
      },
    );
  }

  Future<Map<String, dynamic>> addFilleul(String phone) async {
    final res = await _client.post(
      '/parrainages',
      data: {
        'filleul_phone': phone,
      },
    );
    await _store.invalidate(_key);
    return res.data;
  }
}
