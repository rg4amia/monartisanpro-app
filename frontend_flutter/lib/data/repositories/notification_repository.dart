import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/notification_model.dart';

class NotificationRepository {
  final ApiClient _client = ApiClient();

  Future<List<NotificationModel>> getNotifications() async {
    final res = await _client.get(ApiEndpoints.notifications);
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => NotificationModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> markRead(int id) async {
    await _client.put(ApiEndpoints.markNotificationRead(id));
  }

  Future<void> markAllRead() async {
    await _client.post(ApiEndpoints.markAllRead);
  }

  Future<void> submitEvaluation({
    required int missionId,
    required int evalueId,
    required int note,
    String? commentaire,
  }) async {
    await _client.post(ApiEndpoints.evaluations, data: {
      'mission_id': missionId,
      'evalue_id': evalueId,
      'note': note,
      if (commentaire != null) 'commentaire': commentaire,
    });
  }

  Future<void> submitLitige({
    required int missionId,
    required String description,
  }) async {
    await _client.post(ApiEndpoints.litiges, data: {
      'mission_id': missionId,
      'description': description,
    });
  }

  Future<Map<String, dynamic>> getLitige(int id) async {
    final res = await _client.get(ApiEndpoints.litige(id));
    return res.data as Map<String, dynamic>;
  }
}
