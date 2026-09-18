import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/artisan_stock_model.dart';
import '../controllers/stock_controller.dart';

/// Stock personnel de l'artisan : matériaux/outils qu'il possède déjà,
/// distinct du catalogue d'une quincaillerie.
class StockScreen extends StatelessWidget {
  const StockScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.isRegistered<StockController>()
        ? Get.find<StockController>()
        : Get.put(StockController());

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mon stock matériaux'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        actions: [
          IconButton(
            onPressed: controller.refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showItemDialog(context, controller),
        icon: const Icon(Icons.add),
        label: const Text('Ajouter'),
        backgroundColor: AppColors.primary,
      ),
      body: RefreshIndicator(
        onRefresh: controller.refresh,
        color: AppColors.primary,
        child: Obx(() {
          if (controller.isLoading.value && controller.stockItems.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (controller.hasError.value && controller.stockItems.isEmpty) {
            return _ErrorRetryView(onRetry: controller.refresh);
          }

          if (controller.stockItems.isEmpty) {
            return _EmptyStockView(
              onAdd: () => _showItemDialog(context, controller),
            );
          }

          return ListView.separated(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 100),
            itemCount: controller.stockItems.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, index) {
              final item = controller.stockItems[index];
              return _StockItemCard(
                item: item,
                onEdit: () => _showItemDialog(context, controller, item: item),
                onDelete: () => _confirmDelete(context, controller, item),
              );
            },
          );
        }),
      ),
    );
  }

  Future<void> _showItemDialog(
    BuildContext context,
    StockController controller, {
    ArtisanStockModel? item,
  }) async {
    final descCtrl = TextEditingController(text: item?.description ?? '');
    final quantityCtrl = TextEditingController(
      text: item == null ? '1' : item.quantity.toString(),
    );
    final unitCostCtrl = TextEditingController(
      text: item == null ? '' : item.unitCost.toString(),
    );
    final condition = (item?.condition ?? 'neuf').obs;

    await Get.dialog(
      Obx(
        () => AlertDialog(
          title: Text(item == null ? 'Nouvel article' : 'Modifier l\'article'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: descCtrl,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    hintText: 'Ex: Sac de ciment 50kg, perceuse Bosch...',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: quantityCtrl,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(labelText: 'Quantité'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: unitCostCtrl,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Coût unitaire (FCFA)',
                  ),
                ),
                const SizedBox(height: 16),
                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'État',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'neuf', label: Text('Neuf')),
                    ButtonSegment(value: 'occasion', label: Text('Occasion')),
                  ],
                  selected: {condition.value},
                  onSelectionChanged: (selection) =>
                      condition.value = selection.first,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: Get.back,
              child: const Text('Annuler'),
            ),
            ElevatedButton(
              onPressed: controller.isSaving.value
                  ? null
                  : () async {
                      final description = descCtrl.text.trim();
                      final quantity =
                          int.tryParse(quantityCtrl.text.trim());
                      final unitCost =
                          int.tryParse(unitCostCtrl.text.trim());

                      if (description.isEmpty ||
                          quantity == null ||
                          quantity < 1 ||
                          unitCost == null ||
                          unitCost < 0) {
                        Get.snackbar(
                          'Champs invalides',
                          'Renseignez une description, une quantité (≥ 1) et un coût unitaire valides.',
                          snackPosition: SnackPosition.TOP,
                        );
                        return;
                      }

                      final success = await controller.saveItem(
                        id: item?.id,
                        description: description,
                        quantity: quantity,
                        unitCost: unitCost,
                        condition: condition.value,
                      );
                      if (success) {
                        Get.back();
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
              ),
              child: controller.isSaving.value
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(item == null ? 'Ajouter' : 'Enregistrer'),
            ),
          ],
        ),
      ),
    );

    descCtrl.dispose();
    quantityCtrl.dispose();
    unitCostCtrl.dispose();
  }

  Future<void> _confirmDelete(
    BuildContext context,
    StockController controller,
    ArtisanStockModel item,
  ) async {
    await Get.dialog(
      AlertDialog(
        title: const Text('Supprimer l\'article'),
        content: Text(
          'Supprimer « ${item.description} » de votre stock ? Cette action est irréversible.',
        ),
        actions: [
          TextButton(
            onPressed: Get.back,
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger,
              foregroundColor: Colors.white,
            ),
            onPressed: () async {
              Get.back();
              await controller.deleteItem(item);
            },
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
  }
}

class _StockItemCard extends StatelessWidget {
  const _StockItemCard({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  final ArtisanStockModel item;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final isNeuf = item.condition == 'neuf';
    final totalValue = item.quantity * item.unitCost;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: AppColors.cardShadow,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.description,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: (isNeuf ? AppColors.success : AppColors.warning)
                            .withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        isNeuf ? 'Neuf' : 'Occasion',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: isNeuf ? AppColors.success : AppColors.warning,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _InfoChip(label: 'Qté: ${item.quantity}'),
                    _InfoChip(label: Formatters.fcfa(item.unitCost)),
                    _InfoChip(
                      label: 'Valeur: ${Formatters.fcfa(totalValue)}',
                    ),
                  ],
                ),
              ],
            ),
          ),
          Column(
            children: [
              IconButton(
                onPressed: onEdit,
                icon: const Icon(
                  Icons.edit_outlined,
                  color: AppColors.textSecondary,
                  size: 20,
                ),
                tooltip: 'Modifier',
              ),
              IconButton(
                onPressed: onDelete,
                icon: const Icon(
                  Icons.delete_outline,
                  color: AppColors.danger,
                  size: 20,
                ),
                tooltip: 'Supprimer',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.secondary.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
      ),
    );
  }
}

class _EmptyStockView extends StatelessWidget {
  const _EmptyStockView({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(32),
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.15),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.inventory_2_outlined,
                  size: 56,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Aucun article en stock',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Ajoutez les matériaux et outils que vous possédez déjà pour les retrouver ici.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: onAdd,
                icon: const Icon(Icons.add, size: 18),
                label: const Text('Ajouter un article'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 14,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ErrorRetryView extends StatelessWidget {
  const _ErrorRetryView({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(20),
      children: [
        SizedBox(height: MediaQuery.of(context).size.height * 0.2),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.wifi_off_rounded,
                  size: 48,
                  color: AppColors.danger,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Impossible de charger votre stock',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'Vérifiez votre connexion internet\net tirez vers le bas pour réessayer.',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Réessayer'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 28,
                    vertical: 14,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 2,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
