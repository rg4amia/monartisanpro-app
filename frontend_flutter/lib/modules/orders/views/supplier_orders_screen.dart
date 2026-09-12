import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/code_verification_card.dart';
import '../controllers/order_follow_up_controller.dart';
import '../widgets/order_status_badge.dart';

/// Espace fournisseur : les commandes e-commerce reçues.
///
/// Jusqu'ici le fournisseur ne voyait que ses missions J-Code ; les commandes
/// passées depuis le catalogue n'apparaissaient nulle part, et il n'avait donc
/// aucun moyen de contrôler le code présenté au comptoir.
class SupplierOrdersScreen extends StatefulWidget {
  const SupplierOrdersScreen({super.key});

  @override
  State<SupplierOrdersScreen> createState() => _SupplierOrdersScreenState();
}

class _SupplierOrdersScreenState extends State<SupplierOrdersScreen> {
  late final OrderFollowUpController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.put(OrderFollowUpController())..asSupplier = true;
    controller.load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mes commandes'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.orders.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        final active = controller.activeOrders;
        final past = controller.pastOrders;

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
              if (active.isEmpty && past.isEmpty)
                const _EmptyState()
              else ...[
                if (active.isNotEmpty) ...[
                  const _SectionLabel('À traiter'),
                  const SizedBox(height: 10),
                  ...active.map(
                    (order) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _SupplierOrderCard(
                        order: order,
                        controller: controller,
                      ),
                    ),
                  ),
                ],
                if (past.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const _SectionLabel('Historique'),
                  const SizedBox(height: 10),
                  ...past.map(
                    (order) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _SupplierOrderCard(
                        order: order,
                        controller: controller,
                      ),
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

class _SupplierOrderCard extends StatelessWidget {
  const _SupplierOrderCard({required this.order, required this.controller});

  final Map<String, dynamic> order;
  final OrderFollowUpController controller;

  int get _id => (order['id'] as num?)?.toInt() ?? 0;

  String get _status => (order['status'] as String?) ?? 'paid';

  bool get _isStorePickup => order['delivery_mode'] == 'pickup';

  String get _clientName {
    final client = order['client'];

    return client is Map ? (client['name'] as String? ?? 'Client') : 'Client';
  }

  /// Le code n'est utile qu'au moment de la remise : en retrait magasin dès
  /// que la commande est prête, en livraison dès qu'un livreur est assigné.
  bool get _showCode =>
      _status == 'prepared' ||
      _status == 'driver_assigned' ||
      _status == 'searching_driver';

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
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
                      '$_clientName · ${_isStorePickup ? 'Retrait magasin' : 'Livraison'}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              OrderStatusBadge(status: _status),
            ],
          ),
          const SizedBox(height: 12),
          _OrderItemsSummary(order: order),
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
                Formatters.fcfa((order['subtotal'] as num?)?.toInt() ?? 0),
                style: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          if (_status == 'paid') ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: Obx(() {
                final busy = controller.preparingOrderId.value == _id;

                return ElevatedButton.icon(
                  onPressed: busy ? null : () => controller.markPrepared(_id),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.success,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: busy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.inventory_2_outlined, size: 18),
                  label: Text(busy ? 'Enregistrement…' : 'Marquer préparée'),
                );
              }),
            ),
          ],
          if (_showCode) ...[
            const SizedBox(height: 14),
            CodeVerificationCard(
              title: 'Code de retrait',
              orderLabel: 'Commande #$_id',
              accentColor: AppColors.success,
              backgroundColor: AppColors.supplierSoft,
              instruction: _isStorePickup
                  ? 'Demandez ce code au client avant de lui remettre la commande.'
                  : 'Communiquez ce code au livreur APRÈS avoir chargé la marchandise.',
              onReveal: () => controller.revealCode(_id),
            ),
            const SizedBox(height: 10),
            // Second chemin de validation : si le livreur ou le client n'a pas
            // de réseau, le fournisseur fait avancer la commande depuis son
            // propre appareil. Le rejeu de l'autre côté sera sans effet.
            SizedBox(
              width: double.infinity,
              child: Obx(() {
                final busy = controller.confirmingOrderId.value == _id;

                return OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () => controller.confirmFromCounterparty(
                            _id,
                            isPickup: true,
                          ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.success,
                    side: const BorderSide(color: AppColors.success),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: busy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.handshake_outlined, size: 18),
                  label: Text(
                    busy ? 'Confirmation…' : 'J\'ai remis la marchandise',
                  ),
                );
              }),
            ),
          ],
        ],
      ),
    );
  }
}

class _OrderItemsSummary extends StatelessWidget {
  const _OrderItemsSummary({required this.order});

  final Map<String, dynamic> order;

  @override
  Widget build(BuildContext context) {
    final items = (order['items'] as List?) ?? const [];

    if (items.isEmpty) {
      return const SizedBox.shrink();
    }

    final summary = items.whereType<Map>().map((item) {
      final product = item['product'];
      final name =
          product is Map ? (product['name'] as String? ?? 'Article') : 'Article';
      final qty = (item['quantity'] as num?)?.toInt() ?? 1;

      return '$name ×$qty';
    }).join(' · ');

    return Text(
      summary,
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(
        fontSize: 13,
        height: 1.35,
        color: AppColors.textSecondary,
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
            'Les commandes passées depuis votre catalogue\napparaîtront ici.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}
