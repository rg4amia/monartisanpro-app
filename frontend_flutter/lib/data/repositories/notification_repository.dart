import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/notification_model.dart';

class NotificationRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<NotificationModel> _store =
      CacheStore<NotificationModel>(
    boxName: 'notifications_cache',
    fromJson: NotificationModel.fromJson,
    toJson: (n) => n.toJson(),
  );

  static const Duration _ttl = Duration(minutes: 1);

  String get _key => 'list_u${StorageService.getUserId() ?? 0}';

  Future<List<NotificationModel>> getNotifications({
    bool forceRefresh = false,
  }) async {
    await _store.init();
    return _store.readList(
      key: _key,
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.notifications),
        );
        final list = res.data['data'] as List<dynamic>;
        return list
            .map((e) => NotificationModel.fromJson(e as Map<String, dynamic>))
            .toList();
      },
    );
  }

  Future<void> markRead(int id) async {
    await _client.put(ApiEndpoints.markNotificationRead(id));
    await _store.invalidate(_key);
  }

  Future<void> markAllRead() async {
    await _client.post(ApiEndpoints.markAllRead);
    await _store.invalidate(_key);
  }

  Future<void> submitEvaluation({
    required int missionId,
    required int evalueId,
    required int note,
    String? commentaire,
  }) async {
    await _client.post(
      ApiEndpoints.evaluations,
      data: {
        'mission_id': missionId,
        'evalue_id': evalueId,
        'note': note,
        if (commentaire != null) 'commentaire': commentaire,
      },
    );
  }

  Future<void> submitLitige({
    required int missionId,
    required String description,
  }) async {
    await _client.post(
      ApiEndpoints.litiges,
      data: {
        'mission_id': missionId,
        'description': description,
      },
    );
  }

  Future<Map<String, dynamic>> getLitige(int id) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.litige(id)),
    );
    return res.data as Map<String, dynamic>;
  }
}
