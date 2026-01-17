import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/controllers/worksite_controller.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/pages/photo_capture_page.dart';

/// Milestone proof submission screen for artisans
///
/// Allows artisans to submit proof of work completion for milestones
/// Requirements: 6.2, 6.3
class MilestoneProofSubmissionPage extends StatefulWidget {
  final Jalon jalon;

  const MilestoneProofSubmissionPage({super.key, required this.jalon});

  @override
  State<MilestoneProofSubmissionPage> createState() =>
      _MilestoneProofSubmissionPageState();
}

class _MilestoneProofSubmissionPageState
    extends State<MilestoneProofSubmissionPage> {
  final WorksiteController _controller = Get.find<WorksiteController>();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Soumettre une Preuve'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildJalonInfo(context),
            const SizedBox(height: 24),
            _buildStatusInfo(context),
            const SizedBox(height: 24),
            _buildInstructions(context),
            const SizedBox(height: 24),
            if (widget.jalon.hasProof) ...[
              _buildExistingProof(context),
              const SizedBox(height: 24),
            ],
            _buildActionButton(context),
          ],
        ),
      ),
    );
  }

  Widget _buildJalonInfo(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Theme.of(context).primaryColor,
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      '${widget.jalon.sequenceNumber}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 18,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Jalon ${widget.jalon.sequenceNumber}',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        widget.jalon.statusLabel,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: _getStatusColor(widget.jalon.status),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              widget.jalon.description,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.green[200]!),
              ),
              child: Row(
                children: [
                  Icon(Icons.payments, color: Colors.green[700]),
                  const SizedBox(width: 8),
                  Text(
                    'Montant à libérer: ${widget.jalon.laborAmount.formatted}',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.green[700],
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusInfo(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'État du jalon',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            _buildStatusStep('Travail en cours', widget.jalon.isPending, true),
            _buildStatusStep(
              'Preuve soumise',
              widget.jalon.isSubmitted,
              widget.jalon.hasProof,
            ),
            _buildStatusStep(
              'Validation client',
              widget.jalon.isValidated,
              widget.jalon.isSubmitted,
            ),
            _buildStatusStep(
              'Paiement libéré',
              widget.jalon.isCompleted,
              widget.jalon.isValidated,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusStep(String title, bool isCompleted, bool isActive) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isCompleted
                  ? Colors.green
                  : isActive
                  ? Colors.orange
                  : Colors.grey[300],
            ),
            child: isCompleted
                ? const Icon(Icons.check, color: Colors.white, size: 14)
                : isActive
                ? const Icon(
                    Icons.radio_button_unchecked,
                    color: Colors.white,
                    size: 14,
                  )
                : null,
          ),
          const SizedBox(width: 12),
          Text(
            title,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: isCompleted
                  ? Colors.green
                  : isActive
                  ? Colors.orange
                  : Colors.grey[600],
              fontWeight: isActive ? FontWeight.w500 : null,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInstructions(BuildContext context) {
    return Card(
      color: Colors.blue[50],
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.info, color: Colors.blue[700]),
                const SizedBox(width: 8),
                Text(
                  'Instructions',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: Colors.blue[700],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildInstructionItem('Prenez une photo claire du travail terminé'),
            _buildInstructionItem(
              'Assurez-vous que la localisation GPS est activée',
            ),
            _buildInstructionItem(
              'La précision GPS doit être inférieure à 10 mètres',
            ),
            _buildInstructionItem(
              'Le client aura 48h pour valider ou contester',
            ),
            _buildInstructionItem(
              'Validation automatique après 48h sans réponse',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInstructionItem(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 6,
            height: 6,
            margin: const EdgeInsets.only(top: 6),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.blue[700],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: Colors.blue[700]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExistingProof(BuildContext context) {
    final proof = widget.jalon.proof!;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Preuve déjà soumise',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.green[200]!),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.check_circle, color: Colors.green[700]),
                      const SizedBox(width: 8),
                      Text(
                        'Preuve soumise avec succès',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Colors.green[700],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Soumis le ${_formatDateTime(proof.capturedAt)}',
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: Colors.green[600]),
                  ),
                  Text(
                    'Localisation: ${proof.location.latitude.toStringAsFixed(4)}, ${proof.location.longitude.toStringAsFixed(4)}',
                    style: Theme.of(
                      context,
                    ).textTheme.bodySmall?.copyWith(color: Colors.green[600]),
                  ),
                ],
              ),
            ),

            if (widget.jalon.autoValidationDeadline != null) ...[
              const SizedBox(height: 16),
              _buildAutoValidationInfo(context),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildAutoValidationInfo(BuildContext context) {
    final hoursRemaining = widget.jalon.hoursUntilAutoValidation ?? 0;
    final isUrgent = hoursRemaining <= 6;
    final isPastDeadline = widget.jalon.isAutoValidationDue;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isPastDeadline
            ? Colors.green[50]
            : (isUrgent ? Colors.orange[50] : Colors.blue[50]),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isPastDeadline
              ? Colors.green[200]!
              : (isUrgent ? Colors.orange[200]! : Colors.blue[200]!),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isPastDeadline ? Icons.check_circle : Icons.timer,
                color: isPastDeadline
                    ? Colors.green[700]
                    : (isUrgent ? Colors.orange[700] : Colors.blue[700]),
              ),
              const SizedBox(width: 8),
              Text(
                isPastDeadline
                    ? 'Validation automatique'
                    : 'En attente de validation',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: isPastDeadline
                      ? Colors.green[700]
                      : (isUrgent ? Colors.orange[700] : Colors.blue[700]),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          if (isPastDeadline) ...[
            Text(
              'Ce jalon sera validé automatiquement',
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: Colors.green[600]),
            ),
          ] else ...[
            Text(
              'Temps restant: ${hoursRemaining.toStringAsFixed(1)} heures',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: isUrgent ? Colors.orange[600] : Colors.blue[600],
              ),
            ),
            Text(
              'Échéance: ${_formatDateTime(widget.jalon.autoValidationDeadline!)}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: isUrgent ? Colors.orange[600] : Colors.blue[600],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildActionButton(BuildContext context) {
    if (widget.jalon.isCompleted) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: null,
          icon: const Icon(Icons.check_circle),
          label: const Text('Jalon terminé'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.green,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
        ),
      );
    }

    if (widget.jalon.isContested) {
      return Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.red[50],
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.red[200]!),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.warning, color: Colors.red[700]),
                    const SizedBox(width: 8),
                    Text(
                      'Jalon contesté',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: Colors.red[700],
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
                if (widget.jalon.contestReason != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    'Motif: ${widget.jalon.contestReason}',
                    style: Theme.of(
                      context,
                    ).textTheme.bodyMedium?.copyWith(color: Colors.red[600]),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _navigateToPhotoCapture(),
              icon: const Icon(Icons.camera_alt),
              label: const Text('Soumettre une nouvelle preuve'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Theme.of(context).primaryColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
            ),
          ),
        ],
      );
    }

    if (widget.jalon.hasProof) {
      return SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          onPressed: () => _navigateToPhotoCapture(),
          icon: const Icon(Icons.camera_alt),
          label: const Text('Modifier la preuve'),
          style: OutlinedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
        ),
      );
    }

    return SizedBox(
      width: double.infinity,
      child: ElevatedButton.icon(
        onPressed: () => _navigateToPhotoCapture(),
        icon: const Icon(Icons.camera_alt),
        label: const Text('Prendre une photo'),
        style: ElevatedButton.styleFrom(
          backgroundColor: Theme.of(context).primaryColor,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
        ),
      ),
    );
  }

  void _navigateToPhotoCapture() {
    Get.to(
      () => PhotoCapturePage(
        jalonId: widget.jalon.id,
        jalonDescription: widget.jalon.description,
      ),
    )?.then((result) {
      if (result == true) {
        // Refresh the jalon data
        _controller.loadJalon(widget.jalon.id);
      }
    });
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PENDING':
        return Colors.grey;
      case 'SUBMITTED':
        return Colors.orange;
      case 'VALIDATED':
        return Colors.green;
      case 'CONTESTED':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day.toString().padLeft(2, '0')}/${dateTime.month.toString().padLeft(2, '0')}/${dateTime.year} ${dateTime.hour.toString().padLeft(2, '0')}:${dateTime.minute.toString().padLeft(2, '0')}';
  }
}
