import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../../../worksite/domain/models/jalon.dart';
import '../../../worksite/presentation/controllers/worksite_controller.dart';
import '../../../worksite/presentation/pages/photo_capture_page.dart';

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
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Soumettre une Preuve',
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
            _buildStatusInfo(context),
            SizedBox(height: AppSpacing.lg),
            _buildInstructions(context),
            SizedBox(height: AppSpacing.lg),
            if (widget.jalon.hasProof) ...[
              _buildExistingProof(context),
              SizedBox(height: AppSpacing.lg),
            ],
            _buildActionButton(context),
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
                width: 40,
                height: 40,
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
              SizedBox(width: AppSpacing.md),
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
                    SizedBox(height: AppSpacing.xs),
                    Text(
                      widget.jalon.statusLabel,
                      style: AppTypography.body.copyWith(
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
          SizedBox(height: AppSpacing.md),
          InfoCard(
            title: 'Montant à libérer',
            subtitle: widget.jalon.laborAmount.formatted,
            icon: Icons.payments,
            backgroundColor: AppColors.accentSuccess.withValues(alpha: 0.1),
            iconColor: AppColors.accentSuccess,
          ),
        ],
      ),
    );
  }

  Widget _buildStatusInfo(BuildContext context) {
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
            'État du jalon',
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.md),
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
    );
  }

  Widget _buildStatusStep(String title, bool isCompleted, bool isActive) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isCompleted
                  ? AppColors.accentSuccess
                  : isActive
                  ? AppColors.accentWarning
                  : AppColors.overlayMedium,
            ),
            child: isCompleted
                ? Icon(Icons.check, color: AppColors.textPrimary, size: 14)
                : isActive
                ? Icon(
                    Icons.radio_button_unchecked,
                    color: AppColors.textPrimary,
                    size: 14,
                  )
                : null,
          ),
          SizedBox(width: AppSpacing.sm),
          Text(
            title,
            style: AppTypography.body.copyWith(
              color: isCompleted
                  ? AppColors.accentSuccess
                  : isActive
                  ? AppColors.accentWarning
                  : AppColors.textSecondary,
              fontWeight: isActive ? FontWeight.w500 : null,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInstructions(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InfoCard(
          title: 'Instructions',
          subtitle: 'Suivez ces étapes pour soumettre votre preuve de travail',
          icon: Icons.info,
          backgroundColor: AppColors.accentPrimary.withValues(alpha: 0.1),
          iconColor: AppColors.accentPrimary,
        ),
        SizedBox(height: AppSpacing.sm),
        Container(
          padding: EdgeInsets.all(AppSpacing.base),
          decoration: BoxDecoration(
            color: AppColors.cardBg,
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.overlayMedium),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildInstructionItem(
                'Prenez une photo claire du travail terminé',
              ),
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
      ],
    );
  }

  Widget _buildInstructionItem(String text) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 6,
            height: 6,
            margin: EdgeInsets.only(top: 6),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.accentPrimary,
            ),
          ),
          SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              text,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExistingProof(BuildContext context) {
    final proof = widget.jalon.proof!;

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
            'Preuve déjà soumise',
            style: AppTypography.sectionTitle.copyWith(
              color: AppColors.textPrimary,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: AppSpacing.md),
          Container(
            padding: EdgeInsets.all(AppSpacing.sm),
            decoration: BoxDecoration(
              color: AppColors.accentSuccess.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(AppRadius.sm),
              border: Border.all(
                color: AppColors.accentSuccess.withValues(alpha: 0.3),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.check_circle, color: AppColors.accentSuccess),
                    SizedBox(width: AppSpacing.xs),
                    Text(
                      'Preuve soumise avec succès',
                      style: AppTypography.body.copyWith(
                        color: AppColors.accentSuccess,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
                SizedBox(height: AppSpacing.xs),
                Text(
                  'Soumis le ${_formatDateTime(proof.capturedAt)}',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.accentSuccess,
                  ),
                ),
                Text(
                  'Localisation: ${proof.location.latitude.toStringAsFixed(4)}, ${proof.location.longitude.toStringAsFixed(4)}',
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.accentSuccess,
                  ),
                ),
              ],
            ),
          ),

          if (widget.jalon.autoValidationDeadline != null) ...[
            SizedBox(height: AppSpacing.md),
            _buildAutoValidationInfo(context),
          ],
        ],
      ),
    );
  }

  Widget _buildAutoValidationInfo(BuildContext context) {
    final hoursRemaining = widget.jalon.hoursUntilAutoValidation ?? 0;
    final isUrgent = hoursRemaining <= 6;
    final isPastDeadline = widget.jalon.isAutoValidationDue;

    Color backgroundColor;
    Color borderColor;
    Color iconColor;
    Color textColor;

    if (isPastDeadline) {
      backgroundColor = AppColors.accentSuccess.withValues(alpha: 0.1);
      borderColor = AppColors.accentSuccess.withValues(alpha: 0.3);
      iconColor = AppColors.accentSuccess;
      textColor = AppColors.accentSuccess;
    } else if (isUrgent) {
      backgroundColor = AppColors.accentWarning.withValues(alpha: 0.1);
      borderColor = AppColors.accentWarning.withValues(alpha: 0.3);
      iconColor = AppColors.accentWarning;
      textColor = AppColors.accentWarning;
    } else {
      backgroundColor = AppColors.accentPrimary.withValues(alpha: 0.1);
      borderColor = AppColors.accentPrimary.withValues(alpha: 0.3);
      iconColor = AppColors.accentPrimary;
      textColor = AppColors.accentPrimary;
    }

    return Container(
      padding: EdgeInsets.all(AppSpacing.sm),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                isPastDeadline ? Icons.check_circle : Icons.timer,
                color: iconColor,
              ),
              SizedBox(width: AppSpacing.xs),
              Text(
                isPastDeadline
                    ? 'Validation automatique'
                    : 'En attente de validation',
                style: AppTypography.body.copyWith(
                  color: textColor,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.xs / 2),
          if (isPastDeadline) ...[
            Text(
              'Ce jalon sera validé automatiquement',
              style: AppTypography.bodySmall.copyWith(color: textColor),
            ),
          ] else ...[
            Text(
              'Temps restant: ${hoursRemaining.toStringAsFixed(1)} heures',
              style: AppTypography.bodySmall.copyWith(color: textColor),
            ),
            Text(
              'Échéance: ${_formatDateTime(widget.jalon.autoValidationDeadline!)}',
              style: AppTypography.bodySmall.copyWith(color: textColor),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildActionButton(BuildContext context) {
    if (widget.jalon.isCompleted) {
      return PrimaryButton(
        onPressed: null,
        text: 'Jalon terminé',
        icon: Icons.check_circle,
        backgroundColor: AppColors.accentSuccess,
      );
    }

    if (widget.jalon.isContested) {
      return Column(
        children: [
          InfoCard(
            title: 'Jalon contesté',
            subtitle: widget.jalon.contestReason != null
                ? 'Motif: ${widget.jalon.contestReason}'
                : 'Ce jalon a été contesté par le client',
            icon: Icons.warning,
            backgroundColor: AppColors.accentDanger.withValues(alpha: 0.1),
            iconColor: AppColors.accentDanger,
          ),
          SizedBox(height: AppSpacing.md),
          PrimaryButton(
            onPressed: () => _navigateToPhotoCapture(),
            text: 'Soumettre une nouvelle preuve',
            icon: Icons.camera_alt,
          ),
        ],
      );
    }

    if (widget.jalon.hasProof) {
      return SizedBox(
        width: double.infinity,
        height: 48,
        child: OutlinedButton.icon(
          onPressed: () => _navigateToPhotoCapture(),
          icon: Icon(Icons.camera_alt),
          label: Text('Modifier la preuve'),
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.accentPrimary,
            side: BorderSide(color: AppColors.accentPrimary),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
          ),
        ),
      );
    }

    return PrimaryButton(
      onPressed: () => _navigateToPhotoCapture(),
      text: 'Prendre une photo',
      icon: Icons.camera_alt,
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
