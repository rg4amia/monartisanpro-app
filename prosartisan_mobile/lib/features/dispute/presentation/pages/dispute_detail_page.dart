import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../domain/models/dispute.dart';
import '../controllers/dispute_controller.dart';
import '../widgets/dispute_status_chip.dart';
import '../widgets/evidence_viewer_widget.dart';
import 'mediation_chat_page.dart';

/// Page for viewing dispute details (admin and involved parties)
///
/// Requirements: 9.1, 9.5
class DisputeDetailPage extends StatefulWidget {
  final String disputeId;

  const DisputeDetailPage({Key? key, required this.disputeId})
    : super(key: key);

  @override
  State<DisputeDetailPage> createState() => _DisputeDetailPageState();
}

class _DisputeDetailPageState extends State<DisputeDetailPage> {
  final DisputeController _controller = Get.find<DisputeController>();

  @override
  void initState() {
    super.initState();
    _controller.loadDispute(widget.disputeId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Détails du litige'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        actions: [
          if (_controller.isAdmin)
            PopupMenuButton<String>(
              onSelected: _handleAdminAction,
              itemBuilder: (context) => [
                const PopupMenuItem(
                  value: 'start_mediation',
                  child: Text('Démarrer médiation'),
                ),
                const PopupMenuItem(
                  value: 'render_arbitration',
                  child: Text('Rendre arbitrage'),
                ),
              ],
            ),
        ],
      ),
      body: Obx(() {
        if (_controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final dispute = _controller.currentDispute.value;
        if (dispute == null) {
          return _buildErrorView();
        }

        return RefreshIndicator(
          onRefresh: () => _controller.loadDispute(widget.disputeId),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildDisputeHeader(dispute),
                const SizedBox(height: 24),
                _buildDisputeDetails(dispute),
                const SizedBox(height: 24),
                _buildEvidenceSection(dispute),
                if (dispute.mediation != null) ...[
                  const SizedBox(height: 24),
                  _buildMediationSection(dispute),
                ],
                if (dispute.arbitration != null) ...[
                  const SizedBox(height: 24),
                  _buildArbitrationSection(dispute),
                ],
                if (dispute.resolution != null) ...[
                  const SizedBox(height: 24),
                  _buildResolutionSection(dispute),
                ],
                const SizedBox(height: 24),
                _buildActionButtons(dispute),
              ],
            ),
          ),
        );
      }),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 64, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Litige introuvable',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(color: Colors.grey[600]),
          ),
          const SizedBox(height: 8),
          Text(
            'Le litige demandé n\'existe pas ou vous n\'avez pas accès.',
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Retour'),
          ),
        ],
      ),
    );
  }

  Widget _buildDisputeHeader(Dispute dispute) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  _controller.getTypeIcon(dispute.type),
                  style: const TextStyle(fontSize: 24),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    dispute.type.label,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                DisputeStatusChip(status: dispute.status),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              'Créé le ${_formatDate(dispute.createdAt)}',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
            ),
            if (dispute.resolvedAt != null) ...[
              const SizedBox(height: 4),
              Text(
                'Résolu le ${_formatDate(dispute.resolvedAt!)}',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: Colors.green[600]),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildDisputeDetails(Dispute dispute) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Description',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              dispute.description,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            _buildInfoRow('Mission ID', dispute.missionId),
            _buildInfoRow('Rapporteur', dispute.reporterId),
            _buildInfoRow('Défendeur', dispute.defendantId),
          ],
        ),
      ),
    );
  }

  Widget _buildEvidenceSection(Dispute dispute) {
    if (dispute.evidence.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Preuves (${dispute.evidence.length})',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            EvidenceViewerWidget(evidenceUrls: dispute.evidence),
          ],
        ),
      ),
    );
  }

  Widget _buildMediationSection(Dispute dispute) {
    final mediation = dispute.mediation!;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  mediation.isActive ? Icons.forum : Icons.forum_outlined,
                  color: mediation.isActive ? Colors.blue : Colors.grey,
                ),
                const SizedBox(width: 8),
                Text(
                  'Médiation',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Spacer(),
                Chip(
                  label: Text(mediation.isActive ? 'Active' : 'Terminée'),
                  backgroundColor: mediation.isActive
                      ? Colors.blue.withOpacity(0.1)
                      : Colors.grey.withOpacity(0.1),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _buildInfoRow('Médiateur', mediation.mediatorId),
            _buildInfoRow('Démarrée le', _formatDate(mediation.startedAt)),
            if (mediation.endedAt != null)
              _buildInfoRow('Terminée le', _formatDate(mediation.endedAt!)),
            _buildInfoRow('Messages', '${mediation.communicationsCount}'),
            if (mediation.isActive) ...[
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _openMediationChat(dispute),
                  icon: const Icon(Icons.chat),
                  label: const Text('Ouvrir la discussion'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildArbitrationSection(Dispute dispute) {
    final arbitration = dispute.arbitration!;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.gavel, color: Colors.purple),
                const SizedBox(width: 8),
                Text(
                  'Arbitrage',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _buildInfoRow('Arbitre', arbitration.arbitratorId),
            _buildInfoRow('Décision', arbitration.decision.type.label),
            if (arbitration.decision.amount != null)
              _buildInfoRow(
                'Montant',
                arbitration.decision.amount!.formattedAmount,
              ),
            _buildInfoRow('Rendu le', _formatDate(arbitration.renderedAt)),
            const SizedBox(height: 16),
            Text(
              'Justification:',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              arbitration.justification,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildResolutionSection(Dispute dispute) {
    final resolution = dispute.resolution!;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.green),
                const SizedBox(width: 8),
                Text(
                  'Résolution',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _buildInfoRow('Résultat', resolution.outcome),
            if (resolution.amount != null)
              _buildInfoRow('Montant', resolution.amount!.formattedAmount),
            _buildInfoRow('Résolu le', _formatDate(resolution.resolvedAt)),
            if (resolution.notes.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text(
                'Notes:',
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 4),
              Text(
                resolution.notes,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildActionButtons(Dispute dispute) {
    final buttons = <Widget>[];

    if (dispute.mediation != null && dispute.mediation!.isActive) {
      buttons.add(
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: () => _openMediationChat(dispute),
            icon: const Icon(Icons.chat),
            label: const Text('Ouvrir la médiation'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.blue,
              foregroundColor: Colors.white,
            ),
          ),
        ),
      );
    }

    if (buttons.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      children: buttons
          .expand((button) => [button, const SizedBox(height: 8)])
          .take(buttons.length * 2 - 1)
          .toList(),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              '$label:',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.bold,
                color: Colors.grey[700],
              ),
            ),
          ),
          Expanded(
            child: Text(value, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }

  void _openMediationChat(Dispute dispute) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) =>
            MediationChatPage(disputeId: dispute.id, dispute: dispute),
      ),
    );
  }

  void _handleAdminAction(String action) {
    switch (action) {
      case 'start_mediation':
        _showStartMediationDialog();
        break;
      case 'render_arbitration':
        _showRenderArbitrationDialog();
        break;
    }
  }

  void _showStartMediationDialog() {
    // Implementation for starting mediation
    // This would show a dialog to select a mediator
    Get.snackbar(
      'Info',
      'Fonctionnalité en cours de développement',
      snackPosition: SnackPosition.BOTTOM,
    );
  }

  void _showRenderArbitrationDialog() {
    // Implementation for rendering arbitration
    // This would show a dialog to enter decision details
    Get.snackbar(
      'Info',
      'Fonctionnalité en cours de développement',
      snackPosition: SnackPosition.BOTTOM,
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year} à ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }
}
