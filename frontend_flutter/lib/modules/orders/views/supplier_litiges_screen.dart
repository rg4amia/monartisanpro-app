import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/supplier_litiges_controller.dart';
import '../widgets/order_status_badge.dart';

/// Vue des litiges concernant le fournisseur : commandes e-commerce
/// contestées et chantiers où il a livré des matériaux via J-Code, désormais
/// en litige. L'API `/supplier/litiges` existait déjà côté backend sans écran
/// pour l'afficher.
///
/// Purement informatif — l'arbitrage reste une action admin du backoffice.
class SupplierLitigesScreen extends StatefulWidget {
  const SupplierLitigesScreen({super.key});

  @override
  State<SupplierLitigesScreen> createState() => _SupplierLitigesScreenState();
}

class _SupplierLitigesScreenState extends State<SupplierLitigesScreen> {
  late final SupplierLitigesController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.put(SupplierLitigesController());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mes litiges'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value &&
            controller.orderLitiges.isEmpty &&
            controller.missionLitiges.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        final orderLitiges = controller.orderLitiges;
        final missionLitiges = controller.missionLitiges;
        final isEmpty = orderLitiges.isEmpty && missionLitiges.isEmpty;

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
              if (isEmpty && controller.errorMsg.value == null)
                const _EmptyState()
              else ...[
                if (orderLitiges.isNotEmpty) ...[
                  const _SectionLabel('Commandes en litige'),
                  const SizedBox(height: 10),
                  ...orderLitiges.map(
                    (order) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _OrderLitigeCard(order: order),
                    ),
                  ),
                ],
                if (missionLitiges.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const _SectionLabel('Chantiers en litige'),
                  const SizedBox(height: 10),
                  ...missionLitiges.map(
                    (mission) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _MissionLitigeCard(mission: mission),
                    ),
                  ),
                ],
              ],
            ],
          ),
        );
      }),
    );
  }
}

class _OrderLitigeCard extends StatelessWidget {
  const _OrderLitigeCard({required this.order});

  final Map<String, dynamic> order;

  int get _id => (order['id'] as num?)?.toInt() ?? 0;

  int get _subtotal => (order['subtotal'] as num?)?.toInt() ?? 0;

  String get _clientName {
    final client = order['client'];

    return client is Map ? (client['name'] as String? ?? 'Client') : 'Client';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.dangerSoft),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Commande #$_id',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _clientName,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const OrderStatusBadge(status: 'disputed'),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(
                Icons.payments_outlined,
                size: 16,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                Formatters.fcfa(_subtotal),
                style: const TextStyle(
                  fontSize: 13.5,
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

class _MissionLitigeCard extends StatelessWidget {
  const _MissionLitigeCard({required this.mission});

  final Map<String, dynamic> mission;

  int get _id => (mission['id'] as num?)?.toInt() ?? 0;

  String get _clientName {
    final client = mission['client'];

    return client is Map ? (client['name'] as String? ?? 'Client') : 'Client';
  }

  String get _artisanName {
    final artisan = mission['artisan'];

    return artisan is Map
        ? (artisan['name'] as String? ?? 'Artisan')
        : 'Artisan';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.dangerSoft),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppColors.dangerSoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.gpp_bad_outlined,
              color: AppColors.danger,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Chantier #$_id',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Client : $_clientName · Artisan : $_artisanName',
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label.toUpperCase(),
      style: const TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.8,
        color: AppColors.textSecondary,
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
      padding: const EdgeInsets.only(top: 80),
      child: Column(
        children: [
          Icon(
            Icons.verified_outlined,
            size: 48,
            color: AppColors.textSecondary.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 12),
          const Text(
            'Aucun litige en cours',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Vos commandes et chantiers contestés apparaîtront ici.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}
