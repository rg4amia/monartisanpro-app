import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../shared/models/message_model.dart';
import '../../../../shared/controllers/chat_controller.dart';

class ChatConversationScreen extends StatefulWidget {
  final int projectId;
  final String projectTitle;
  final ConversationUser? otherUser;

  const ChatConversationScreen({
    super.key,
    required this.projectId,
    required this.projectTitle,
    this.otherUser,
  });

  @override
  State<ChatConversationScreen> createState() => _ChatConversationScreenState();
}

class _ChatConversationScreenState extends State<ChatConversationScreen> {
  final chatController = Get.find<ChatController>();
  final messageController = TextEditingController();
  final scrollController = ScrollController();
  final selectedAttachments = <String>[].obs;

  @override
  void initState() {
    super.initState();
    chatController.fetchProjectMessages(widget.projectId);
  }

  @override
  void dispose() {
    messageController.dispose();
    scrollController.dispose();
    chatController.clearCurrentConversation();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.otherUser?.name ?? 'Conversation',
              style: const TextStyle(fontSize: 16),
            ),
            Text(
              widget.projectTitle,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.normal),
            ),
          ],
        ),
        actions: [
          if (widget.otherUser != null)
            CircleAvatar(
              radius: 18,
              backgroundColor: Colors.grey[300],
              backgroundImage: widget.otherUser!.avatar != null
                  ? NetworkImage(widget.otherUser!.avatar!)
                  : null,
              child: widget.otherUser!.avatar == null
                  ? Text(
                      widget.otherUser!.name.substring(0, 1).toUpperCase(),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    )
                  : null,
            ),
          const SizedBox(width: 16),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: Obx(() {
              if (chatController.isLoading.value &&
                  chatController.currentMessages.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }

              if (chatController.currentMessages.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.chat_bubble_outline,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Aucun message',
                        style: TextStyle(
                          fontSize: 18,
                          color: Colors.grey[600],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Démarrez la conversation',
                        style: TextStyle(
                          color: Colors.grey[500],
                          fontSize: 14,
                        ),
                      ),
                    ],
                  ),
                );
              }

              // Auto-scroll to bottom when new messages arrive
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (scrollController.hasClients) {
                  scrollController.animateTo(
                    scrollController.position.maxScrollExtent,
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeOut,
                  );
                }
              });

              return ListView.builder(
                controller: scrollController,
                padding: const EdgeInsets.all(16),
                itemCount: chatController.currentMessages.length,
                itemBuilder: (context, index) {
                  final message = chatController.currentMessages[index];
                  return _MessageBubble(message: message);
                },
              );
            }),
          ),
          Obx(() {
            if (selectedAttachments.isNotEmpty) {
              return _AttachmentPreview(
                attachments: selectedAttachments,
                onRemove: (index) {
                  selectedAttachments.removeAt(index);
                },
              );
            }
            return const SizedBox.shrink();
          }),
          _MessageInput(
            controller: messageController,
            onSend: _sendMessage,
            onAttach: _showAttachmentOptions,
            isSending: chatController.isSendingMessage.value,
          ),
        ],
      ),
    );
  }

  Future<void> _sendMessage() async {
    final message = messageController.text.trim();

    if (message.isEmpty && selectedAttachments.isEmpty) return;

    bool success;
    if (selectedAttachments.isNotEmpty) {
      success = await chatController.sendMessageWithAttachments(
        widget.projectId,
        message,
        selectedAttachments.toList(),
      );
    } else {
      success = await chatController.sendMessage(widget.projectId, message);
    }

    if (success) {
      messageController.clear();
      selectedAttachments.clear();

      // Scroll to bottom
      if (scrollController.hasClients) {
        scrollController.animateTo(
          scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    }
  }

  Future<void> _showAttachmentOptions() async {
    showModalBottomSheet(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Wrap(
            children: [
              ListTile(
                leading: const Icon(Icons.photo_library),
                title: const Text('Choisir depuis la galerie'),
                onTap: () async {
                  Navigator.pop(context);
                  final images = await chatController.pickImages();
                  if (images.isNotEmpty) {
                    selectedAttachments.addAll(images);
                  }
                },
              ),
              ListTile(
                leading: const Icon(Icons.camera_alt),
                title: const Text('Prendre une photo'),
                onTap: () async {
                  Navigator.pop(context);
                  final photo = await chatController.takePhoto();
                  if (photo != null) {
                    selectedAttachments.add(photo);
                  }
                },
              ),
            ],
          ),
        );
      },
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final ProjectMessage message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    final isOwnMessage = message.isOwnMessage;

    return Align(
      alignment: isOwnMessage ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.75,
        ),
        decoration: BoxDecoration(
          color: isOwnMessage ? Colors.blue[100] : Colors.grey[200],
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!isOwnMessage)
              Text(
                message.sender.name,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Colors.blue[900],
                  fontSize: 12,
                ),
              ),
            if (!isOwnMessage) const SizedBox(height: 4),
            if (message.attachments != null && message.attachments!.isNotEmpty)
              ...[
                _AttachmentDisplay(attachments: message.attachments!),
                const SizedBox(height: 8),
              ],
            Text(
              message.content,
              style: const TextStyle(fontSize: 15),
            ),
            const SizedBox(height: 4),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _formatTime(message.createdAt),
                  style: TextStyle(
                    fontSize: 10,
                    color: Colors.grey[600],
                  ),
                ),
                if (isOwnMessage && message.readAt != null) ...[
                  const SizedBox(width: 4),
                  Icon(
                    Icons.done_all,
                    size: 14,
                    color: Colors.blue[700],
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'À l\'instant';
    } else if (difference.inHours < 1) {
      return 'Il y a ${difference.inMinutes} min';
    } else if (difference.inDays < 1) {
      return 'Il y a ${difference.inHours}h';
    } else {
      return '${dateTime.day}/${dateTime.month} ${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
    }
  }
}

