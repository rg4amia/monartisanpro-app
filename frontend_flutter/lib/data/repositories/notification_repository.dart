import 'package:frontend_flutter/core/utils/json_readers.dart';
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
        return readDataList(res.data).map(NotificationModel.fromJson).toList();
      },
    );
  }

  /// Une page de notifications, filtrée par rubrique si [domain] est fourni.
  /// Sans cache de repli : un échec remonte, il ne passe jamais pour une
  /// liste vide.
  Future<NotificationPage> fetchPage({
    int page = 1,
    String? domain,
    int perPage = 30,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.get(
        ApiEndpoints.notifications,
        params: {
          'page': page,
          'per_page': perPage,
          if (domain != null) 'domain': domain,
        },
      ),
    );
    return NotificationPage.fromResponse(res.data);
  }

  Future<NotificationPreferences> getPreferences() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.notificationPreferences),
    );
    return NotificationPreferences.fromResponse(res.data);
  }

  /// Enregistre les réglages transmis ; les rubriques absentes ne changent
  /// pas. Renvoie les préférences telles que le serveur les a retenues.
  Future<NotificationPreferences> updatePreferences({
    bool? promotionalPush,
    Map<String, Map<String, bool>>? domains,
  }) async {
    final res = await _client.put(
      ApiEndpoints.notificationPreferences,
      data: {
        if (promotionalPush != null) 'promotional_push': promotionalPush,
        if (domains != null) 'domains': domains,
      },
    );
    return NotificationPreferences.fromResponse(res.data);
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
