import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';
import '../models/chat_message_model.dart';

class ChatRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> fetchMessages(
    int missionId, {
    int page = 1,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.get(
        ApiEndpoints.missionMessages(missionId),
        params: {'page': page},
      ),
    );

    final data = requireMap(res.data);
    final rawList = readList(data['data']) ?? [];
    final messages = rawList
        .map((m) => ChatMessageModel.fromJson(requireMap(m)))
        .toList();

    return {
      'is_funded': readBool(data['is_funded']) ?? false,
      'chat_mode': readString(data['chat_mode']) ?? 'unlimited',
      'messages': messages,
    };
  }

  Future<ChatMessageModel> sendMessage({
    required int missionId,
    String? content,
    String? filePath,
    String type = 'text',
  }) async {
    if (filePath != null && filePath.isNotEmpty) {
      final fileName = filePath.split(RegExp(r'[/\\]')).last;
      final formData = FormData.fromMap({
        'type': type,
        if (content != null && content.isNotEmpty) 'content': content,
        'file': await MultipartFile.fromFile(filePath, filename: fileName),
      });

      final res = await NetworkExecutor.run(
        () => _client.postMultipart(
          ApiEndpoints.missionMessages(missionId),
          formData,
        ),
      );

      final data = requireMap(res.data);
      return ChatMessageModel.fromJson(requireMap(data['data']));
    }

    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.missionMessages(missionId),
        data: {
          'type': type,
          'content': content ?? '',
        },
      ),
    );

    final data = requireMap(res.data);
    return ChatMessageModel.fromJson(requireMap(data['data']));
  }

  Future<void> markAsRead(int missionId, int messageId) async {
    try {
      await _client.post(ApiEndpoints.missionMessageRead(missionId, messageId));
    } catch (_) {
      // Ignorer silencieusement pour le tracking de lecture
    }
  }
}
