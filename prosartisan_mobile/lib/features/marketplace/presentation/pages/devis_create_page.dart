import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/presentation/controllers/devis_controller.dart';

class DevisCreatePage extends GetView<DevisController> {
  final Mission mission;

  const DevisCreatePage({super.key, required this.mission});

  @override
  Widget build(BuildContext context) {
    // Set the mission when the page is built
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.setMission(mission);
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Créer un devis'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
      ),
      body: Form(
        key: controller.formKey,
        child: Column(
          children: [
            // Mission info header
            _buildMissionHeader(),

            // Line items section
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildSectionTitle('Lignes du devis'),
                    const SizedBox(height: 16),

                    // Add line item form
                    _buildAddLineItemForm(),

                    const SizedBox(height: 24),

                    // Line items list
                    _buildLineItemsList(),

                    const SizedBox(height: 24),

                    // Total summary
                    _buildTotalSummary(),
                  ],
                ),
              ),
            ),

            // Submit button
            _buildSubmitButton(),
          ],
        ),
      ),
    );
  }

  Widget _buildMissionHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.blue[50],
        border: Border(bottom: BorderSide(color: Colors.grey[300]!)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Mission: ${mission.category.displayName}',
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Colors.blue,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            mission.description,
            style: const TextStyle(fontSize: 14),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 8),
          Text(
            'Budget: ${_formatCurrency(mission.budgetMin)} - ${_formatCurrency(mission.budgetMax)}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: Colors.green,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: Get.textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.bold,
        color: Colors.blue[700],
      ),
    );
  }

  Widget _buildAddLineItemForm() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ajouter une ligne',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),

            // Description field
            TextFormField(
              controller: controller.descriptionController,
              decoration: const InputDecoration(
                labelText: 'Description',
                hintText: 'Ex: Tuyau PVC 32mm',
                border: OutlineInputBorder(),
              ),
            ),

            const SizedBox(height: 16),

            // Quantity and unit price row
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: controller.quantityController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Quantité',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  flex: 3,
                  child: TextFormField(
                    controller: controller.unitPriceController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Prix unitaire',
                      suffixText: 'FCFA',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 16),

            // Type selector
            Obx(
              () => Row(
                children: [
                  const Text('Type: '),
                  const SizedBox(width: 16),
                  Expanded(
                    child: SegmentedButton<DevisLineType>(
                      segments: const [
                        ButtonSegment(
                          value: DevisLineType.material,
                          label: Text('Matériel'),
                          icon: Icon(Icons.build),
                        ),
                        ButtonSegment(
                          value: DevisLineType.labor,
                          label: Text('Main d\'œuvre'),
                          icon: Icon(Icons.person),
                        ),
                      ],
                      selected: controller.selectedLineType != null
                          ? {controller.selectedLineType!}
                          : <DevisLineType>{},
                      onSelectionChanged: (Set<DevisLineType> selection) {
                        if (selection.isNotEmpty) {
                          controller.setLineType(selection.first);
                        }
                      },
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Add button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: controller.addLineItem,
                icon: const Icon(Icons.add),
                label: const Text('Ajouter la ligne'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLineItemsList() {
    return Obx(() {
      if (controller.lineItems.isEmpty) {
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              children: [
                Icon(Icons.receipt_long, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 16),
                Text(
                  'Aucune ligne ajoutée',
                  style: TextStyle(color: Colors.grey[600]),
                ),
                const SizedBox(height: 8),
                Text(
                  'Ajoutez des lignes pour créer votre devis',
                  style: TextStyle(color: Colors.grey[500], fontSize: 12),
                ),
              ],
            ),
          ),
        );
      }

      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Lignes du devis (${controller.lineItems.length})',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),

          ...controller.lineItems.asMap().entries.map((entry) {
            final index = entry.key;
            final item = entry.value;

            return Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: item.type == DevisLineType.material
                      ? Colors.orange
                      : Colors.blue,
                  child: Icon(
                    item.type == DevisLineType.material
                        ? Icons.build
                        : Icons.person,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
                title: Text(item.description),
                subtitle: Text(
                  '${item.quantity} x ${_formatCurrency(item.unitPrice)} = ${_formatCurrency(item.total)}',
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.delete, color: Colors.red),
                  onPressed: () => controller.removeLineItem(index),
                ),
              ),
            );
          }).toList(),
        ],
      );
    });
  }

  Widget _buildTotalSummary() {
    return Obx(() {
      if (controller.lineItems.isEmpty) return const SizedBox.shrink();

      return Card(
        color: Colors.blue[50],
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Matériel:'),
                  Text(
                    _formatCurrency(controller.totalMaterialsAmount),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Main d\'œuvre:'),
                  Text(
                    _formatCurrency(controller.totalLaborAmount),
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Total:',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  Text(
                    _formatCurrency(controller.totalAmount),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Colors.blue,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    });
  }

  Widget _buildSubmitButton() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Colors.grey[300]!)),
      ),
      child: Obx(
        () => SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton(
            onPressed: controller.isLoading || !controller.canSubmitDevis
                ? null
                : controller.submitDevis,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.blue[600],
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: controller.isLoading
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text(
                    'Soumettre le devis',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
          ),
        ),
      ),
    );
  }

  String _formatCurrency(double amount) {
    return '${amount.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA';
  }
}
