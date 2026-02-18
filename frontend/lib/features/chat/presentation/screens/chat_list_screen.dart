import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../shared/models/message_model.dart';
import '../../../../shared/controllers/chat_controller.dart';
import 'chat_conversation_screen.dart';

class ChatListScreen extends StatelessWidget {
  const ChatListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final chatController = Get.put(ChatController());

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        actions: [
          Obx(() {
            final unreadCount = chatController.getTotalUnreadCount();
            if (unreadCount > 0) {
              return Center(
                child: Container(
                  margin: const EdgeInsets.only(right: 16),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.red,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    unreadCount > 99 ? '99+' : unreadCount.toString(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              );
            }
            return const SizedBox.shrink();
          }),
        ],
      ),
      body: Obx(() {
        if (chatController.isLoading.value && chatController.conversations.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (chatController.conversations.isEmpty) {
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
                  'Aucune conversation',
                  style: TextStyle(
                    fontSize: 18,
                    color: Colors.grey[600],
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Vos conversations avec les artisans\napparaîtront ici',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.grey[500],
                    fontSize: 14,
                  ),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: chatController.fetchConversations,
          child: ListView.separated(
            itemCount: chatController.conversations.length,
            separatorBuilder: (context, index) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final conversation = chatController.conversations[index];
              return _ConversationTile(
                conversation: conversation,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => ChatConversationScreen(
                        projectId: conversation.id,
                        projectTitle: conversation.title,
                        otherUser: conversation.otherUser,
                      ),
                    ),
                  );
                },
              );
            },
          ),
        );
      }),
    );
  }
}

class _ConversationTile extends StatelessWidget {
  final Conversation conversation;
  final VoidCallback onTap;

  const _ConversationTile({
    required this.conversation,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasUnread = conversation.unreadCount > 0;

    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        radius: 28,
        backgroundColor: Colors.grey[300],
        backgroundImage: conversation.otherUser?.avatar != null
            ? NetworkImage(conversation.otherUser!.avatar!)
            : null,
        child: conversation.otherUser?.avatar == null
            ? Text(
                conversation.otherUser?.name.substring(0, 1).toUpperCase() ?? '?',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              )
            : null,
      ),
      title: Row(
        children: [
          Expanded(
            child: Text(
              conversation.otherUser?.name ?? 'Utilisateur inconnu',
              style: TextStyle(
                fontWeight: hasUnread ? FontWeight.bold : FontWeight.w500,
                fontSize: 16,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          if (hasUnread)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                conversation.unreadCount > 9 ? '9+' : conversation.unreadCount.toString(),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
        ],
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 4),
          Text(
            conversation.title,
            style: TextStyle(
              color: Colors.grey[700],
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          if (conversation.tradeName != null) ...[
            const SizedBox(height: 2),
            Text(
              conversation.tradeName!,
              style: TextStyle(
                color: Colors.grey[500],
                fontSize: 12,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (conversation.lastMessage != null) ...[
            const SizedBox(height: 4),
            Text(
              conversation.lastMessage!.message,
              style: TextStyle(
                color: hasUnread ? Colors.black87 : Colors.grey[600],
                fontSize: 14,
                fontWeight: hasUnread ? FontWeight.w500 : FontWeight.normal,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ],
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            _formatTime(conversation.updatedAt),
            style: TextStyle(
              color: hasUnread ? Theme.of(context).primaryColor : Colors.grey[500],
              fontSize: 12,
              fontWeight: hasUnread ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          const SizedBox(height: 4),
          _ProjectStatusBadge(status: conversation.status),
        ],
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    );
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Maintenant';
    } else if (difference.inHours < 1) {
      return '${difference.inMinutes}m';
    } else if (difference.inDays < 1) {
      return '${difference.inHours}h';
    } else if (difference.inDays < 7) {
      return '${difference.inDays}j';
    } else {
      return '${dateTime.day}/${dateTime.month}';
    }
  }
}

class _ProjectStatusBadge extends StatelessWidget {
  final String status;

  const _ProjectStatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;

    switch (status) {
      case 'pending':
        color = Colors.orange;
        label = 'En attente';
        break;
      case 'in_progress':
        color = Colors.blue;
        label = 'En cours';
        break;
      case 'completed':
        color = Colors.green;
        label = 'Terminé';
        break;
      case 'cancelled':
        color = Colors.red;
        label = 'Annulé';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3), width: 1),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
