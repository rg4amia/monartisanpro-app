import 'package:get/get.dart';
import '../models/message_model.dart';
import '../../core/network/message_service.dart';

class MessageController extends GetxController {
  final MessageService _messageService = MessageService();

  // Observable state
  final RxList<Conversation> conversations = <Conversation>[].obs;
  final RxList<ProjectMessage> currentMessages = <ProjectMessage>[].obs;
  final RxBool isLoading = false.obs;
  final RxBool isSending = false.obs;
  final RxString errorMessage = ''.obs;
  final Rxn<int> currentProjectId = Rxn<int>();

  /// Get total unread count
  int get totalUnreadCount =>
      conversations.fold(0, (sum, conv) => sum + conv.unreadCount);

  @override
  void onInit() {
    super.onInit();
    fetchConversations();
  }

  /// Fetch all conversations
  Future<void> fetchConversations() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _messageService.getConversations();

      if (response.success && response.data != null) {
        conversations.value = response.data!;
      } else {
        errorMessage.value =
            response.message ?? 'Erreur de chargement des conversations';
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch messages for a specific project
  Future<void> fetchProjectMessages(int projectId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';
      currentProjectId.value = projectId;

      final response = await _messageService.getProjectMessages(projectId);

      if (response.success && response.data != null) {
        currentMessages.value = response.data!;
        // Mark messages as read
        await markAsRead(projectId);
      } else {
        errorMessage.value =
            response.message ?? 'Erreur de chargement des messages';
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Send a message
  Future<bool> sendMessage({
    required int projectId,
    required String message,
    List<String>? attachments,
  }) async {
    try {
      isSending.value = true;
      errorMessage.value = '';

      final response = await _messageService.sendMessage(
        projectId: projectId,
        message: message,
        attachments: attachments,
      );

      if (response.success && response.data != null) {
        // Add the new message to the list
        currentMessages.add(response.data!);
        // Refresh conversations to update last message
        fetchConversations();
        return true;
      } else {
        errorMessage.value = response.message ?? 'Erreur d\'envoi du message';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      return false;
    } finally {
      isSending.value = false;
    }
  }

  /// Mark messages as read
  Future<void> markAsRead(int projectId) async {
    try {
      await _messageService.markAsRead(projectId);
      // Update conversation unread count
      final convIndex = conversations.indexWhere(
        (conv) => conv.id == projectId,
      );
      if (convIndex != -1) {
        final updatedConv = Conversation(
          id: conversations[convIndex].id,
          title: conversations[convIndex].title,
          tradeName: conversations[convIndex].tradeName,
          status: conversations[convIndex].status,
          otherUser: conversations[convIndex].otherUser,
          lastMessage: conversations[convIndex].lastMessage,
          unreadCount: 0,
          updatedAt: conversations[convIndex].updatedAt,
        );
        conversations[convIndex] = updatedConv;
      }
    } catch (e) {
      // Silently fail - not critical
    }
  }

  /// Clear current conversation
  void clearCurrentConversation() {
    currentMessages.clear();
    currentProjectId.value = null;
  }
}
