import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../shared/models/dispute_model.dart';
import '../../../../shared/controllers/dispute_controller.dart';

class DisputeDetailsScreen extends StatefulWidget {
  final Dispute dispute;

  const DisputeDetailsScreen({super.key, required this.dispute});

  @override
  State<DisputeDetailsScreen> createState() => _DisputeDetailsScreenState();
}

class _DisputeDetailsScreenState extends State<DisputeDetailsScreen> {
  final disputeController = Get.find<DisputeController>();
  final messageController = TextEditingController();
  final scrollController = ScrollController();

  @override
  void dispose() {
    messageController.dispose();
    scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Détails du Litige'),
        actions: [
          IconButton(
            icon: const Icon(Icons.info_outline),
            onPressed: () => _showInfoDialog(),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildStatusCard(),
                const SizedBox(height: 16),
                _buildInfoSection(),
                const SizedBox(height: 16),
                _buildEvidenceSection(),
                const SizedBox(height: 16),
                if (widget.dispute.resolution != null) _buildResolutionSection(),
                const SizedBox(height: 16),
                _buildMessagesSection(),
              ],
            ),
          ),
          if (widget.dispute.status != 'resolved' && widget.dispute.status != 'cancelled')
            _buildMessageInput(),
        ],
      ),
    );
  }

  Widget _buildStatusCard() {
    Color statusColor;
    IconData statusIcon;
    String statusText;

    switch (widget.dispute.status) {
      case 'open':
        statusColor = Colors.orange;
        statusIcon = Icons.folder_open;
        statusText = 'Litige Ouvert';
        break;
      case 'investigating':
        statusColor = Colors.blue;
        statusIcon = Icons.search;
        statusText = 'En Investigation';
        break;
      case 'resolved':
        statusColor = Colors.green;
        statusIcon = Icons.check_circle;
        statusText = 'Résolu';
        break;
      case 'cancelled':
        statusColor = Colors.red;
        statusIcon = Icons.cancel;
        statusText = 'Annulé';
        break;
      default:
        statusColor = Colors.grey;
        statusIcon = Icons.help;
        statusText = widget.dispute.status;
    }

    return Card(
      color: statusColor.withOpacity(0.1),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: statusColor.withOpacity(0.2),
                shape: BoxShape.circle,
              ),
              child: Icon(statusIcon, color: statusColor, size: 32),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    statusText,
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: statusColor,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Créé le ${_formatDate(widget.dispute.createdAt)}',
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            _buildPriorityBadge(),
          ],
        ),
      ),
    );
  }

  Widget _buildPriorityBadge() {
    Color color;
    String label;

    switch (widget.dispute.priority) {
      case 'low':
        color = Colors.green;
        label = 'Basse';
        break;
      case 'medium':
        color = Colors.orange;
        label = 'Moyenne';
        break;
      case 'high':
        color = Colors.red;
        label = 'Haute';
        break;
      case 'urgent':
        color = Colors.red[900]!;
        label = 'Urgente';
        break;
      default:
        color = Colors.grey;
        label = widget.dispute.priority;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _buildInfoSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Informations',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const Divider(),
            _buildInfoRow('Type', _getDisputeTypeLabel(widget.dispute.disputeType)),
            _buildInfoRow('Raison', widget.dispute.reason),
            const SizedBox(height: 12),
            const Text(
              'Description',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              widget.dispute.description,
              style: TextStyle(color: Colors.grey[700]),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(color: Colors.grey[700]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEvidenceSection() {
    if (widget.dispute.evidence == null || widget.dispute.evidence!.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Preuves',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemCount: widget.dispute.evidence!.length,
              itemBuilder: (context, index) {
                return ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    widget.dispute.evidence![index],
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) {
                      return Container(
                        color: Colors.grey[200],
                        child: const Icon(Icons.broken_image),
                      );
                    },
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResolutionSection() {
    return Card(
      color: Colors.green[50],
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.check_circle, color: Colors.green[700]),
                const SizedBox(width: 8),
                const Text(
                  'Résolution',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const Divider(),
            if (widget.dispute.resolutionType != null)
              _buildInfoRow(
                'Type',
                _getResolutionTypeLabel(widget.dispute.resolutionType!),
              ),
            if (widget.dispute.resolvedAt != null)
              _buildInfoRow(
                'Résolu le',
                _formatDate(widget.dispute.resolvedAt!),
              ),
            const SizedBox(height: 8),
            Text(
              widget.dispute.resolution!,
              style: TextStyle(color: Colors.grey[800]),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMessagesSection() {
    final messages = widget.dispute.messages ?? [];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Messages',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            if (messages.isEmpty)
              Center(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Text(
                    'Aucun message',
                    style: TextStyle(color: Colors.grey[500]),
                  ),
                ),
              )
            else
              ...messages.map((message) => _buildMessageBubble(message)),
          ],
        ),
      ),
    );
  }

  Widget _buildMessageBubble(DisputeMessage message) {
    final isAdminMessage = message.isAdminMessage;

    return Align(
      alignment: isAdminMessage ? Alignment.centerLeft : Alignment.centerRight,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.7,
        ),
        decoration: BoxDecoration(
          color: isAdminMessage ? Colors.grey[200] : Colors.blue[100],
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (isAdminMessage)
              Text(
                'Support',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Colors.blue[900],
                  fontSize: 12,
                ),
              ),
            if (isAdminMessage) const SizedBox(height: 4),
            Text(message.message),
            const SizedBox(height: 4),
            Text(
              _formatMessageDate(message.createdAt),
              style: TextStyle(
                fontSize: 10,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMessageInput() {
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
          Expanded(
            child: TextField(
              controller: messageController,
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
              onSubmitted: (_) => _sendMessage(),
            ),
          ),
          const SizedBox(width: 8),
          CircleAvatar(
            backgroundColor: Theme.of(context).primaryColor,
            child: IconButton(
              icon: const Icon(Icons.send, color: Colors.white),
              onPressed: _sendMessage,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _sendMessage() async {
    if (messageController.text.trim().isEmpty) return;

    final message = messageController.text.trim();
    messageController.clear();

    final success = await disputeController.sendMessage(
      widget.dispute.id,
      message,
    );

    if (success) {
      // Reload dispute details to get new messages
      final updatedDispute = await disputeController.getDisputeDetails(widget.dispute.id);
      if (updatedDispute != null) {
        setState(() {
          // Update would happen here if using state management properly
        });
      }
    }
  }

  void _showInfoDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('À propos du litige'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('ID: #${widget.dispute.id}'),
            const SizedBox(height: 8),
            Text('Statut: ${widget.dispute.status}'),
            const SizedBox(height: 8),
            Text('Créé: ${_formatDate(widget.dispute.createdAt)}'),
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

  String _getDisputeTypeLabel(String type) {
    switch (type) {
      case 'quality':
        return 'Qualité';
      case 'payment':
        return 'Paiement';
      case 'delay':
        return 'Retard';
      case 'fraud':
        return 'Fraude';
      default:
        return 'Autre';
    }
  }

  String _getResolutionTypeLabel(String type) {
    switch (type) {
      case 'refund':
        return 'Remboursement';
      case 'partial_refund':
        return 'Remboursement partiel';
      case 'release_funds':
        return 'Libération des fonds';
      case 'project_cancelled':
        return 'Projet annulé';
      default:
        return 'Autre';
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }

  String _formatMessageDate(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);

    if (diff.inMinutes < 1) {
      return 'À l\'instant';
    } else if (diff.inHours < 1) {
      return 'Il y a ${diff.inMinutes} min';
    } else if (diff.inDays < 1) {
      return 'Il y a ${diff.inHours}h';
    } else {
      return '${date.day}/${date.month} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
    }
  }
}
