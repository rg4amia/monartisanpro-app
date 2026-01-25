import 'package:flutter/material.dart';
import '../../domain/models/dispute.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';

/// Widget for displaying mediation chat messages
class MessageBubbleWidget extends StatelessWidget {
  final Communication message;
  final bool isCurrentUser;
  final bool isMediator;

  const MessageBubbleWidget({
    super.key,
    required this.message,
    required this.isCurrentUser,
    required this.isMediator,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        mainAxisAlignment: isCurrentUser
            ? MainAxisAlignment.end
            : MainAxisAlignment.start,
        children: [
          if (!isCurrentUser) ...[
            _buildAvatar(context),
            SizedBox(width: AppSpacing.sm),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(
                maxWidth: MediaQuery.of(context).size.width * 0.75,
              ),
              padding: EdgeInsets.symmetric(
                horizontal: AppSpacing.base,
                vertical: AppSpacing.md,
              ),
              decoration: BoxDecoration(
                color: _getBubbleColor(context),
                borderRadius: BorderRadius.circular(AppRadius.lg).copyWith(
                  bottomLeft: isCurrentUser
                      ? Radius.circular(AppRadius.lg)
                      : Radius.circular(AppRadius.sm),
                  bottomRight: isCurrentUser
                      ? Radius.circular(AppRadius.sm)
                      : Radius.circular(AppRadius.lg),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.1),
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
                      style: AppTypography.bodySmall.copyWith(
                        fontWeight: FontWeight.bold,
                        color: _getSenderLabelColor(context),
                      ),
                    ),
                    SizedBox(height: AppSpacing.xs),
                  ],
                  Text(
                    message.message,
                    style: AppTypography.body.copyWith(
                      color: isCurrentUser
                          ? Colors.white
                          : AppColors.textPrimary,
                    ),
                  ),
                  SizedBox(height: AppSpacing.xs),
                  Text(
                    _formatTime(message.sentAt),
                    style: AppTypography.caption.copyWith(
                      color: isCurrentUser
                          ? Colors.white.withValues(alpha: 0.7)
                          : AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isCurrentUser) ...[
            SizedBox(width: AppSpacing.sm),
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
        style: AppTypography.caption.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Color _getBubbleColor(BuildContext context) {
    if (isCurrentUser) {
      return AppColors.accentPrimary;
    } else if (isMediator) {
      return AppColors.accentPrimary.withValues(alpha: 0.1);
    } else {
      return AppColors.cardBg;
    }
  }

  Color _getAvatarColor(BuildContext context) {
    if (isCurrentUser) {
      return AppColors.accentPrimary;
    } else if (isMediator) {
      return AppColors.accentPrimary;
    } else {
      return AppColors.textSecondary;
    }
  }

  Color _getSenderLabelColor(BuildContext context) {
    if (isMediator) {
      return AppColors.accentPrimary;
    } else {
      return AppColors.textSecondary;
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
