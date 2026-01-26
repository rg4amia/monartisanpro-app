import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../../../../shared/widgets/cards/empty_state_card.dart';
import '../../../worksite/domain/models/chantier.dart';
import '../../../worksite/domain/models/jalon.dart';
import '../../../worksite/presentation/controllers/worksite_controller.dart';
import '../../../worksite/presentation/widgets/milestone_card.dart';
import '../../../worksite/presentation/widgets/progress_indicator_widget.dart';

/// Chantier detail screen with milestone list
///
/// Shows worksite progress, milestones, and allows milestone management
/// Requirements: 6.1, 6.2, 6.3
class ChantierDetailPage extends StatelessWidget {
  final String chantierId;

  const ChantierDetailPage({super.key, required this.chantierId});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<WorksiteController>();

    // Load chantier details when page opens
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.loadChantier(chantierId);
    });

    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Détails du Chantier',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        actions: [
          IconButton(
            icon: Icon(Icons.refresh, color: AppColors.textPrimary),
            onPressed: () => controller.refreshCurrentChantier(),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading && controller.currentChantier == null) {
          return Center(
            child: CircularProgressIndicator(color: AppColors.accentPrimary),
          );
        }

        final chantier = controller.currentChantier;
        if (chantier == null) {
          return EmptyStateCard(
            icon: Icons.construction,
            title: 'Chantier non trouvé',
            subtitle: 'Le chantier demandé n\'existe pas.',
          );
        }

        return RefreshIndicator(
          onRefresh: () => controller.refreshCurrentChantier(),
          color: AppColors.accentPrimary,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.all(AppSpacing.md),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildChantierHeader(context, chantier),
                SizedBox(height: AppSpacing.lg),
                _buildProgressSection(context, chantier),
                SizedBox(height: AppSpacing.lg),
                _buildFinancialSection(context, chantier),
                SizedBox(height: AppSpacing.lg),
                _buildMilestonesSection(context, chantier),
              ],
            ),
          ),
        );
      }),
    );
  }

  Widget _buildChantierHeader(BuildContext context, Chantier chantier) {
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
                _getStatusIcon(chantier.status),
                color: _getStatusColor(chantier.status),
                size: 24,
              ),
              SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  chantier.statusLabel,
                  style: AppTypography.sectionTitle.copyWith(
                    color: _getStatusColor(chantier.status),
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.md),
          _buildInfoRow(
            context,
            'Démarré le',
            _formatDate(chantier.startedAt),
            Icons.calendar_today,
          ),
          if (chantier.completedAt != null) ...[
            SizedBox(height: AppSpacing.sm),
            _buildInfoRow(
              context,
              'Terminé le',
              _formatDate(chantier.completedAt!),
              Icons.check_circle,
            ),
          ],
          SizedBox(height: AppSpacing.sm),
          _buildInfoRow(
            context,
            'Jalons',
            '${chantier.completedMilestonesCount}/${chantier.milestonesCount}',
            Icons.flag,
          ),
        ],
      ),
    );
  }

  Widget _buildProgressSection(BuildContext context, Chantier chantier) {
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
            'Progression',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.base),
          WorksiteProgressIndicator(
            progress: chantier.progressPercentage / 100,
            completedMilestones: chantier.completedMilestonesCount,
            totalMilestones: chantier.milestonesCount,
          ),
          SizedBox(height: AppSpacing.base),
          if (chantier.nextMilestone != null) ...[
            Text(
              'Prochain jalon',
              style: AppTypography.body.copyWith(
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: AppSpacing.sm),
            Text(
              chantier.nextMilestone!.description,
              style: AppTypography.body.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildFinancialSection(BuildContext context, Chantier chantier) {
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
            'Informations financières',
            style: AppTypography.sectionTitle.copyWith(
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          SizedBox(height: AppSpacing.base),
          _buildFinancialRow(
            context,
            'Montant total',
            chantier.totalLaborAmount.formatted,
            Icons.account_balance_wallet,
          ),
          SizedBox(height: AppSpacing.sm),
          _buildFinancialRow(
            context,
            'Montant libéré',
            chantier.completedLaborAmount.formatted,
            Icons.payments,
            color: AppColors.accentSuccess,
          ),
        ],
      ),
    );
  }

  Widget _buildMilestonesSection(BuildContext context, Chantier chantier) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Jalons (${chantier.milestonesCount})',
          style: AppTypography.sectionTitle.copyWith(
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        SizedBox(height: AppSpacing.base),
        if (chantier.milestones.isEmpty)
          Container(
            padding: EdgeInsets.all(AppSpacing.base),
            decoration: BoxDecoration(
              color: AppColors.cardBg,
              borderRadius: AppRadius.cardRadius,
              border: Border.all(color: AppColors.overlayMedium),
            ),
            child: Center(
              child: Text(
                'Aucun jalon défini',
                style: AppTypography.body.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ),
          )
        else
          ...chantier.milestones.map(
            (jalon) => Padding(
              padding: EdgeInsets.only(bottom: AppSpacing.md),
              child: MilestoneCard(
                jalon: jalon,
                onTap: () => _navigateToMilestone(jalon),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildInfoRow(
    BuildContext context,
    String label,
    String value,
    IconData icon,
  ) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppColors.textSecondary),
        SizedBox(width: AppSpacing.sm),
        Text(
          '$label: ',
          style: AppTypography.body.copyWith(color: AppColors.textSecondary),
        ),
        Expanded(
          child: Text(
            value,
            style: AppTypography.body.copyWith(
              fontWeight: FontWeight.w500,
              color: AppColors.textPrimary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildFinancialRow(
    BuildContext context,
    String label,
    String value,
    IconData icon, {
    Color? color,
  }) {
    return Row(
      children: [
        Icon(icon, size: 16, color: color ?? AppColors.textSecondary),
        SizedBox(width: AppSpacing.sm),
        Text(
          '$label: ',
          style: AppTypography.body.copyWith(color: AppColors.textSecondary),
        ),
        Expanded(
          child: Text(
            value,
            style: AppTypography.body.copyWith(
              fontWeight: FontWeight.w600,
              color: color ?? AppColors.textPrimary,
            ),
          ),
        ),
      ],
    );
  }

  IconData _getStatusIcon(String status) {
    switch (status) {
      case 'IN_PROGRESS':
        return Icons.construction;
      case 'COMPLETED':
        return Icons.check_circle;
      case 'DISPUTED':
        return Icons.warning;
      default:
        return Icons.info;
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'IN_PROGRESS':
        return AppColors.accentWarning;
      case 'COMPLETED':
        return AppColors.accentSuccess;
      case 'DISPUTED':
        return AppColors.accentDanger;
      default:
        return AppColors.textSecondary;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  void _navigateToMilestone(Jalon jalon) {
    Get.toNamed('/milestone-detail', arguments: jalon);
  }
}
