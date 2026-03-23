import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/supplier_product_model.dart';
import '../controllers/jcode_controller.dart';

class SupplierCatalogScreen extends StatefulWidget {
  const SupplierCatalogScreen({super.key});

  @override
  State<SupplierCatalogScreen> createState() => _SupplierCatalogScreenState();
}

class _SupplierCatalogScreenState extends State<SupplierCatalogScreen> {
  late final JcodeController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<JcodeController>();
    controller.loadMyCatalogProducts();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mon catalogue'),
        actions: [
          IconButton(
            onPressed: controller.loadMyCatalogProducts,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showProductDialog(context),
        icon: const Icon(Icons.add),
        label: const Text('Ajouter'),
      ),
      body: Obx(() {
        if (controller.isCatalogLoading.value &&
            controller.myProducts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (controller.myProducts.isEmpty) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.08),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.inventory_2_outlined,
                      size: 56,
                      color: AppColors.success,
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Aucun article dans votre catalogue',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Ajoutez vos articles pour enrichir le catalogue consulté par les artisans.',
                    textAlign: TextAlign.center,
                    style:
                        TextStyle(color: AppColors.textSecondary, height: 1.4),
                  ),
                ],
              ),
            ),
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.all(20),
          itemCount: controller.myProducts.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (_, index) {
            final product = controller.myProducts[index];
            return _CatalogProductCard(
              product: product,
              onEdit: () => _showProductDialog(context, product: product),
              onArchive: () => _confirmArchive(product),
            );
          },
        );
      }),
    );
  }

  Future<void> _showProductDialog(
    BuildContext context, {
    SupplierProductModel? product,
  }) async {
    final nameCtrl = TextEditingController(text: product?.name ?? '');
    final skuCtrl = TextEditingController(text: product?.sku ?? '');
    final descriptionCtrl =
        TextEditingController(text: product?.description ?? '');
    final priceCtrl = TextEditingController(
      text: product == null ? '' : product.unitPrice.toString(),
    );
    final stockCtrl = TextEditingController(
      text: product == null ? '0' : product.stockQuantity.toString(),
    );
    final imageCtrl = TextEditingController(text: product?.imageUrl ?? '');
    final isActive = (product?.isActive ?? true).obs;

    await Get.dialog(
      Obx(
        () => AlertDialog(
          title:
              Text(product == null ? 'Nouvel article' : 'Modifier l\'article'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: nameCtrl,
                  decoration: const InputDecoration(labelText: 'Nom'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: skuCtrl,
                  decoration: const InputDecoration(labelText: 'SKU'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: descriptionCtrl,
                  decoration: const InputDecoration(labelText: 'Description'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: priceCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Prix unitaire (FCFA)',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: stockCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Stock disponible',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: imageCtrl,
                  decoration: const InputDecoration(
                    labelText: 'URL image (optionnel)',
                  ),
                ),
                const SizedBox(height: 12),
                SwitchListTile.adaptive(
                  value: isActive.value,
                  onChanged: (value) => isActive.value = value,
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Article actif'),
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
              onPressed: controller.isSavingProduct.value
                  ? null
                  : () async {
                      final unitPrice = int.tryParse(priceCtrl.text.trim());
                      final stockQuantity = int.tryParse(stockCtrl.text.trim());

                      if (nameCtrl.text.trim().isEmpty ||
                          unitPrice == null ||
                          unitPrice < 0 ||
                          stockQuantity == null ||
                          stockQuantity < 0) {
                        Get.snackbar(
                          'Champs invalides',
                          'Renseignez au minimum un nom, un prix et un stock valides.',
                          snackPosition: SnackPosition.TOP,
                        );
                        return;
                      }

                      try {
                        await controller.saveSupplierProduct(
                          productId: product?.id,
                          name: nameCtrl.text,
                          sku: skuCtrl.text,
                          description: descriptionCtrl.text,
                          unitPrice: unitPrice,
                          stockQuantity: stockQuantity,
                          imageUrl: imageCtrl.text,
                          isActive: isActive.value,
                        );
                        Get.back();
                      } catch (_) {}
                    },
              child: controller.isSavingProduct.value
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(product == null ? 'Ajouter' : 'Enregistrer'),
            ),
          ],
        ),
      ),
    );

    nameCtrl.dispose();
    skuCtrl.dispose();
    descriptionCtrl.dispose();
    priceCtrl.dispose();
    stockCtrl.dispose();
    imageCtrl.dispose();
  }

  Future<void> _confirmArchive(SupplierProductModel product) async {
    await Get.dialog(
      AlertDialog(
        title: const Text('Retirer du catalogue'),
        content: Text(
          'Retirer ${product.name} du catalogue ? L\'article restera historique mais ne sera plus proposé aux artisans.',
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
              await controller.archiveSupplierProduct(product);
              Get.back();
            },
            child: const Text('Retirer'),
          ),
        ],
      ),
    );
  }
}

class _CatalogProductCard extends StatelessWidget {
  final SupplierProductModel product;
  final VoidCallback onEdit;
  final VoidCallback onArchive;

  const _CatalogProductCard({
    required this.product,
    required this.onEdit,
    required this.onArchive,
  });

  @override
  Widget build(BuildContext context) {
    final stockColor =
        product.stockQuantity > 0 ? AppColors.success : AppColors.danger;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadowColor.withValues(alpha: 0.1),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  product.name,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              PopupMenuButton<String>(
                onSelected: (value) {
                  if (value == 'edit') {
                    onEdit();
                  } else if (value == 'archive') {
                    onArchive();
                  }
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Modifier')),
                  PopupMenuItem(value: 'archive', child: Text('Retirer')),
                ],
              ),
            ],
          ),
          if ((product.description ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              product.description!,
              style: const TextStyle(
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _CatalogChip(
                label: Formatters.fcfa(product.unitPrice),
                color: AppColors.primary,
              ),
              _CatalogChip(
                label: 'Stock: ${product.stockQuantity}',
                color: stockColor,
              ),
              _CatalogChip(
                label: product.isActive ? 'Actif' : 'Inactif',
                color: product.isActive ? AppColors.info : AppColors.textMuted,
              ),
              if ((product.sku ?? '').isNotEmpty)
                _CatalogChip(label: product.sku!, color: AppColors.warning),
            ],
          ),
        ],
      ),
    );
  }
}

class _CatalogChip extends StatelessWidget {
  final String label;
  final Color color;

  const _CatalogChip({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}
