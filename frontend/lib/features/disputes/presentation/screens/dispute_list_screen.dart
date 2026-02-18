import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../shared/controllers/dispute_controller.dart';
import '../../../../shared/models/dispute_model.dart';
import '../../../../core/theme/app_colors.dart';
import 'dispute_details_screen.dart';
import 'create_dispute_screen.dart';

class DisputeListScreen extends StatelessWidget {
  const DisputeListScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final disputeController = Get.put(DisputeController());

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes Litiges'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: () => _showFilterBottomSheet(context, disputeController),
          ),
        ],
      ),
      body: Obx(() {
        if (disputeController.isLoadingDisputes.value) {
          return const Center(child: CircularProgressIndicator());
        }

        if (disputeController.disputes.isEmpty) {
          return _buildEmptyState();
        }

        return RefreshIndicator(
          onRefresh: () => disputeController.refreshDisputes(),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: disputeController.disputes.length,
            separatorBuilder: (context, index) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final dispute = disputeController.disputes[index];
              return _DisputeCard(dispute: dispute);
            },
          ),
        );
      }),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Get.to(() => const CreateDisputeScreen()),
        icon: const Icon(Icons.add),
        label: const Text('Nouveau Litige'),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.verified_user, size: 80, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Aucun litige',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Vous n\'avez aucun litige en cours',
            style: TextStyle(color: Colors.grey[500]),
          ),
        ],
      ),
    );
  }

  void _showFilterBottomSheet(
    BuildContext context,
    DisputeController controller,
  ) {
    showModalBottomSheet(
      context: context,
      builder: (context) => Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Filtrer par statut',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            _FilterChip(
              label: 'Tous',
              isSelected: controller.disputesFilter.value == 'all',
              onTap: () {
                controller.filterDisputes('all');
                Get.back();
              },
            ),
            _FilterChip(
              label: 'Ouvert',
              isSelected: controller.disputesFilter.value == 'open',
              onTap: () {
                controller.filterDisputes('open');
                Get.back();
              },
            ),
            _FilterChip(
              label: 'En investigation',
              isSelected: controller.disputesFilter.value == 'investigating',
              onTap: () {
                controller.filterDisputes('investigating');
                Get.back();
              },
            ),
            _FilterChip(
              label: 'Résolu',
              isSelected: controller.disputesFilter.value == 'resolved',
              onTap: () {
                controller.filterDisputes('resolved');
                Get.back();
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _DisputeCard extends StatelessWidget {
  final Dispute dispute;

  const _DisputeCard({required this.dispute});

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: () => Get.to(() => DisputeDetailsScreen(disputeId: dispute.id)),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      dispute.reason,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  _buildStatusBadge(dispute.status),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                dispute.description,
                style: TextStyle(color: Colors.grey[600], fontSize: 14),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _buildInfoChip(
                    icon: Icons.category,
                    label: _getDisputeTypeLabel(dispute.disputeType),
                    color: Colors.blue,
                  ),
                  const SizedBox(width: 8),
                  _buildInfoChip(
                    icon: Icons.priority_high,
                    label: _getPriorityLabel(dispute.priority),
                    color: _getPriorityColor(dispute.priority),
                  ),
                  const Spacer(),
                  Text(
                    _formatDate(dispute.createdAt),
                    style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color color;
    String label;

    switch (status) {
      case 'open':
        color = Colors.orange;
        label = 'Ouvert';
        break;
      case 'investigating':
        color = Colors.blue;
        label = 'Investigation';
        break;
      case 'resolved':
        color = Colors.green;
        label = 'Résolu';
        break;
      case 'cancelled':
        color = Colors.red;
        label = 'Annulé';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _buildInfoChip({
    required IconData icon,
    required String label,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: color,
              fontWeight: FontWeight.w500,
            ),
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

  String _getPriorityLabel(String priority) {
    switch (priority) {
      case 'low':
        return 'Basse';
      case 'medium':
        return 'Moyenne';
      case 'high':
        return 'Haute';
      case 'urgent':
        return 'Urgente';
      default:
        return priority;
    }
  }

  Color _getPriorityColor(String priority) {
    switch (priority) {
      case 'low':
        return Colors.green;
      case 'medium':
        return Colors.orange;
      case 'high':
      case 'urgent':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);

    if (diff.inDays == 0) {
      return 'Aujourd\'hui';
    } else if (diff.inDays == 1) {
      return 'Hier';
    } else if (diff.inDays < 7) {
      return 'Il y a ${diff.inDays} jours';
    } else {
      return '${date.day}/${date.month}/${date.year}';
    }
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isSelected
              ? AppColors.lightAccentPrimary.withOpacity(0.1)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: isSelected
                ? AppColors.lightAccentPrimary
                : Colors.grey[300]!,
          ),
        ),
        child: Row(
          children: [
            if (isSelected)
              Icon(
                Icons.check_circle,
                color: AppColors.lightAccentPrimary,
                size: 20,
              ),
            if (isSelected) const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                color: isSelected
                    ? AppColors.lightAccentPrimary
                    : Colors.black87,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
