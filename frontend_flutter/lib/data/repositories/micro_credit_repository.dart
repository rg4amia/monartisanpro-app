import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/micro_credit_model.dart';

class MicroCreditRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'micro_credit_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _eligibilityTtl = Duration(minutes: 2);

  String get _eligibilityKey =>
      'eligibility_u${StorageService.getUserId() ?? 0}';

  Future<MicroCreditEligibilityModel> getEligibility({
    bool forceRefresh = false,
  }) async {
    await _store.init();
    final raw = await _store.readOne(
      key: _eligibilityKey,
      ttl: _eligibilityTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.microCreditEligibility),
        );
        return Map<String, dynamic>.from(
          (res.data as Map<String, dynamic>)['data'] as Map,
        );
      },
    );
    return MicroCreditEligibilityModel.fromJson(raw);
  }

  Future<MicroCreditApplicationModel?> getCurrentCredit() async {
    final res = await _client.get(ApiEndpoints.microCreditCurrent);
    final data = (res.data as Map<String, dynamic>)['data'];
    if (data == null || data is! Map) return null;
    return MicroCreditApplicationModel.fromJson(Map<String, dynamic>.from(data));
  }

  Future<MicroCreditApplicationModel> apply(int amount) async {
    final res = await _client.post(
      ApiEndpoints.microCreditApply,
      data: {'amount': amount},
    );
    await _store.invalidate(_eligibilityKey);
    return MicroCreditApplicationModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<Map<String, dynamic>> repay(int amount, {String provider = 'wave'}) async {
    final res = await _client.post(
      ApiEndpoints.microCreditRepay,
      data: {
        'amount': amount,
        'provider': provider,
      },
    );
    await _store.invalidate(_eligibilityKey);
    final data = (res.data as Map<String, dynamic>)['data'];
    return data is Map ? Map<String, dynamic>.from(data) : {};
  }
}
