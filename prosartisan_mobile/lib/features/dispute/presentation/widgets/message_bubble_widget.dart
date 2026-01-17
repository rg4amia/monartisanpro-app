import 'package:flutter/material.dart';
import '../../domain/models/dispute.dart';

/// Widget for displaying mediation chat messages
class MessageBubbleWidget extends StatelessWidget {
  final Communication message;
  final bool isCurrentUser;
  final bool isMediator;

  const MessageBubbleWidget({
    Key? key,
    required this.message,
    required this.isCurrentUser,
    required this.isMediator,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: isCurrentUser
            ? MainAxisAlignment.end
            : MainAxisAlignment.start,
        children: [
          if (!isCurrentUser) ...[
            _buildAvatar(context),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(
                maxWidth: MediaQuery.of(context).size.width * 0.75,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: _getBubbleColor(context),
                borderRadius: BorderRadius.circular(20).copyWith(
                  bottomLeft: isCurrentUser
                      ? const Radius.circular(20)
                      : const Radius.circular(4),
                  bottomRight: isCurrentUser
                      ? const Radius.circular(4)
                      : const Radius.circular(20),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (!isCurrentUser) ...[
                    Text(
                      _getSenderLabel(),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: _getSenderLabelColor(context),
                      ),
                    ),
                    const SizedBox(height: 4),
                  ],
                  Text(
                    message.message,
                    style: TextStyle(
                      color: isCurrentUser ? Colors.white : Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _formatTime(message.sentAt),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: isCurrentUser
                          ? Colors.white.withOpacity(0.7)
                          : Colors.grey[600],
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isCurrentUser) ...[
            const SizedBox(width: 8),
            _buildAvatar(context),
          ],
        ],
      ),
    );
  }

  Widget _buildAvatar(BuildContext context) {
    return CircleAvatar(
      radius: 16,
      backgroundColor: _getAvatarColor(context),
      child: Text(
        _getAvatarText(),
        style: const TextStyle(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Color _getBubbleColor(BuildContext context) {
    if (isCurrentUser) {
      return Theme.of(context).primaryColor;
    } else if (isMediator) {
      return Colors.blue[100]!;
    } else {
      return Colors.grey[200]!;
    }
  }

  Color _getAvatarColor(BuildContext context) {
    if (isCurrentUser) {
      return Theme.of(context).primaryColor;
    } else if (isMediator) {
      return Colors.blue;
    } else {
      return Colors.grey[600]!;
    }
  }

  Color _getSenderLabelColor(BuildContext context) {
    if (isMediator) {
      return Colors.blue[700]!;
    } else {
      return Colors.grey[700]!;
    }
  }

  String _getSenderLabel() {
    if (isMediator) {
      return 'Médiateur';
    } else {
      return 'Partie adverse';
    }
  }

  String _getAvatarText() {
    if (isCurrentUser) {
      return 'V'; // Vous
    } else if (isMediator) {
      return 'M'; // Médiateur
    } else {
      return 'P'; // Partie
    }
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inDays > 0) {
      return '${dateTime.day}/${dateTime.month} ${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
    } else if (difference.inHours > 0) {
      return '${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
    } else if (difference.inMinutes > 0) {
      return 'Il y a ${difference.inMinutes} min';
    } else {
      return 'À l\'instant';
    }
  }
}
