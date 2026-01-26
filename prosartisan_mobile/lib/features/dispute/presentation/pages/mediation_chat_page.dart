import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../domain/models/dispute.dart';
import '../controllers/dispute_controller.dart';
import '../widgets/message_bubble_widget.dart';

/// Page for mediation chat communication
///
/// Requirement 9.5: Provide communication channel during mediation
class MediationChatPage extends StatefulWidget {
  final String disputeId;
  final Dispute dispute;

  const MediationChatPage({
    super.key,
    required this.disputeId,
    required this.dispute,
  });

  @override
  State<MediationChatPage> createState() => _MediationChatPageState();
}

class _MediationChatPageState extends State<MediationChatPage> {
  final DisputeController _controller = Get.find<DisputeController>();
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _controller.loadDispute(widget.disputeId);

    // Auto-scroll to bottom when new messages arrive
    ever(_controller.messages, (_) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollController.hasClients) {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      });
    });
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Médiation',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(Icons.info_outline, color: AppColors.textPrimary),
            onPressed: _showMediationInfo,
          ),
        ],
      ),
      body: Obx(() {
        if (_controller.isLoading.value) {
          return Center(
            child: CircularProgressIndicator(color: AppColors.accentPrimary),
          );
        }

        final dispute = _controller.currentDispute.value;
        if (dispute == null || dispute.mediation == null) {
          return _buildNoMediationView();
        }

        return Column(
          children: [
            _buildMediationHeader(dispute.mediation!),
            Expanded(child: _buildMessagesList()),
            _buildMessageInput(),
          ],
        );
      }),
    );
  }

  Widget _buildNoMediationView() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.forum_outlined, size: 64, color: AppColors.textMuted),
          SizedBox(height: AppSpacing.base),
          Text(
            'Aucune médiation active',
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          SizedBox(height: AppSpacing.sm),
          Padding(
            padding: EdgeInsets.symmetric(horizontal: AppSpacing.xl),
            child: Text(
              'La médiation n\'a pas encore été initiée pour ce litige.',
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
              textAlign: TextAlign.center,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMediationHeader(Mediation mediation) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: mediation.isActive
            ? AppColors.accentSuccess.withValues(alpha: 0.1)
            : AppColors.textSecondary.withValues(alpha: 0.1),
        border: Border(bottom: BorderSide(color: AppColors.overlayMedium)),
      ),
      child: Row(
        children: [
          Icon(
            mediation.isActive ? Icons.circle : Icons.circle_outlined,
            color: mediation.isActive
                ? AppColors.accentSuccess
                : AppColors.textSecondary,
            size: 12,
          ),
          SizedBox(width: AppSpacing.sm),
          Text(
            mediation.isActive ? 'Médiation active' : 'Médiation terminée',
            style: AppTypography.body.copyWith(
              fontWeight: FontWeight.bold,
              color: mediation.isActive
                  ? AppColors.accentSuccess
                  : AppColors.textSecondary,
            ),
          ),
          const Spacer(),
          Text(
            '${mediation.communicationsCount} messages',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessagesList() {
    return Obx(() {
      final messages = _controller.messages;

      if (messages.isEmpty) {
        return Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.chat_bubble_outline,
                size: 48,
                color: AppColors.textMuted,
              ),
              SizedBox(height: AppSpacing.base),
              Text(
                'Aucun message',
                style: AppTypography.sectionTitle.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
              SizedBox(height: AppSpacing.sm),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: AppSpacing.xl),
                child: Text(
                  'Commencez la conversation avec le médiateur',
                  style: AppTypography.body.copyWith(
                    color: AppColors.textSecondary,
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
            ],
          ),
        );
      }

      return ListView.builder(
        controller: _scrollController,
        padding: EdgeInsets.all(AppSpacing.base),
        itemCount: messages.length,
        itemBuilder: (context, index) {
          final message = messages[index];
          return MessageBubbleWidget(
            message: message,
            isCurrentUser: _isCurrentUser(message.senderId),
            isMediator: _isMediator(message.senderId),
          );
        },
      );
    });
  }

  Widget _buildMessageInput() {
    return Obx(() {
      final dispute = _controller.currentDispute.value;
      final mediation = dispute?.mediation;

      if (mediation == null || !mediation.isActive) {
        return Container(
          padding: EdgeInsets.all(AppSpacing.base),
          decoration: BoxDecoration(
            color: AppColors.cardBg,
            border: Border(top: BorderSide(color: AppColors.overlayMedium)),
          ),
          child: Row(
            children: [
              Icon(Icons.info_outline, color: AppColors.textSecondary),
              SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  'La médiation n\'est plus active',
                  style: AppTypography.body.copyWith(
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
        );
      }

      return Container(
        padding: EdgeInsets.all(AppSpacing.base),
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          border: Border(top: BorderSide(color: AppColors.overlayMedium)),
        ),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _messageController,
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
                decoration: InputDecoration(
                  hintText: 'Tapez votre message...',
                  hintStyle: AppTypography.body.copyWith(
                    color: AppColors.textMuted,
                  ),
                  filled: true,
                  fillColor: AppColors.elevatedBg,
                  border: OutlineInputBorder(
                    borderRadius: AppRadius.inputRadius,
                    borderSide: BorderSide(color: AppColors.overlayMedium),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: AppRadius.inputRadius,
                    borderSide: BorderSide(color: AppColors.overlayMedium),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: AppRadius.inputRadius,
                    borderSide: BorderSide(
                      color: AppColors.accentPrimary,
                      width: 2,
                    ),
                  ),
                  contentPadding: EdgeInsets.symmetric(
                    horizontal: AppSpacing.base,
                    vertical: AppSpacing.md,
                  ),
                ),
                maxLines: null,
                textCapitalization: TextCapitalization.sentences,
                onChanged: (value) => _controller.setMessageText(value),
              ),
            ),
            SizedBox(width: AppSpacing.sm),
            Obx(
              () => IconButton(
                onPressed:
                    _controller.isSubmitting.value ||
                        _controller.messageText.value.trim().isEmpty
                    ? null
                    : _sendMessage,
                icon: _controller.isSubmitting.value
                    ? SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(Icons.send, color: Colors.white),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.accentPrimary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: AppRadius.circular(
                      AppSpacing.minTouchTarget / 2,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    });
  }

  void _showMediationInfo() {
    final dispute = _controller.currentDispute.value;
    final mediation = dispute?.mediation;

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.cardBg,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.largeRadius),
        title: Text(
          'Informations sur la médiation',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (mediation != null) ...[
              _buildInfoRow(
                'Statut',
                mediation.isActive ? 'Active' : 'Terminée',
              ),
              _buildInfoRow('Démarrée le', _formatDate(mediation.startedAt)),
              if (mediation.endedAt != null)
                _buildInfoRow('Terminée le', _formatDate(mediation.endedAt!)),
              _buildInfoRow('Messages', '${mediation.communicationsCount}'),
            ] else ...[
              Text(
                'Aucune médiation active pour ce litige.',
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(
              'Fermer',
              style: AppTypography.button.copyWith(
                color: AppColors.accentPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              '$label:',
              style: AppTypography.body.copyWith(
                fontWeight: FontWeight.bold,
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _sendMessage() async {
    final success = await _controller.sendMediationMessage(widget.disputeId);
    if (success) {
      _messageController.clear();
    }
  }

  bool _isCurrentUser(String senderId) {
    // This should check against the current user ID
    // Implementation depends on your auth service
    return false; // Placeholder
  }

  bool _isMediator(String senderId) {
    final mediation = _controller.currentDispute.value?.mediation;
    return mediation != null && mediation.mediatorId == senderId;
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year} à ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }
}
