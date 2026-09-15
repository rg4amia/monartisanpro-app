import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../models/faq_model.dart';
import '../models/support_contact_model.dart';

class SupportRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<FaqModel> _faqStore = CacheStore<FaqModel>(
    boxName: 'faqs_cache',
    fromJson: FaqModel.fromJson,
    toJson: (f) => f.toJson(),
  );

  static const Duration _ttl = Duration(minutes: 30);

  Future<List<FaqModel>> getFaqs({
    required String role,
    bool forceRefresh = false,
  }) async {
    await _faqStore.init();
    return _faqStore.readList(
      key: 'list_$role',
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(
            ApiEndpoints.faqs,
            params: {'role': role},
          ),
        );
        final data = res.data;
        final list = data is Map<String, dynamic> && data['data'] is List
            ? data['data'] as List<dynamic>
            : <dynamic>[];
        return list
            .whereType<Map<String, dynamic>>()
            .map(FaqModel.fromJson)
            .toList();
      },
    );
  }

  Future<SupportContactModel> getContactSettings() async {
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(ApiEndpoints.publicSettings),
      );
      final data = res.data;
      final settings = data is Map<String, dynamic> && data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : <String, dynamic>{};
      return SupportContactModel.fromSettings(settings);
    } catch (_) {
      return SupportContactModel.fromSettings(const {});
    }
  }
}
