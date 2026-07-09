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
  final OrderController controller = Get.put(OrderController());

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

  void _submit() async {
    final success = await controller.createOrder(
      supplierId: supplierId,
      deliveryMode: deliveryMode,
      items: items,
      vehicleClass: deliveryMode == 'delivery' ? vehicleClass : null,
      surgeMultiplier: deliveryMode == 'delivery' ? surgeMultiplier : null,
    );

    if (success) {
      Get.back(); // Return on success
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Commander (Sprint 5+)'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Mode de récupération',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'pickup', label: Text('Retrait (Click & Collect)')),
                ButtonSegment(value: 'delivery', label: Text('Livraison E-commerce')),
              ],
              selected: {deliveryMode},
              onSelectionChanged: (set) {
                setState(() => deliveryMode = set.first);
              },
            ),
            const SizedBox(height: 32),

            if (deliveryMode == 'delivery') ...[
              const Text(
                'Type de véhicule',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: vehicleClass,
                decoration: const InputDecoration(border: OutlineInputBorder()),
                items: const [
                  DropdownMenuItem(value: 'moto', child: Text('Moto (Standard)')),
                  DropdownMenuItem(value: 'voiture', child: Text('Voiture (+1500 FCFA)')),
                  DropdownMenuItem(value: 'cargo', child: Text('Cargo (Lourds)')),
                ],
                onChanged: (val) => setState(() => vehicleClass = val!),
              ),
              const SizedBox(height: 24),

              const Text(
                'Surge Pricing (Multiplicateur)',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Slider(
                value: surgeMultiplier,
                min: 1.0,
                max: 3.0,
                divisions: 20,
                label: '${surgeMultiplier.toStringAsFixed(1)}x',
                onChanged: (val) => setState(() => surgeMultiplier = val),
              ),
              Text(
                'Majoration actuelle: ${surgeMultiplier.toStringAsFixed(1)}x (ex: pluie, nuit)',
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
            ],

            const SizedBox(height: 48),
            Obx(() => ElevatedButton(
              onPressed: controller.isSubmitting.value ? null : _submit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
              ),
              child: controller.isSubmitting.value
                  ? const CircularProgressIndicator(color: Colors.white)
                  : const Text('Confirmer la commande'),
            )),
          ],
        ),
      ),
    );
  }
}
