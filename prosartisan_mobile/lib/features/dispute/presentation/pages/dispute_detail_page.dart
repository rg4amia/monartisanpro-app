import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
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

  const DisputeDetailPage({super.key, required this.disputeId});

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
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Détails du litige',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
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
          return Center(
            child: CircularProgressIndicator(color: AppColors.accentPrimary),
          );
        }

        final dispute = _controller.currentDispute.value;
        if (dispute == null) {
          return _buildErrorView();
        }

        return RefreshIndicator(
          onRefresh: () => _controller.loadDispute(widget.disputeId),
          color: AppColors.accentPrimary,
          child: SingleChildScrollView(
            padding: EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildDisputeHeader(dispute),
                SizedBox(height: AppSpacing.lg),
                _buildDisputeDetails(dispute),
                SizedBox(height: AppSpacing.lg),
                _buildEvidenceSection(dispute),
                if (dispute.mediation != null) ...[
                  SizedBox(height: AppSpacing.lg),
                  _buildMediationSection(dispute),
                ],
                if (dispute.arbitration != null) ...[
                  SizedBox(height: AppSpacing.lg),
                  _buildArbitrationSection(dispute),
                ],
                if (dispute.resolution != null) ...[
                  SizedBox(height: AppSpacing.lg),
                  _buildResolutionSection(dispute),
                ],
                SizedBox(height: AppSpacing.lg),
                _buildActionButtons(dispute),
              ],
            ),
          ),
        );
      }),
    );
  }

  Widget _buildErrorView() {
    return EmptyStateCard(
      icon: Icons.error_outline,
      title: 'Litige introuvable',
      subtitle: 'Le litige demandé n\'existe pas ou vous n\'avez pas accès.',
      actionText: 'Retour',
      onActionPressed: () => Navigator.pop(context),
    );
  }

  Widget _buildDisputeHeader(Dispute dispute) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                _controller.getTypeIcon(dispute.type),
                style: AppTypography.h3,
              ),
              SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  dispute.type.label,
                  style: AppTypography.sectionTitle.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              DisputeStatusChip(status: dispute.status),
            ],
          ),
          SizedBox(height: AppSpacing.base),
          Text(
            'Créé le ${_formatDate(dispute.createdAt)}',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          if (dispute.resolvedAt != null) ...[
            SizedBox(height: AppSpacing.xs),
            Text(
              'Résolu le ${_formatDate(dispute.resolvedAt!)}',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.accentSuccess,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildDisputeDetails(Dispute dispute) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Description',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.sm),
          Text(
            dispute.description,
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
          ),
          SizedBox(height: AppSpacing.base),
          _buildInfoRow('Mission ID', dispute.missionId),
          _buildInfoRow('Rapporteur', dispute.reporterId),
          _buildInfoRow('Défendeur', dispute.defendantId),
        ],
      ),
    );
  }

  Widget _buildEvidenceSection(Dispute dispute) {
    if (dispute.evidence.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Preuves (${dispute.evidence.length})',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.base),
          EvidenceViewerWidget(evidenceUrls: dispute.evidence),
        ],
      ),
    );
  }

  Widget _buildMediationSection(Dispute dispute) {
    final mediation = dispute.mediation!;

    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                mediation.isActive ? Icons.forum : Icons.forum_outlined,
                color: mediation.isActive
                    ? AppColors.accentPrimary
                    : AppColors.textSecondary,
              ),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Médiation',
                style: AppTypography.sectionTitle.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const Spacer(),
              Container(
                padding: EdgeInsets.symmetric(
                  horizontal: AppSpacing.md,
                  vertical: AppSpacing.xs,
                ),
                decoration: BoxDecoration(
                  color: mediation.isActive
                      ? AppColors.accentPrimary.withValues(alpha: 0.1)
                      : AppColors.textSecondary.withValues(alpha: 0.1),
                  borderRadius: AppRadius.badgeRadius,
                ),
                child: Text(
                  mediation.isActive ? 'Active' : 'Terminée',
                  style: AppTypography.badge.copyWith(
                    color: mediation.isActive
                        ? AppColors.accentPrimary
                        : AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.base),
          _buildInfoRow('Médiateur', mediation.mediatorId),
          _buildInfoRow('Démarrée le', _formatDate(mediation.startedAt)),
          if (mediation.endedAt != null)
            _buildInfoRow('Terminée le', _formatDate(mediation.endedAt!)),
          _buildInfoRow('Messages', '${mediation.communicationsCount}'),
          if (mediation.isActive) ...[
            SizedBox(height: AppSpacing.base),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _openMediationChat(dispute),
                icon: const Icon(Icons.chat),
                label: const Text('Ouvrir la discussion'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.accentPrimary,
                  foregroundColor: AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildArbitrationSection(Dispute dispute) {
    final arbitration = dispute.arbitration!;

    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.gavel, color: AppColors.accentWarning),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Arbitrage',
                style: AppTypography.sectionTitle.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.base),
          _buildInfoRow('Arbitre', arbitration.arbitratorId),
          _buildInfoRow('Décision', arbitration.decision.type.label),
          if (arbitration.decision.amount != null)
            _buildInfoRow(
              'Montant',
              arbitration.decision.amount!.formattedAmount,
            ),
          _buildInfoRow('Rendu le', _formatDate(arbitration.renderedAt)),
          SizedBox(height: AppSpacing.base),
          Text(
            'Justification:',
            style: AppTypography.body.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.xs),
          Text(
            arbitration.justification,
            style: AppTypography.body.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }

  Widget _buildResolutionSection(Dispute dispute) {
    final resolution = dispute.resolution!;

    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: AppRadius.cardRadius,
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.check_circle, color: AppColors.accentSuccess),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Résolution',
                style: AppTypography.sectionTitle.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.base),
          _buildInfoRow('Résultat', resolution.outcome),
          if (resolution.amount != null)
            _buildInfoRow('Montant', resolution.amount!.formattedAmount),
          _buildInfoRow('Résolu le', _formatDate(resolution.resolvedAt)),
          if (resolution.notes.isNotEmpty) ...[
            SizedBox(height: AppSpacing.base),
            Text(
              'Notes:',
              style: AppTypography.body.copyWith(
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: AppSpacing.xs),
            Text(
              resolution.notes,
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
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
              backgroundColor: AppColors.accentPrimary,
              foregroundColor: AppColors.textPrimary,
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
          .expand((button) => [button, SizedBox(height: AppSpacing.sm)])
          .take(buttons.length * 2 - 1)
          .toList(),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
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
