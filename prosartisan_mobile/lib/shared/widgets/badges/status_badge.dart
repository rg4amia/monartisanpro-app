import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_radius.dart';

/// Badge de statut avec couleur appropriée
class StatusBadge extends StatelessWidget {
  final String status;
  final bool compact;
  final Color? customColor;
  final Color? customTextColor;

  const StatusBadge({
    super.key,
    required this.status,
    this.compact = false,
    this.customColor,
    this.customTextColor,
  });

  @override
  Widget build(BuildContext context) {
    final statusInfo = _getStatusInfo(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? AppSpacing.sm : AppSpacing.md,
        vertical: compact ? AppSpacing.xs : AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: customColor ?? statusInfo.color,
        borderRadius: AppRadius.badgeRadius,
      ),
      child: Text(
        statusInfo.label,
        style: (compact ? AppTypography.tiny : AppTypography.badge).copyWith(
          color: customTextColor ?? Colors.white,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  StatusInfo _getStatusInfo(String status) {
    switch (status.toLowerCase()) {
      case 'completed':
      case 'terminé':
      case 'fini':
        return StatusInfo(label: 'Terminé', color: AppColors.statusCompleted);
      case 'pending':
      case 'en_attente':
      case 'attente':
        return StatusInfo(label: 'En attente', color: AppColors.statusPending);
      case 'cancelled':
      case 'annulé':
      case 'annule':
        return StatusInfo(label: 'Annulé', color: AppColors.statusCancelled);
      case 'confirmed':
      case 'confirmé':
      case 'confirme':
        return StatusInfo(label: 'Confirmé', color: AppColors.statusConfirmed);
      case 'in_progress':
      case 'en_cours':
      case 'cours':
        return StatusInfo(label: 'En cours', color: AppColors.accentPrimary);
      case 'paused':
      case 'pause':
      case 'suspendu':
        return StatusInfo(label: 'Suspendu', color: AppColors.accentWarning);
      default:
        return StatusInfo(label: status, color: AppColors.textSecondary);
    }
  }
}

/// Informations de statut
class StatusInfo {
  final String label;
  final Color color;

  StatusInfo({required this.label, required this.color});
}

/// Badge de statut avec icône
class StatusBadgeWithIcon extends StatelessWidget {
  final String status;
  final bool compact;
  final Color? customColor;
  final Color? customTextColor;

  const StatusBadgeWithIcon({
    super.key,
    required this.status,
    this.compact = false,
    this.customColor,
    this.customTextColor,
  });

  @override
  Widget build(BuildContext context) {
    final statusInfo = _getStatusInfoWithIcon(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? AppSpacing.sm : AppSpacing.md,
        vertical: compact ? AppSpacing.xs : AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: customColor ?? statusInfo.color,
        borderRadius: AppRadius.badgeRadius,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            statusInfo.icon,
            size: compact ? 12 : 14,
            color: customTextColor ?? Colors.white,
          ),
          const SizedBox(width: AppSpacing.xs),
          Text(
            statusInfo.label,
            style: (compact ? AppTypography.tiny : AppTypography.badge)
                .copyWith(
                  color: customTextColor ?? Colors.white,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ],
      ),
    );
  }

  StatusInfoWithIcon _getStatusInfoWithIcon(String status) {
    switch (status.toLowerCase()) {
      case 'completed':
      case 'terminé':
      case 'fini':
        return StatusInfoWithIcon(
          label: 'Terminé',
          color: AppColors.statusCompleted,
          icon: Icons.check_circle,
        );
      case 'pending':
      case 'en_attente':
      case 'attente':
        return StatusInfoWithIcon(
          label: 'En attente',
          color: AppColors.statusPending,
          icon: Icons.schedule,
        );
      case 'cancelled':
      case 'annulé':
      case 'annule':
        return StatusInfoWithIcon(
          label: 'Annulé',
          color: AppColors.statusCancelled,
          icon: Icons.cancel,
        );
      case 'confirmed':
      case 'confirmé':
      case 'confirme':
        return StatusInfoWithIcon(
          label: 'Confirmé',
          color: AppColors.statusConfirmed,
          icon: Icons.verified,
        );
      case 'in_progress':
      case 'en_cours':
      case 'cours':
        return StatusInfoWithIcon(
          label: 'En cours',
          color: AppColors.accentPrimary,
          icon: Icons.play_circle,
        );
      case 'paused':
      case 'pause':
      case 'suspendu':
        return StatusInfoWithIcon(
          label: 'Suspendu',
          color: AppColors.accentWarning,
          icon: Icons.pause_circle,
        );
      default:
        return StatusInfoWithIcon(
          label: status,
          color: AppColors.textSecondary,
          icon: Icons.info,
        );
    }
  }
}

/// Informations de statut avec icône
class StatusInfoWithIcon {
  final String label;
  final Color color;
  final IconData icon;

  StatusInfoWithIcon({
    required this.label,
    required this.color,
    required this.icon,
  });
}

/// Badge de statut avec bordure
class OutlinedStatusBadge extends StatelessWidget {
  final String status;
  final bool compact;

  const OutlinedStatusBadge({
    super.key,
    required this.status,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final statusInfo = _getStatusInfo(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? AppSpacing.sm : AppSpacing.md,
        vertical: compact ? AppSpacing.xs : AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: statusInfo.color.withOpacity(0.1),
        borderRadius: AppRadius.badgeRadius,
        border: Border.all(color: statusInfo.color, width: 1),
      ),
      child: Text(
        statusInfo.label,
        style: (compact ? AppTypography.tiny : AppTypography.badge).copyWith(
          color: statusInfo.color,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  StatusInfo _getStatusInfo(String status) {
    switch (status.toLowerCase()) {
      case 'completed':
      case 'terminé':
      case 'fini':
        return StatusInfo(label: 'Terminé', color: AppColors.statusCompleted);
      case 'pending':
      case 'en_attente':
      case 'attente':
        return StatusInfo(label: 'En attente', color: AppColors.statusPending);
      case 'cancelled':
      case 'annulé':
      case 'annule':
        return StatusInfo(label: 'Annulé', color: AppColors.statusCancelled);
      case 'confirmed':
      case 'confirmé':
      case 'confirme':
        return StatusInfo(label: 'Confirmé', color: AppColors.statusConfirmed);
      case 'in_progress':
      case 'en_cours':
      case 'cours':
        return StatusInfo(label: 'En cours', color: AppColors.accentPrimary);
      case 'paused':
      case 'pause':
      case 'suspendu':
        return StatusInfo(label: 'Suspendu', color: AppColors.accentWarning);
      default:
        return StatusInfo(label: status, color: AppColors.textSecondary);
    }
  }
}

/// Badge de priorité
class PriorityBadge extends StatelessWidget {
  final String priority;
  final bool compact;

  const PriorityBadge({
    super.key,
    required this.priority,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final priorityInfo = _getPriorityInfo(priority);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? AppSpacing.sm : AppSpacing.md,
        vertical: compact ? AppSpacing.xs : AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: priorityInfo.color,
        borderRadius: AppRadius.badgeRadius,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(priorityInfo.icon, size: compact ? 12 : 14, color: Colors.white),
          const SizedBox(width: AppSpacing.xs),
          Text(
            priorityInfo.label,
            style: (compact ? AppTypography.tiny : AppTypography.badge)
                .copyWith(color: Colors.white, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  PriorityInfo _getPriorityInfo(String priority) {
    switch (priority.toLowerCase()) {
      case 'high':
      case 'haute':
      case 'urgent':
        return PriorityInfo(
          label: 'Urgent',
          color: AppColors.accentDanger,
          icon: Icons.priority_high,
        );
      case 'medium':
      case 'moyenne':
      case 'normal':
        return PriorityInfo(
          label: 'Normal',
          color: AppColors.accentWarning,
          icon: Icons.remove,
        );
      case 'low':
      case 'basse':
      case 'faible':
        return PriorityInfo(
          label: 'Faible',
          color: AppColors.accentSuccess,
          icon: Icons.keyboard_arrow_down,
        );
      default:
        return PriorityInfo(
          label: priority,
          color: AppColors.textSecondary,
          icon: Icons.info,
        );
    }
  }
}

/// Informations de priorité
class PriorityInfo {
  final String label;
  final Color color;
  final IconData icon;

  PriorityInfo({required this.label, required this.color, required this.icon});
}
