import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../home/widgets/artisan_home/stat_card.dart';
import '../controllers/supplier_dashboard_controller.dart';
import '../widgets/order_status_badge.dart';

/// Tableau de bord fournisseur : l'API `/supplier/dashboard` existait déjà
/// côté backend mais n'était reliée à aucun écran — le fournisseur n'avait
/// aucune vue d'ensemble de son activité (commandes, chiffre d'affaires,
/// catalogue) en dehors de la liste brute de ses commandes.
class SupplierDashboardScreen extends StatefulWidget {
  const SupplierDashboardScreen({super.key});

  @override
  State<SupplierDashboardScreen> createState() =>
      _SupplierDashboardScreenState();
}

class _SupplierDashboardScreenState extends State<SupplierDashboardScreen> {
  late final SupplierDashboardController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.put(SupplierDashboardController());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Tableau de bord'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.recentOrders.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        return RefreshIndicator(
          color: AppColors.success,
          onRefresh: controller.load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              if (controller.errorMsg.value != null) ...[
                _ErrorBanner(message: controller.errorMsg.value!),
                const SizedBox(height: 16),
              ],
              // Rangées de hauteur intrinsèque plutôt qu'un GridView.count à
              // ratio fixe : sur police système agrandie ou écran étroit, le
              // texte dépassait le ratio calculé et provoquait un "BOTTOM
              // OVERFLOWED" sur les 4 cartes (même StatCard que le pipeline
              // artisan — voir stat_grid.dart).
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: StatCard(
                        label: 'Commandes',
                        value: '${controller.totalOrders.value}',
                        subtitle: 'Total reçues',
                        color: AppColors.primary,
                        icon: Icons.receipt_long_outlined,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: StatCard(
                        label: 'En attente',
                        value: '${controller.pendingOrders.value}',
                        subtitle: 'À préparer',
                        color: AppColors.accent,
                        icon: Icons.pending_actions_outlined,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: StatCard(
                        label: 'Chiffre d\'affaires',
                        value:
                            Formatters.fcfaShort(controller.totalRevenue.value),
                        subtitle: 'Livré (FCFA)',
                        color: AppColors.success,
                        icon: Icons.payments_outlined,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: StatCard(
                        label: 'Catalogue',
                        value: '${controller.catalogCount.value}',
                        subtitle: 'Articles actifs',
                        color: AppColors.info,
                        icon: Icons.inventory_2_outlined,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Commandes récentes',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 12),
              if (controller.recentOrders.isEmpty)
                const _EmptyState()
              else
                ...controller.recentOrders.map(
                  (order) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _RecentOrderTile(order: order),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }
}

class _RecentOrderTile extends StatelessWidget {
  const _RecentOrderTile({required this.order});

  final Map<String, dynamic> order;

  int get _id => (order['id'] as num?)?.toInt() ?? 0;

  String get _status => (order['status'] as String?) ?? 'paid';

  int get _subtotal => (order['subtotal'] as num?)?.toInt() ?? 0;

  String get _clientName {
    final client = order['client'];

    return client is Map ? (client['name'] as String? ?? 'Client') : 'Client';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Commande #$_id',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _clientName,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              OrderStatusBadge(status: _status),
              const SizedBox(height: 6),
              Text(
                Formatters.fcfa(_subtotal),
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.dangerSoft,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.danger, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.danger,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 40),
      child: Column(
        children: [
          Icon(
            Icons.receipt_long_outlined,
            size: 48,
            color: AppColors.textSecondary.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 12),
          const Text(
            'Aucune commande pour le moment',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Vos commandes les plus récentes apparaîtront ici.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}
