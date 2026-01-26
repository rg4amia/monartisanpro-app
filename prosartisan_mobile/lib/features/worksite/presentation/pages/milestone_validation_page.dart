import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
import '../../../worksite/domain/models/jalon.dart';
import '../../../worksite/presentation/controllers/worksite_controller.dart';

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
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Validation du Jalon',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: AppColors.accentPrimary,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildJalonInfo(context),
            SizedBox(height: AppSpacing.lg),
            _buildProofSection(context),
            SizedBox(height: AppSpacing.lg),
            _buildAutoValidationInfo(context),
            SizedBox(height: AppSpacing.lg),
            if (_showContestForm) ...[
              _buildContestForm(context),
              SizedBox(height: AppSpacing.lg),
            ],
            _buildActionButtons(context),
          ],
        ),
      ),
    );
  }

  Widget _buildJalonInfo(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: AppColors.accentPrimary,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    '${widget.jalon.sequenceNumber}',
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Jalon ${widget.jalon.sequenceNumber}',
                      style: AppTypography.sectionTitle.copyWith(
                        color: AppColors.textPrimary,
                      ),
                    ),
                    Text(
                      widget.jalon.statusLabel,
                      style: AppTypography.bodySmall.copyWith(
                        color: _getStatusColor(widget.jalon.status),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.md),
          Text(
            widget.jalon.description,
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
          ),
          SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Icon(Icons.payments, size: 16, color: AppColors.textSecondary),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Montant: ${widget.jalon.laborAmount.formatted}',
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildProofSection(BuildContext context) {
    final proof = widget.jalon.proof;

    if (proof == null) {
      return EmptyStateCard(
        icon: Icons.photo_camera,
        title: 'Aucune preuve soumise',
        subtitle: 'L\'artisan n\'a pas encore soumis de preuve pour ce jalon',
      );
    }

    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Preuve de livraison',
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.md),

          // Photo
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.md),
            child: CachedNetworkImage(
              imageUrl: proof.photoUrl,
              width: double.infinity,
              height: 200,
              fit: BoxFit.cover,
              placeholder: (context, url) => Container(
                height: 200,
                color: AppColors.overlayMedium,
                child: Center(
                  child: CircularProgressIndicator(
                    color: AppColors.accentPrimary,
                  ),
                ),
              ),
              errorWidget: (context, url, error) => Container(
                height: 200,
                color: AppColors.overlayMedium,
                child: Center(
                  child: Icon(Icons.error, color: AppColors.accentDanger),
                ),
              ),
            ),
          ),

          SizedBox(height: AppSpacing.md),

          // Location info
          _buildLocationInfo(context, proof),

          SizedBox(height: AppSpacing.md),

          // Timestamp
          Row(
            children: [
              Icon(Icons.access_time, size: 16, color: AppColors.textSecondary),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Capturé le ${_formatDateTime(proof.capturedAt)}',
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),

          // Integrity status
          SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Icon(
                proof.integrityVerified ? Icons.verified : Icons.warning,
                size: 16,
                color: proof.integrityVerified
                    ? AppColors.accentSuccess
                    : AppColors.accentWarning,
              ),
              SizedBox(width: AppSpacing.sm),
              Text(
                proof.integrityVerified
                    ? 'Intégrité vérifiée'
                    : 'Intégrité non vérifiée',
                style: AppTypography.bodySmall.copyWith(
                  color: proof.integrityVerified
                      ? AppColors.accentSuccess
                      : AppColors.accentWarning,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildLocationInfo(BuildContext context, ProofOfDelivery proof) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.secondaryBg,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.location_on, size: 16, color: AppColors.textSecondary),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Localisation GPS',
                style: AppTypography.bodySmall.copyWith(
                  fontWeight: FontWeight.w500,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.sm),
          Text(
            'Lat: ${proof.location.latitude.toStringAsFixed(6)}',
            style: AppTypography.bodySmall.copyWith(
              fontFamily: 'monospace',
              color: AppColors.textSecondary,
            ),
          ),
          Text(
            'Lng: ${proof.location.longitude.toStringAsFixed(6)}',
            style: AppTypography.bodySmall.copyWith(
              fontFamily: 'monospace',
              color: AppColors.textSecondary,
            ),
          ),
          Text(
            'Précision: ${proof.location.accuracy.toStringAsFixed(1)}m',
            style: AppTypography.bodySmall.copyWith(
              color: proof.location.accuracy <= 10
                  ? AppColors.accentSuccess
                  : AppColors.accentWarning,
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

    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: isPastDeadline
            ? AppColors.accentDanger.withValues(alpha: 0.1)
            : (isUrgent
                  ? AppColors.accentWarning.withValues(alpha: 0.1)
                  : AppColors.cardBg),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isPastDeadline ? Icons.warning : Icons.timer,
                color: isPastDeadline
                    ? AppColors.accentDanger
                    : (isUrgent
                          ? AppColors.accentWarning
                          : AppColors.accentPrimary),
              ),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Validation automatique',
                style: AppTypography.sectionTitle.copyWith(
                  fontWeight: FontWeight.bold,
                  color: isPastDeadline
                      ? AppColors.accentDanger
                      : AppColors.textPrimary,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.sm),
          if (isPastDeadline) ...[
            Text(
              'Le délai de validation est dépassé. Ce jalon sera validé automatiquement.',
              style: AppTypography.body.copyWith(color: AppColors.accentDanger),
            ),
          ] else ...[
            Text(
              'Temps restant: ${hoursRemaining.toStringAsFixed(1)} heures',
              style: AppTypography.body.copyWith(
                color: isUrgent
                    ? AppColors.accentWarning
                    : AppColors.textPrimary,
                fontWeight: isUrgent ? FontWeight.w500 : null,
              ),
            ),
            SizedBox(height: AppSpacing.xs),
            Text(
              'Échéance: ${_formatDateTime(widget.jalon.autoValidationDeadline!)}',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildContestForm(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.base),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Motif de contestation',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.base),
          TextField(
            controller: _contestReasonController,
            maxLines: 4,
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            decoration: InputDecoration(
              hintText: 'Expliquez pourquoi vous contestez ce jalon...',
              hintStyle: AppTypography.body.copyWith(
                color: AppColors.textMuted,
              ),
              filled: true,
              fillColor: AppColors.secondaryBg,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(
                  color: AppColors.accentPrimary,
                  width: 2,
                ),
              ),
            ),
          ),
          SizedBox(height: AppSpacing.base),
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
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.textSecondary,
                    side: BorderSide(color: AppColors.overlayMedium),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                  ),
                  child: Text(
                    'Annuler',
                    style: AppTypography.button.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ),
              SizedBox(width: AppSpacing.base),
              Expanded(
                child: ElevatedButton(
                  onPressed: _isContesting ? null : _contestJalon,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.accentDanger,
                    foregroundColor: AppColors.textPrimary,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                  ),
                  child: _isContesting
                      ? SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              AppColors.textPrimary,
                            ),
                          ),
                        )
                      : Text(
                          'Contester',
                          style: AppTypography.button.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ],
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
            style: AppTypography.body.copyWith(color: AppColors.textPrimary),
            decoration: InputDecoration(
              labelText: 'Commentaire (optionnel)',
              labelStyle: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
              hintText: 'Ajoutez un commentaire sur ce jalon...',
              hintStyle: AppTypography.body.copyWith(
                color: AppColors.textMuted,
              ),
              filled: true,
              fillColor: AppColors.cardBg,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(color: AppColors.overlayMedium),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadius.md),
                borderSide: BorderSide(
                  color: AppColors.accentPrimary,
                  width: 2,
                ),
              ),
            ),
            maxLines: 2,
          ),
          SizedBox(height: AppSpacing.base),
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
                  icon: Icon(Icons.close, color: AppColors.accentDanger),
                  label: Text(
                    'Contester',
                    style: AppTypography.button.copyWith(
                      color: AppColors.accentDanger,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: AppColors.accentDanger),
                    padding: EdgeInsets.symmetric(vertical: AppSpacing.base),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                    ),
                  ),
                ),
              ),
              SizedBox(width: AppSpacing.base),
            ],
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _showContestForm || _isValidating || _isContesting
                    ? null
                    : _validateJalon,
                icon: _isValidating
                    ? SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            AppColors.textPrimary,
                          ),
                        ),
                      )
                    : Icon(Icons.check),
                label: Text(
                  _isValidating ? 'Validation...' : 'Valider',
                  style: AppTypography.button.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.accentSuccess,
                  foregroundColor: AppColors.textPrimary,
                  padding: EdgeInsets.symmetric(vertical: AppSpacing.base),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
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
        return AppColors.textSecondary;
      case 'SUBMITTED':
        return AppColors.accentWarning;
      case 'VALIDATED':
        return AppColors.accentSuccess;
      case 'CONTESTED':
        return AppColors.accentDanger;
      default:
        return AppColors.textSecondary;
    }
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day.toString().padLeft(2, '0')}/${dateTime.month.toString().padLeft(2, '0')}/${dateTime.year} ${dateTime.hour.toString().padLeft(2, '0')}:${dateTime.minute.toString().padLeft(2, '0')}';
  }
}
