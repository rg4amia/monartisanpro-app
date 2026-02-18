import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/models/dispute_model.dart';
import 'dispute_details_screen.dart';

class DisputeManagementScreen extends StatefulWidget {
  const DisputeManagementScreen({super.key});

  @override
  State<DisputeManagementScreen> createState() =>
      _DisputeManagementScreenState();
}

class _DisputeManagementScreenState extends State<DisputeManagementScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<Dispute> _disputes = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _loadDisputes();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadDisputes() async {
    setState(() => _isLoading = true);
    // TODO: Implement API call to fetch user's disputes
    await Future.delayed(const Duration(seconds: 1));
    setState(() => _isLoading = false);
  }

  List<Dispute> _getFilteredDisputes(String status) {
    if (status == 'all') return _disputes;
    return _disputes.where((d) => d.status == status).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Litiges'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Tous'),
            Tab(text: 'Ouverts'),
            Tab(text: 'En cours'),
            Tab(text: 'Résolus'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildDisputeList('all'),
          _buildDisputeList('open'),
          _buildDisputeList('in_progress'),
          _buildDisputeList('resolved'),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createDispute,
        icon: const Icon(Icons.add),
        label: const Text('Nouveau litige'),
        backgroundColor: AppColors.error,
      ),
    );
  }

  Widget _buildDisputeList(String status) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final disputes = _getFilteredDisputes(status);

    if (disputes.isEmpty) {
      return _buildEmptyState(status);
    }

    return RefreshIndicator(
      onRefresh: _loadDisputes,
      child: ListView.builder(
        padding: const EdgeInsets.all(Spacing.md),
        itemCount: disputes.length,
        itemBuilder: (context, index) {
          final dispute = disputes[index];
          return _DisputeCard(
            dispute: dispute,
            onTap: () =>
                Get.to(() => DisputeDetailsScreen(disputeId: dispute.id)),
          );
        },
      ),
    );
  }

  Widget _buildEmptyState(String status) {
    String message;
    switch (status) {
      case 'open':
        message = 'Aucun litige ouvert';
        break;
      case 'in_progress':
        message = 'Aucun litige en cours';
        break;
      case 'resolved':
        message = 'Aucun litige résolu';
        break;
      default:
        message = 'Aucun litige';
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.gavel_outlined, size: 80, color: Colors.grey[400]),
            const SizedBox(height: Spacing.lg),
            Text(
              message,
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              'Vos litiges apparaîtront ici',
              style: TextStyle(fontSize: 14, color: Colors.grey[500]),
            ),
          ],
        ),
      ),
    );
  }

  void _createDispute() {
    // TODO: Navigate to create dispute screen
    Get.snackbar('Info', 'Fonctionnalité en cours de développement');
  }
}

class _DisputeCard extends StatelessWidget {
  final Dispute dispute;
  final VoidCallback onTap;

  const _DisputeCard({required this.dispute, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      dispute.subject,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  _StatusBadge(status: dispute.status),
                ],
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                dispute.description,
                style: const TextStyle(fontSize: 14, color: Colors.grey),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const Divider(height: Spacing.lg),
              Row(
                children: [
                  Icon(Icons.calendar_today, size: 16, color: Colors.grey[600]),
                  const SizedBox(width: Spacing.xs),
                  Text(
                    'Créé le ${DateFormat('dd/MM/yyyy').format(dispute.createdAt)}',
                    style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.arrow_forward_ios,
                    size: 16,
                    color: Colors.grey,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;

    switch (status) {
      case 'open':
        color = AppColors.error;
        label = 'Ouvert';
        break;
      case 'in_progress':
        color = AppColors.warning;
        label = 'En cours';
        break;
      case 'resolved':
        color = AppColors.success;
        label = 'Résolu';
        break;
      case 'closed':
        color = Colors.grey;
        label = 'Fermé';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.xs,
      ),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}
