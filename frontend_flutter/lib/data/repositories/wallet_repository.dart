import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../../core/utils/json_readers.dart';

class WalletRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'wallet_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _balanceTtl = Duration(minutes: 2);

  String get _key => 'balance_u${StorageService.getUserId() ?? 0}';

  Future<Map<String, int>> getBalance({bool forceRefresh = false}) async {
    await _store.init();

    final data = await _store.readOne(
      key: _key,
      ttl: _balanceTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.walletBalance),
        );
        return requireDataMap(res.data);
      },
    );

    return {
      'walletMateriaux': readInt(data['walletMateriaux']) ?? 0,
      'walletMo': readInt(data['walletMo']) ?? 0,
      'total': readInt(data['total']) ?? 0,
    };
  }

  Future<void> invalidateBalance() async {
    await _store.init();
    await _store.invalidate(_key);
  }

  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
