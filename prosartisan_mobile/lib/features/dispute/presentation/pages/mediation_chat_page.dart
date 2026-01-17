import 'package:flutter/material.dart';
import 'package:get/get.dart';
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
    Key? key,
    required this.disputeId,
    required this.dispute,
  }) : super(key: key);

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
      appBar: AppBar(
        title: const Text('Médiation'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.info_outline),
            onPressed: _showMediationInfo,
          ),
        ],
      ),
      body: Obx(() {
        if (_controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
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
          Icon(Icons.forum_outlined, size: 64, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Aucune médiation active',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(color: Colors.grey[600]),
          ),
          const SizedBox(height: 8),
          Text(
            'La médiation n\'a pas encore été initiée pour ce litige.',
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildMediationHeader(Mediation mediation) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: mediation.isActive
            ? Colors.green.withOpacity(0.1)
            : Colors.grey.withOpacity(0.1),
        border: Border(bottom: BorderSide(color: Colors.grey[300]!)),
      ),
      child: Row(
        children: [
          Icon(
            mediation.isActive ? Icons.circle : Icons.circle_outlined,
            color: mediation.isActive ? Colors.green : Colors.grey,
            size: 12,
          ),
          const SizedBox(width: 8),
          Text(
            mediation.isActive ? 'Médiation active' : 'Médiation terminée',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: mediation.isActive ? Colors.green[700] : Colors.grey[700],
            ),
          ),
          const Spacer(),
          Text(
            '${mediation.communicationsCount} messages',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
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
                color: Colors.grey[400],
              ),
              const SizedBox(height: 16),
              Text(
                'Aucun message',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(color: Colors.grey[600]),
              ),
              const SizedBox(height: 8),
              Text(
                'Commencez la conversation avec le médiateur',
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        );
      }

      return ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
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
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.grey[100],
            border: Border(top: BorderSide(color: Colors.grey[300]!)),
          ),
          child: Row(
            children: [
              Icon(Icons.info_outline, color: Colors.grey[600]),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'La médiation n\'est plus active',
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ),
            ],
          ),
        );
      }

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: Colors.grey[300]!)),
        ),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _messageController,
                decoration: const InputDecoration(
                  hintText: 'Tapez votre message...',
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                ),
                maxLines: null,
                textCapitalization: TextCapitalization.sentences,
                onChanged: (value) => _controller.setMessageText(value),
              ),
            ),
            const SizedBox(width: 8),
            Obx(
              () => IconButton(
                onPressed:
                    _controller.isSubmitting.value ||
                        _controller.messageText.value.trim().isEmpty
                    ? null
                    : _sendMessage,
                icon: _controller.isSubmitting.value
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send),
                style: IconButton.styleFrom(
                  backgroundColor: Theme.of(context).primaryColor,
                  foregroundColor: Colors.white,
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
        title: const Text('Informations sur la médiation'),
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
              const Text('Aucune médiation active pour ce litige.'),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fermer'),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              '$label:',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
          Expanded(child: Text(value)),
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
