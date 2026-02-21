import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/message_controller.dart';
import '../../../../shared/models/message_model.dart';

class ChatScreen extends StatefulWidget {
  final int projectId;
  final String projectTitle;
  final ConversationUser? otherUser;

  const ChatScreen({
    super.key,
    required this.projectId,
    required this.projectTitle,
    this.otherUser,
  });

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _messageController = Get.find<MessageController>();
  final _textController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _loadMessages();
  }

  @override
  void dispose() {
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    await _messageController.fetchProjectMessages(widget.projectId);
    _scrollToBottom();
  }

  void _scrollToBottom() {
    if (_scrollController.hasClients) {
      Future.delayed(const Duration(milliseconds: 300), () {
        if (_scrollController.hasClients) {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      });
    }
  }

  Future<void> _sendMessage() async {
    final message = _textController.text.trim();
    if (message.isEmpty) return;

    _textController.clear();

    final success = await _messageController.sendMessage(
      projectId: widget.projectId,
      message: message,
    );

    if (success) {
      _scrollToBottom();
    } else {
      Get.snackbar(
        'Erreur',
        _messageController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.projectTitle, style: const TextStyle(fontSize: 16)),
            if (widget.otherUser != null)
              Text(
                widget.otherUser!.name,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.normal,
                ),
              ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.info_outline),
            onPressed: () {
              // TODO: Navigate to project details
              Get.snackbar(
                'Info',
                'Détails du projet',
                snackPosition: SnackPosition.TOP,
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Messages list
          Expanded(
            child: Obx(() {
              if (_messageController.isLoading.value &&
                  _messageController.currentMessages.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }

              if (_messageController.currentMessages.isEmpty) {
                return _buildEmptyState();
              }

              return ListView.builder(
                controller: _scrollController,
                padding: const EdgeInsets.all(Spacing.base),
                itemCount: _messageController.currentMessages.length,
                itemBuilder: (context, index) {
                  final message = _messageController.currentMessages[index];
                  final showDate =
                      index == 0 ||
                      !_isSameDay(
                        message.createdAt,
                        _messageController.currentMessages[index - 1].createdAt,
                      );

                  return Column(
                    children: [
                      if (showDate) _buildDateSeparator(message.createdAt),
                      _MessageBubble(message: message),
                    ],
                  );
                },
              );
            }),
          ),

          // Input area
          Container(
            decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -2),
                ),
              ],
            ),
            padding: EdgeInsets.only(
              left: Spacing.base,
              right: Spacing.base,
              top: Spacing.sm,
              bottom: MediaQuery.of(context).padding.bottom + Spacing.sm,
            ),
            child: Row(
              children: [
                // Attachment button (optional)
                IconButton(
                  icon: const Icon(Icons.attach_file),
                  onPressed: () {
                    Get.snackbar(
                      'Info',
                      'Pièces jointes disponibles prochainement',
                      snackPosition: SnackPosition.TOP,
                    );
                  },
                  color: AppColors.lightTextSecondary,
                ),

                // Text input
                Expanded(
                  child: TextField(
                    controller: _textController,
                    decoration: InputDecoration(
                      hintText: 'Votre message...',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide.none,
                      ),
                      filled: true,
                      fillColor: AppColors.lightBackground,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: Spacing.base,
                        vertical: Spacing.sm,
                      ),
                    ),
                    maxLines: null,
                    textCapitalization: TextCapitalization.sentences,
                    onSubmitted: (_) => _sendMessage(),
                  ),
                ),

                const SizedBox(width: Spacing.sm),

                // Send button
                Obx(
                  () => _messageController.isSending.value
                      ? const SizedBox(
                          width: 40,
                          height: 40,
                          child: Center(
                            child: SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          ),
                        )
                      : IconButton(
                          icon: const Icon(Icons.send),
                          onPressed: _sendMessage,
                          color: AppColors.lightAccentPrimary,
                          style: IconButton.styleFrom(
                            backgroundColor: AppColors.lightAccentPrimary
                                .withValues(alpha: 0.1),
                          ),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xxl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.chat_outlined,
              size: 64,
              color: AppColors.lightTextTertiary,
            ),
            const SizedBox(height: Spacing.lg),
            Text(
              'Aucun message',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              'Commencez la conversation',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDateSeparator(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);
    String label;

    if (diff.inDays == 0) {
      label = "Aujourd'hui";
    } else if (diff.inDays == 1) {
      label = 'Hier';
    } else if (diff.inDays < 7) {
      label = DateFormat('EEEE', 'fr_FR').format(date);
    } else {
      label = DateFormat('dd MMMM yyyy', 'fr_FR').format(date);
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Spacing.md),
      child: Center(
        child: Container(
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.base,
            vertical: Spacing.xs,
          ),
          decoration: BoxDecoration(
            color: AppColors.lightBackground,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.lightTextSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  bool _isSameDay(DateTime date1, DateTime date2) {
    return date1.year == date2.year &&
        date1.month == date2.month &&
        date1.day == date2.day;
  }
}

class _MessageBubble extends StatelessWidget {
  final ProjectMessage message;

  const _MessageBubble({required this.message});

  @override
  Widget build(BuildContext context) {
    final isOwn = message.isOwnMessage;

    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.sm),
      child: Row(
        mainAxisAlignment: isOwn
            ? MainAxisAlignment.end
            : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isOwn) ...[
            CircleAvatar(
              radius: 16,
              backgroundColor: AppColors.lightAccentSecondary.withValues(
                alpha: 0.1,
              ),
              backgroundImage: message.sender.avatar != null
                  ? NetworkImage(message.sender.avatar!)
                  : null,
              child: message.sender.avatar == null
                  ? Icon(
                      Icons.person,
                      size: 16,
                      color: AppColors.lightAccentSecondary,
                    )
                  : null,
            ),
            const SizedBox(width: Spacing.sm),
          ],
          Flexible(
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.base,
                vertical: Spacing.sm,
              ),
              decoration: BoxDecoration(
                color: isOwn
                    ? AppColors.lightAccentPrimary
                    : AppColors.lightBackground,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(16),
                  topRight: const Radius.circular(16),
                  bottomLeft: Radius.circular(isOwn ? 16 : 4),
                  bottomRight: Radius.circular(isOwn ? 4 : 16),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isOwn)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Text(
                        message.sender.name,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.lightAccentSecondary,
                        ),
                      ),
                    ),
                  Text(
                    message.content,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: isOwn ? Colors.white : AppColors.lightTextPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    DateFormat('HH:mm').format(message.createdAt),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      fontSize: 10,
                      color: isOwn
                          ? Colors.white.withValues(alpha: 0.7)
                          : AppColors.lightTextTertiary,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isOwn) const SizedBox(width: Spacing.sm),
        ],
      ),
    );
  }
}
