import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/code_verification_card.dart';
import '../controllers/order_follow_up_controller.dart';
import '../widgets/order_status_badge.dart';

/// Espace client : le suivi des commandes passées au catalogue.
///
/// L'application permettait de commander mais pas de suivre : le client ne
/// disposait d'aucun écran où retrouver son code de retrait ou de réception,
/// alors que c'est lui qui doit le présenter ou le remettre.
class ClientOrdersScreen extends StatefulWidget {
  const ClientOrdersScreen({super.key});

  @override
  State<ClientOrdersScreen> createState() => _ClientOrdersScreenState();
}

class _ClientOrdersScreenState extends State<ClientOrdersScreen> {
  late final OrderFollowUpController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.put(OrderFollowUpController())..asSupplier = false;
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
          color: AppColors.client,
          onRefresh: controller.load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              if (active.isEmpty && past.isEmpty)
                const _EmptyState()
              else ...[
                ...active.map(
                  (order) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _ClientOrderCard(
                      order: order,
                      controller: controller,
                    ),
                  ),
                ),
                if (past.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    'HISTORIQUE',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.8,
                      color: AppColors.textSecondary.withValues(alpha: 0.9),
                    ),
                  ),
                  const SizedBox(height: 10),
                  ...past.map(
                    (order) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ClientOrderCard(
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

class _ClientOrderCard extends StatelessWidget {
  const _ClientOrderCard({required this.order, required this.controller});

  final Map<String, dynamic> order;
  final OrderFollowUpController controller;

  int get _id => (order['id'] as num?)?.toInt() ?? 0;

  String get _status => (order['status'] as String?) ?? 'paid';

  bool get _isStorePickup => order['delivery_mode'] == 'pickup';

  String get _supplierName {
    final supplier = order['supplier'];
    if (supplier is! Map) return 'Fournisseur';

    final agree = supplier['fournisseur_agree'];
    if (agree is Map && agree['nom_boutique'] is String) {
      return agree['nom_boutique'] as String;
    }

    return supplier['name'] as String? ?? 'Fournisseur';
  }

  /// En retrait magasin, le client présente son code dès que la commande est
  /// prête. En livraison, il ne remet le sien qu'une fois le livreur en route
  /// avec le colis — avant, le code n'a aucune raison d'être à l'écran.
  bool get _showCode => _isStorePickup
      ? _status == 'prepared'
      : _status == 'driver_picked_up';

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
                      _supplierName,
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
          Row(
            children: [
              Icon(
                _isStorePickup
                    ? Icons.storefront_outlined
                    : Icons.local_shipping_outlined,
                size: 16,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                _isStorePickup ? 'Retrait en magasin' : 'Livraison à domicile',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
              const Spacer(),
              Text(
                Formatters.fcfa((order['total_amount'] as num?)?.toInt() ?? 0),
                style: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          if (_showCode) ...[
            const SizedBox(height: 14),
            CodeVerificationCard(
              title: _isStorePickup ? 'Code de retrait' : 'Code de réception',
              orderLabel: 'Commande #$_id',
              accentColor: AppColors.client,
              backgroundColor: AppColors.clientSoft,
              instruction: _isStorePickup
                  ? 'Présentez ce code au comptoir pour récupérer votre commande.'
                  : 'Communiquez ce code au livreur APRÈS avoir reçu votre colis.',
              onReveal: () => controller.revealCode(_id),
            ),
            if (!_isStorePickup) ...[
              const SizedBox(height: 10),
              // Si le livreur n'a pas de réseau à la porte, le client fait
              // avancer la commande depuis son propre appareil.
              SizedBox(
                width: double.infinity,
                child: Obx(() {
                  final busy = controller.confirmingOrderId.value == _id;

                  return OutlinedButton.icon(
                    onPressed: busy
                        ? null
                        : () => controller.confirmFromCounterparty(
                              _id,
                              isPickup: false,
                            ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.client,
                      side: const BorderSide(color: AppColors.client),
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
                        : const Icon(Icons.check_circle_outline, size: 18),
                    label: Text(
                      busy ? 'Confirmation…' : 'J\'ai reçu mon colis',
                    ),
                  );
                }),
              ),
            ],
          ],
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
            Icons.shopping_bag_outlined,
            size: 48,
            color: AppColors.textSecondary.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 12),
          const Text(
            'Aucune commande',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Vos commandes de matériaux apparaîtront ici,\navec le code à présenter au retrait.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}
