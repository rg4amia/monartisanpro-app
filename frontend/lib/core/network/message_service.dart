import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/message_model.dart';
import 'dio_client.dart';

class MessageService {
  final Dio _dio = DioClient().dio;

  /// Get all conversations for the current user
  Future<ApiResponse<List<Conversation>>> getConversations() async {
    try {
      final response = await _dio.get('/projects/messages');

      return ApiResponse<List<Conversation>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((conv) => Conversation.fromJson(conv as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Conversation>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get messages for a specific project
  Future<ApiResponse<List<ProjectMessage>>> getProjectMessages(
    int projectId,
  ) async {
    try {
      final response = await _dio.get('/projects/$projectId/messages');

      return ApiResponse<List<ProjectMessage>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((msg) =>
                ProjectMessage.fromJson(msg as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<ProjectMessage>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Send a message to a project
  Future<ApiResponse<ProjectMessage>> sendMessage({
    required int projectId,
    required String message,
    List<String>? attachments,
  }) async {
    try {
      final response = await _dio.post(
        '/projects/$projectId/messages',
        data: {
          'message': message,
          if (attachments != null && attachments.isNotEmpty)
            'attachments': attachments,
        },
      );

      return ApiResponse<ProjectMessage>.fromJson(
        response.data,
        (json) => ProjectMessage.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ProjectMessage>.fromJson(
          e.response!.data,
          (json) => ProjectMessage.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Mark messages as read
  Future<ApiResponse<void>> markAsRead(int projectId) async {
    try {
      final response = await _dio.post('/projects/$projectId/messages/read');

      return ApiResponse<void>.fromJson(response.data, (json) {});
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (json) {});
      }
      rethrow;
    }
  }
}
