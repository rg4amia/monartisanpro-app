import 'package:dio/dio.dart';
import '../../shared/models/message_model.dart';
import '../constants/api_constants.dart';
import 'dio_client.dart';

class MessageService {
  final DioClient _dioClient;

  MessageService(this._dioClient);

  /// Get all conversations for the authenticated user
  Future<List<Conversation>> getConversations() async {
    try {
      final response = await _dioClient.dio.get(ApiConstants.getConversations);

      if (response.statusCode == 200 && response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Conversation.fromJson(json)).toList();
      }

      throw Exception('Failed to load conversations');
    } catch (e) {
      print('Error fetching conversations: $e');
      rethrow;
    }
  }

  /// Get all messages for a specific project
  Future<List<ProjectMessage>> getProjectMessages(int projectId) async {
    try {
      final response = await _dioClient.dio.get(
        ApiConstants.getProjectMessages(projectId),
      );

      if (response.statusCode == 200 && response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => ProjectMessage.fromJson(json)).toList();
      }

      throw Exception('Failed to load messages');
    } catch (e) {
      print('Error fetching messages: $e');
      rethrow;
    }
  }

  /// Send a message to a project
  Future<ProjectMessage> sendMessage(
    int projectId,
    String message, {
    List<String>? attachmentPaths,
  }) async {
    try {
      FormData formData = FormData.fromMap({
        'message': message,
      });

      // Add attachments if provided
      if (attachmentPaths != null && attachmentPaths.isNotEmpty) {
        for (int i = 0; i < attachmentPaths.length; i++) {
          formData.files.add(MapEntry(
            'attachments[$i]',
            await MultipartFile.fromFile(
              attachmentPaths[i],
              filename: attachmentPaths[i].split('/').last,
            ),
          ));
        }
      }

      final response = await _dioClient.dio.post(
        ApiConstants.sendMessage(projectId),
        data: formData,
      );

      if (response.statusCode == 201 && response.data['success'] == true) {
        return ProjectMessage.fromJson(response.data['data']);
      }

      throw Exception(response.data['message'] ?? 'Failed to send message');
    } catch (e) {
      print('Error sending message: $e');
      rethrow;
    }
  }

  /// Mark a message as read
  Future<void> markAsRead(int messageId) async {
    try {
      final response = await _dioClient.dio.post(
        ApiConstants.markMessageAsRead(messageId),
      );

      if (response.statusCode != 200 || response.data['success'] != true) {
        throw Exception('Failed to mark message as read');
      }
    } catch (e) {
      print('Error marking message as read: $e');
      // Don't rethrow - this is a non-critical operation
    }
  }
}
