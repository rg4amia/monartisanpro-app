import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/chantier.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/controllers/worksite_controller.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/widgets/milestone_card.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/widgets/progress_indicator_widget.dart';

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
      appBar: AppBar(
        title: const Text('Détails du Chantier'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => controller.refreshCurrentChantier(),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading && controller.currentChantier == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final chantier = controller.currentChantier;
        if (chantier == null) {
          return const Center(child: Text('Chantier non trouvé'));
        }

        return RefreshIndicator(
          onRefresh: () => controller.refreshCurrentChantier(),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildChantierHeader(context, chantier),
                const SizedBox(height: 24),
                _buildProgressSection(context, chantier),
                const SizedBox(height: 24),
                _buildFinancialSection(context, chantier),
                const SizedBox(height: 24),
                _buildMilestonesSection(context, chantier),
              ],
            ),
          ),
        );
      }),
    );
  }

  Widget _buildChantierHeader(BuildContext context, Chantier chantier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
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
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    chantier.statusLabel,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: _getStatusColor(chantier.status),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildInfoRow(
              context,
              'Démarré le',
              _formatDate(chantier.startedAt),
              Icons.calendar_today,
            ),
            if (chantier.completedAt != null) ...[
              const SizedBox(height: 8),
              _buildInfoRow(
                context,
                'Terminé le',
                _formatDate(chantier.completedAt!),
                Icons.check_circle,
              ),
            ],
            const SizedBox(height: 8),
            _buildInfoRow(
              context,
              'Jalons',
              '${chantier.completedMilestonesCount}/${chantier.milestonesCount}',
              Icons.flag,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProgressSection(BuildContext context, Chantier chantier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Progression',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            WorksiteProgressIndicator(
              progress: chantier.progressPercentage / 100,
              completedMilestones: chantier.completedMilestonesCount,
              totalMilestones: chantier.milestonesCount,
            ),
            const SizedBox(height: 16),
            if (chantier.nextMilestone != null) ...[
              Text(
                'Prochain jalon',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              Text(
                chantier.nextMilestone!.description,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildFinancialSection(BuildContext context, Chantier chantier) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Informations financières',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            _buildFinancialRow(
              context,
              'Montant total',
              chantier.totalLaborAmount.formatted,
              Icons.account_balance_wallet,
            ),
            const SizedBox(height: 8),
            _buildFinancialRow(
              context,
              'Montant libéré',
              chantier.completedLaborAmount.formatted,
              Icons.payments,
              color: Colors.green,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMilestonesSection(BuildContext context, Chantier chantier) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Jalons (${chantier.milestonesCount})',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 16),
        if (chantier.milestones.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16.0),
              child: Center(child: Text('Aucun jalon défini')),
            ),
          )
        else
          ...chantier.milestones.map(
            (jalon) => Padding(
              padding: const EdgeInsets.only(bottom: 12.0),
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
        Icon(icon, size: 16, color: Colors.grey[600]),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
        ),
        Expanded(
          child: Text(
            value,
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
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
        Icon(icon, size: 16, color: color ?? Colors.grey[600]),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
        ),
        Expanded(
          child: Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
              color: color,
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
        return Colors.orange;
      case 'COMPLETED':
        return Colors.green;
      case 'DISPUTED':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
  }

  void _navigateToMilestone(Jalon jalon) {
    Get.toNamed('/milestone-detail', arguments: jalon);
  }
}
