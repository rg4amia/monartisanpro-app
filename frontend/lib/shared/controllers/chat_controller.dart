import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../models/message_model.dart';
import '../../core/network/message_service.dart';

class ChatController extends GetxController {
  final MessageService _messageService = Get.find();
  final ImagePicker _picker = ImagePicker();

  final conversations = <Conversation>[].obs;
  final currentMessages = <ProjectMessage>[].obs;
  final isLoading = false.obs;
  final isSendingMessage = false.obs;
  final currentProjectId = Rxn<int>();

  @override
  void onInit() {
    super.onInit();
    fetchConversations();
  }

  /// Fetch all conversations for the authenticated user
  Future<void> fetchConversations() async {
    try {
      isLoading.value = true;
      final result = await _messageService.getConversations();
      conversations.value = result;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les conversations: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch messages for a specific project
  Future<void> fetchProjectMessages(int projectId) async {
    try {
      isLoading.value = true;
      currentProjectId.value = projectId;
      final result = await _messageService.getProjectMessages(projectId);
      currentMessages.value = result;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les messages: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  /// Send a text message
  Future<bool> sendMessage(int projectId, String message) async {
    if (message.trim().isEmpty) return false;

    try {
      isSendingMessage.value = true;
      final newMessage = await _messageService.sendMessage(
        projectId,
        message.trim(),
      );

      // Add message to current messages if we're viewing this conversation
      if (currentProjectId.value == projectId) {
        currentMessages.add(newMessage);
      }

      // Update conversation's last message
      _updateConversationLastMessage(projectId, newMessage);

      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible d\'envoyer le message: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      isSendingMessage.value = false;
    }
  }

  /// Send a message with attachments
  Future<bool> sendMessageWithAttachments(
    int projectId,
    String message,
    List<String> attachmentPaths,
  ) async {
    if (message.trim().isEmpty && attachmentPaths.isEmpty) return false;

    try {
      isSendingMessage.value = true;
      final newMessage = await _messageService.sendMessage(
        projectId,
        message.trim(),
        attachmentPaths: attachmentPaths,
      );

      // Add message to current messages if we're viewing this conversation
      if (currentProjectId.value == projectId) {
        currentMessages.add(newMessage);
      }

      // Update conversation's last message
      _updateConversationLastMessage(projectId, newMessage);

      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible d\'envoyer le message: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      isSendingMessage.value = false;
    }
  }

  /// Pick images from gallery
  Future<List<String>> pickImages() async {
    try {
      final List<XFile> images = await _picker.pickMultiImage(
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );
      return images.map((image) => image.path).toList();
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner les images: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return [];
    }
  }

  /// Pick a single image from gallery
  Future<String?> pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );
      return image?.path;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner l\'image: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    }
  }

  /// Take a photo with camera
  Future<String?> takePhoto() async {
    try {
      final XFile? photo = await _picker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1920,
        maxHeight: 1920,
        imageQuality: 85,
      );
      return photo?.path;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de prendre la photo: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    }
  }

  /// Get conversation by project ID
  Conversation? getConversationByProjectId(int projectId) {
    try {
      return conversations.firstWhere((conv) => conv.id == projectId);
    } catch (e) {
      return null;
    }
  }

  /// Get unread message count
  int getTotalUnreadCount() {
    return conversations.fold(0, (sum, conv) => sum + conv.unreadCount);
  }

  /// Update conversation's last message (helper method)
  void _updateConversationLastMessage(int projectId, ProjectMessage message) {
    final index = conversations.indexWhere((conv) => conv.id == projectId);
    if (index != -1) {
      final conv = conversations[index];
      final updatedConv = Conversation(
        id: conv.id,
        title: conv.title,
        tradeName: conv.tradeName,
        status: conv.status,
        otherUser: conv.otherUser,
        lastMessage: LastMessage(
          message: message.content,
          createdAt: message.createdAt,
          senderId: message.sender.id,
        ),
        unreadCount: 0, // Reset since we just sent a message
        updatedAt: message.createdAt,
      );

      conversations[index] = updatedConv;
      // Sort by updated_at
      conversations.sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    }
  }

  /// Clear current conversation
  void clearCurrentConversation() {
    currentMessages.clear();
    currentProjectId.value = null;
  }
}
