import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/supplier_product_model.dart';
import '../../../orders/controllers/artisan_cart_controller.dart';
import '../../controllers/devis_controller.dart';
import 'creation_empty_state.dart';
import 'creation_money_format.dart';
import 'ligne_card.dart';

/// Section matériaux du devis : import du panier artisan, ajout d'articles
/// depuis le catalogue du fournisseur retenu, lignes hors catalogue et
/// liste du matériel retenu.
class MaterialsSection extends StatelessWidget {
  const MaterialsSection({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Obx(() {
        final selectedSupplier = controller.selectedSupplier.value;
        final products = controller.supplierProducts;
        final materialLines = controller.materialLines;
        final isLoading = controller.isCatalogLoading.value;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Matériaux du devis',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 12,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                TextButton.icon(
                  onPressed: () => _importCart(),
                  icon: const Icon(Icons.download_rounded, size: 16),
                  label: const Text(
                    'Importer panier',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.success,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
                TextButton.icon(
                  onPressed: selectedSupplier == null
                      ? null
                      : () => _showCustomMaterialDialog(context),
                  icon: const Icon(Icons.add_circle_outline, size: 16),
                  label: const Text(
                    'Hors catalogue',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.primary,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              selectedSupplier == null
                  ? 'Sélectionnez d\'abord une quincaillerie partenaire pour afficher son catalogue.'
                  : 'Catalogue de ${selectedSupplier.shopName}. Ajoutez les produits nécessaires au chantier.',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 16),
            if (selectedSupplier == null)
              const CreationEmptyState(
                icon: Icons.storefront_outlined,
                message: 'Aucun fournisseur sélectionné',
                hint:
                    'Le devis matériaux doit partir d\'une quincaillerie partenaire avant ajout des articles.',
              )
            else if (isLoading)
              const Center(child: CircularProgressIndicator())
            else ...[
              if (products.isEmpty)
                const CreationEmptyState(
                  icon: Icons.inventory_2_outlined,
                  message: 'Catalogue vide',
                  hint:
                      'Ce fournisseur n\'a pas encore publié d\'articles utilisables pour le devis.',
                )
              else
                Column(
                  children: products
                      .map(
                        (product) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _SupplierProductCard(
                            product: product,
                            quantityInQuote:
                                controller.quantityForProduct(product.id),
                            onAdd: () => controller.addCatalogProduct(product),
                          ),
                        ),
                      )
                      .toList(),
                ),
              const SizedBox(height: 16),
              const Text(
                'Matériel retenu',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              if (materialLines.isEmpty)
                const CreationEmptyState(
                  icon: Icons.receipt_long_outlined,
                  message: 'Aucun matériau ajouté',
                  hint:
                      'Ajoutez des articles depuis le catalogue ou créez une ligne hors catalogue liée à ce fournisseur.',
                )
              else
                Column(
                  children: materialLines
                      .map(
                        (ligne) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: LigneCard(
                            ligne: ligne,
                            onDelete: () => controller.removeLigneItem(ligne),
                          ),
                        ),
                      )
                      .toList(),
                ),
            ],
          ],
        );
      }),
    );
  }

  void _importCart() {
    final ArtisanCartController artisanCart =
        Get.isRegistered<ArtisanCartController>()
            ? Get.find<ArtisanCartController>()
            : Get.put(ArtisanCartController());

    if (artisanCart.cart.isEmpty) {
      Get.snackbar(
        'Panier vide',
        'Votre panier ne contient aucun article à importer.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return;
    }

    controller.importArtisanCart(artisanCart.getCartLinesForDevis());
    Get.snackbar(
      'Panier importé',
      '${artisanCart.cartCount} articles ont été importés dans votre devis.',
      backgroundColor: AppColors.success,
      colorText: Colors.white,
    );
  }

  void _showCustomMaterialDialog(BuildContext context) {
    final descController = TextEditingController();
    final quantityController = TextEditingController(text: '1');
    final unitPriceController = TextEditingController();
    final skuController = TextEditingController();

    Get.dialog(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Article hors catalogue',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 18),
              TextField(
                controller: descController,
                maxLines: 2,
                decoration: InputDecoration(
                  labelText: 'Description',
                  hintText: 'Ex: Colle spéciale / raccord non listé',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: quantityController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: 'Quantité',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: unitPriceController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: 'Prix unitaire (FCFA)',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: skuController,
                decoration: InputDecoration(
                  labelText: 'Référence / SKU (optionnel)',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.back(),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        final description = descController.text.trim();
                        final quantity =
                            int.tryParse(quantityController.text.trim());
                        final unitPrice =
                            int.tryParse(unitPriceController.text.trim());

                        if (description.isEmpty ||
                            quantity == null ||
                            quantity <= 0 ||
                            unitPrice == null ||
                            unitPrice <= 0) {
                          Get.snackbar(
                            'Erreur',
                            'Renseignez une description, une quantité et un prix valides.',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        controller.addCustomMaterial(
                          description: description,
                          quantity: quantity,
                          unitPrice: unitPrice,
                          sku: skuController.text.trim().isEmpty
                              ? null
                              : skuController.text.trim(),
                        );

                        Get.back();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Ajouter'),
                    ),
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

class _SupplierProductCard extends StatelessWidget {
  const _SupplierProductCard({
    required this.product,
    required this.quantityInQuote,
    required this.onAdd,
  });

  final SupplierProductModel product;
  final int quantityInQuote;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.secondary.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: const Icon(
              Icons.inventory_2_outlined,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  formatCreationFcfa(product.unitPrice),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.success,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Stock: ${product.stockQuantity}${product.sku?.isNotEmpty == true ? ' • ${product.sku}' : ''}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (quantityInQuote > 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.supplierSoft,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$quantityInQuote dans le devis',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.success,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ElevatedButton(
                onPressed: onAdd,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  minimumSize: const Size(0, 40),
                ),
                child: const Text('Ajouter'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
