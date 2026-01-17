import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/controllers/worksite_controller.dart';

/// Milestone validation screen for clients
///
/// Allows clients to validate or contest milestone submissions
/// Requirements: 6.3
class MilestoneValidationPage extends StatefulWidget {
  final Jalon jalon;

  const MilestoneValidationPage({super.key, required this.jalon});

  @override
  State<MilestoneValidationPage> createState() =>
      _MilestoneValidationPageState();
}

class _MilestoneValidationPageState extends State<MilestoneValidationPage> {
  final WorksiteController _controller = Get.find<WorksiteController>();
  final TextEditingController _commentController = TextEditingController();
  final TextEditingController _contestReasonController =
      TextEditingController();

  bool _isValidating = false;
  bool _isContesting = false;
  bool _showContestForm = false;

  @override
  void dispose() {
    _commentController.dispose();
    _contestReasonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Validation du Jalon'),
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
            _buildProofSection(context),
            const SizedBox(height: 24),
            _buildAutoValidationInfo(context),
            const SizedBox(height: 24),
            if (_showContestForm) ...[
              _buildContestForm(context),
              const SizedBox(height: 24),
            ],
            _buildActionButtons(context),
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
                  width: 32,
                  height: 32,
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
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Jalon ${widget.jalon.sequenceNumber}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.bold),
                      ),
                      Text(
                        widget.jalon.statusLabel,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
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
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Icon(Icons.payments, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 8),
                Text(
                  'Montant: ${widget.jalon.laborAmount.formatted}',
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProofSection(BuildContext context) {
    final proof = widget.jalon.proof;

    if (proof == null) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              Icon(Icons.photo_camera, size: 48, color: Colors.grey[400]),
              const SizedBox(height: 16),
              Text(
                'Aucune preuve soumise',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(color: Colors.grey[600]),
              ),
              const SizedBox(height: 8),
              Text(
                'L\'artisan n\'a pas encore soumis de preuve pour ce jalon',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: Colors.grey[500]),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Preuve de livraison',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),

            // Photo
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: CachedNetworkImage(
                imageUrl: proof.photoUrl,
                width: double.infinity,
                height: 200,
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(
                  height: 200,
                  color: Colors.grey[200],
                  child: const Center(child: CircularProgressIndicator()),
                ),
                errorWidget: (context, url, error) => Container(
                  height: 200,
                  color: Colors.grey[200],
                  child: const Center(
                    child: Icon(Icons.error, color: Colors.red),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Location info
            _buildLocationInfo(context, proof),

            const SizedBox(height: 16),

            // Timestamp
            Row(
              children: [
                Icon(Icons.access_time, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 8),
                Text(
                  'Capturé le ${_formatDateTime(proof.capturedAt)}',
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
                ),
              ],
            ),

            // Integrity status
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  proof.integrityVerified ? Icons.verified : Icons.warning,
                  size: 16,
                  color: proof.integrityVerified ? Colors.green : Colors.orange,
                ),
                const SizedBox(width: 8),
                Text(
                  proof.integrityVerified
                      ? 'Intégrité vérifiée'
                      : 'Intégrité non vérifiée',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: proof.integrityVerified
                        ? Colors.green
                        : Colors.orange,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationInfo(BuildContext context, ProofOfDelivery proof) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey[50],
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey[200]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.location_on, size: 16, color: Colors.grey[600]),
              const SizedBox(width: 8),
              Text(
                'Localisation GPS',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w500),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Lat: ${proof.location.latitude.toStringAsFixed(6)}',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(fontFamily: 'monospace'),
          ),
          Text(
            'Lng: ${proof.location.longitude.toStringAsFixed(6)}',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(fontFamily: 'monospace'),
          ),
          Text(
            'Précision: ${proof.location.accuracy.toStringAsFixed(1)}m',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: proof.location.accuracy <= 10
                  ? Colors.green
                  : Colors.orange,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAutoValidationInfo(BuildContext context) {
    if (widget.jalon.autoValidationDeadline == null) {
      return const SizedBox.shrink();
    }

    final hoursRemaining = widget.jalon.hoursUntilAutoValidation ?? 0;
    final isUrgent = hoursRemaining <= 6;
    final isPastDeadline = widget.jalon.isAutoValidationDue;

    return Card(
      color: isPastDeadline
          ? Colors.red[50]
          : (isUrgent ? Colors.orange[50] : null),
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  isPastDeadline ? Icons.warning : Icons.timer,
                  color: isPastDeadline
                      ? Colors.red
                      : (isUrgent ? Colors.orange : Colors.blue),
                ),
                const SizedBox(width: 8),
                Text(
                  'Validation automatique',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: isPastDeadline ? Colors.red : null,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (isPastDeadline) ...[
              const Text(
                'Le délai de validation est dépassé. Ce jalon sera validé automatiquement.',
                style: TextStyle(color: Colors.red),
              ),
            ] else ...[
              Text(
                'Temps restant: ${hoursRemaining.toStringAsFixed(1)} heures',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: isUrgent ? Colors.orange : null,
                  fontWeight: isUrgent ? FontWeight.w500 : null,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Échéance: ${_formatDateTime(widget.jalon.autoValidationDeadline!)}',
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: Colors.grey[600]),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildContestForm(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Motif de contestation',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _contestReasonController,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Expliquez pourquoi vous contestez ce jalon...',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      setState(() {
                        _showContestForm = false;
                        _contestReasonController.clear();
                      });
                    },
                    child: const Text('Annuler'),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _isContesting ? null : _contestJalon,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      foregroundColor: Colors.white,
                    ),
                    child: _isContesting
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                Colors.white,
                              ),
                            ),
                          )
                        : const Text('Contester'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context) {
    if (!widget.jalon.canBeValidated) {
      return const SizedBox.shrink();
    }

    return Column(
      children: [
        // Comment field for validation
        if (!_showContestForm) ...[
          TextField(
            controller: _commentController,
            decoration: const InputDecoration(
              labelText: 'Commentaire (optionnel)',
              hintText: 'Ajoutez un commentaire sur ce jalon...',
              border: OutlineInputBorder(),
            ),
            maxLines: 2,
          ),
          const SizedBox(height: 16),
        ],

        // Action buttons
        Row(
          children: [
            if (!_showContestForm) ...[
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _isValidating || _isContesting
                      ? null
                      : () {
                          setState(() {
                            _showContestForm = true;
                          });
                        },
                  icon: const Icon(Icons.close, color: Colors.red),
                  label: const Text(
                    'Contester',
                    style: TextStyle(color: Colors.red),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.red),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                ),
              ),
              const SizedBox(width: 16),
            ],
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _showContestForm || _isValidating || _isContesting
                    ? null
                    : _validateJalon,
                icon: _isValidating
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            Colors.white,
                          ),
                        ),
                      )
                    : const Icon(Icons.check),
                label: Text(_isValidating ? 'Validation...' : 'Valider'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _validateJalon() async {
    setState(() {
      _isValidating = true;
    });

    try {
      final success = await _controller.validateJalon(
        widget.jalon.id,
        comment: _commentController.text.trim().isEmpty
            ? null
            : _commentController.text.trim(),
      );

      if (success) {
        Get.back(result: true);
      }
    } finally {
      setState(() {
        _isValidating = false;
      });
    }
  }

  Future<void> _contestJalon() async {
    final reason = _contestReasonController.text.trim();
    if (reason.isEmpty) {
      Get.snackbar(
        'Erreur',
        'Veuillez saisir un motif de contestation',
        snackPosition: SnackPosition.BOTTOM,
      );
      return;
    }

    setState(() {
      _isContesting = true;
    });

    try {
      final success = await _controller.contestJalon(widget.jalon.id, reason);

      if (success) {
        Get.back(result: true);
      }
    } finally {
      setState(() {
        _isContesting = false;
      });
    }
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