class _AttachmentDisplay extends StatelessWidget {
  final List<String> attachments;

  const _AttachmentDisplay({required this.attachments});

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: attachments.map((url) {
        return ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Image.network(
            url,
            width: 120,
            height: 120,
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) {
              return Container(
                width: 120,
                height: 120,
                color: Colors.grey[300],
                child: const Icon(Icons.broken_image),
              );
            },
          ),
        );
      }).toList(),
    );
  }
}

class _AttachmentPreview extends StatelessWidget {
  final RxList<String> attachments;
  final Function(int) onRemove;

  const _AttachmentPreview({
    required this.attachments,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: Colors.grey[100],
        border: Border(top: BorderSide(color: Colors.grey[300]!)),
      ),
      child: SizedBox(
        height: 80,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: attachments.length,
          itemBuilder: (context, index) {
            return Container(
              margin: const EdgeInsets.only(right: 8),
              child: Stack(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(
                      File(attachments[index]),
                      width: 80,
                      height: 80,
                      fit: BoxFit.cover,
                    ),
                  ),
                  Positioned(
                    top: 4,
                    right: 4,
                    child: GestureDetector(
                      onTap: () => onRemove(index),
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.black54,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.close,
                          size: 16,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _MessageInput extends StatelessWidget {
  final TextEditingController controller;
  final VoidCallback onSend;
  final VoidCallback onAttach;
  final bool isSending;

  const _MessageInput({
    required this.controller,
    required this.onSend,
    required this.onAttach,
    required this.isSending,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withOpacity(0.2),
            spreadRadius: 1,
            blurRadius: 5,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.attach_file),
            onPressed: isSending ? null : onAttach,
            color: Theme.of(context).primaryColor,
          ),
          Expanded(
            child: TextField(
              controller: controller,
              decoration: InputDecoration(
                hintText: 'Écrire un message...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
              ),
              maxLines: null,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => onSend(),
              enabled: !isSending,
            ),
          ),
          const SizedBox(width: 8),
          CircleAvatar(
            backgroundColor: Theme.of(context).primaryColor,
            child: isSending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : IconButton(
                    icon: const Icon(Icons.send, color: Colors.white),
                    onPressed: onSend,
                  ),
          ),
        ],
      ),
    );
  }
}
