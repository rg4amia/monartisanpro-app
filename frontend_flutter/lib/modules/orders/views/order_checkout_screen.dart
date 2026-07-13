import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/order_controller.dart';

class OrderCheckoutScreen extends StatefulWidget {
  const OrderCheckoutScreen({super.key});

  @override
  State<OrderCheckoutScreen> createState() => _OrderCheckoutScreenState();
}

class _OrderCheckoutScreenState extends State<OrderCheckoutScreen> {
  final OrderController controller = Get.find<OrderController>();

  late int supplierId;
  late List<Map<String, dynamic>> items;

  String deliveryMode = 'delivery';
  String vehicleClass = 'moto';
  double surgeMultiplier = 1.0;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>? ?? {};
    supplierId = args['supplier_id'] ?? 1;
    items = List<Map<String, dynamic>>.from(args['items'] ?? []);
  }

  int get deliveryCost {
    if (deliveryMode != 'delivery') return 0;
    int base = 1250;
    int addon = 0;
    if (vehicleClass == 'voiture') {
      addon = 1500;
    } else if (vehicleClass == 'cargo') {
      addon = 3000;
    }
    return ((base + addon) * surgeMultiplier).round();
  }

  int get totalOrderAmount {
    return controller.subtotal + controller.platformFee + deliveryCost;
  }

  void _submit() async {
    final success = await controller.createOrder(
      supplierId: supplierId,
      deliveryMode: deliveryMode,
      items: items,
      vehicleClass: deliveryMode == 'delivery' ? vehicleClass : null,
      surgeMultiplier: deliveryMode == 'delivery' ? surgeMultiplier : null,
    );

    if (success) {
      Get.back(); // Retour après succès
    }
  }

  @override
  Widget build(BuildContext context) {
    final shopName = controller.selectedSupplier.value?.shopName ?? 'Quincaillerie';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Finaliser la Commande',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0.5,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Section 1 : Récapitulatif du panier
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Articles chez $shopName',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                  ),
                  const Divider(height: 24),
                  ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: controller.cart.length,
                    itemBuilder: (context, index) {
                      final productId = controller.cart.keys.elementAt(index);
                      final qty = controller.cart.values.elementAt(index);
                      final product = controller.supplierProducts.firstWhereOrNull((p) => p.id == productId);

                      if (product == null) return const SizedBox.shrink();
                      final itemPrice = (product.unitPrice * qty);

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8.0),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                '${product.name} (x$qty)',
                                style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
                              ),
                            ),
                            Text(
                              '${itemPrice.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Section 2 : Mode de récupération
            const Text(
              'Mode de récupération',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 10),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(
                  value: 'pickup',
                  label: Text('Retrait magasin'),
                  icon: Icon(Icons.storefront),
                ),
                ButtonSegment(
                  value: 'delivery',
                  label: Text('Livraison'),
                  icon: Icon(Icons.delivery_dining),
                ),
              ],
              selected: {deliveryMode},
              onSelectionChanged: (set) {
                setState(() => deliveryMode = set.first);
              },
            ),
            const SizedBox(height: 20),

            // Options de livraison
            if (deliveryMode == 'delivery') ...[
              const Text(
                'Type de véhicule',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                value: vehicleClass,
                decoration: InputDecoration(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  filled: true,
                  fillColor: Colors.white,
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.primary),
                  ),
                ),
                items: const [
                  DropdownMenuItem(value: 'moto', child: Text('Moto (Standard)')),
                  DropdownMenuItem(value: 'voiture', child: Text('Voiture (+1 500 FCFA)')),
                  DropdownMenuItem(value: 'cargo', child: Text('Cargo (+3 000 FCFA)')),
                ],
                onChanged: (val) => setState(() => vehicleClass = val!),
              ),
              const SizedBox(height: 20),

              const Text(
                'Surge Pricing (Multiplicateur)',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 8),
              Slider(
                value: surgeMultiplier,
                min: 1.0,
                max: 3.0,
                divisions: 20,
                activeColor: AppColors.primary,
                inactiveColor: Colors.grey[200],
                label: '${surgeMultiplier.toStringAsFixed(1)}x',
                onChanged: (val) => setState(() => surgeMultiplier = val),
              ),
              Text(
                'Majoration actuelle: ${surgeMultiplier.toStringAsFixed(1)}x (ex: intempéries, nuit)',
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
              const SizedBox(height: 20),
            ],

            // Section 3 : Détail de facturation TTC
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Récapitulatif Financier',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                  ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Articles (HT)', style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                      Text(
                        '${controller.subtotal.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.textPrimary, fontSize: 14, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Frais Plateforme (3%)', style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                      Text(
                        '${controller.platformFee.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.textPrimary, fontSize: 14, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  if (deliveryMode == 'delivery') ...[
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Frais de Livraison', style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                        Text(
                          '${deliveryCost.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                          style: const TextStyle(color: AppColors.textPrimary, fontSize: 14, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ],
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total Général TTC',
                        style: TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        '${totalOrderAmount.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.primary, fontSize: 18, fontWeight: FontWeight.w900),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),

            // Bouton de validation
            Obx(() => ElevatedButton(
              onPressed: controller.isSubmitting.value ? null : _submit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
              child: controller.isSubmitting.value
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : const Text(
                      'Payer et Confirmer la Commande',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
            )),
          ],
        ),
      ),
    );
  }
}
