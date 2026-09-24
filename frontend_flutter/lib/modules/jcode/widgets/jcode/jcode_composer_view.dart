import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/supplier_model.dart';
import '../../controllers/jcode_controller.dart';
import 'jcode_item_tiles.dart';
import 'jcode_section_card.dart';

class JcodeComposerView extends StatefulWidget {
  final JcodeController controller;
  final TextEditingController missionIdCtrl;
  final Future<void> Function() onAddCustomItem;

  const JcodeComposerView({
    super.key,
    required this.controller,
    required this.missionIdCtrl,
    required this.onAddCustomItem,
  });

  @override
  State<JcodeComposerView> createState() => _ComposerViewState();
}

class _ComposerViewState extends State<JcodeComposerView> {
  final TextEditingController _searchController = TextEditingController();
  final RxString _searchQuery = ''.obs;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        await widget.controller.loadSuppliers();
        final supplier = widget.controller.selectedSupplier.value;
        if (supplier != null) {
          await widget.controller.loadSupplierProducts(supplier.id);
        }
      },
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          JcodeSectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Créer une commande matériaux',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Choisissez un fournisseur, ajoutez les articles du catalogue ou vos articles personnalisés, puis générez le J-Code.',
                  style: TextStyle(color: AppColors.textSecondary, height: 1.4),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: widget.missionIdCtrl,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'ID de la mission',
                    hintText: 'Exemple: 125',
                    prefixIcon: Icon(Icons.work_outline),
                  ),
                ),
                const SizedBox(height: 12),
                Obx(
                  () => SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: widget.controller.isImportingDevis.value
                          ? null
                          : () {
                              final missionId = int.tryParse(
                                widget.missionIdCtrl.text.trim(),
                              );
                              if (missionId == null || missionId <= 0) {
                                Get.snackbar(
                                  'Mission invalide',
                                  'Renseignez d\'abord un ID de mission valide pour importer le devis.',
                                  snackPosition: SnackPosition.TOP,
                                );
                                return;
                              }
                              widget.controller
                                  .importMaterialsFromMission(missionId);
                            },
                      icon: widget.controller.isImportingDevis.value
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.file_download_outlined),
                      label: Text(
                        widget.controller.isImportingDevis.value
                            ? 'Import en cours...'
                            : 'Importer les matériaux du devis',
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: Obx(() {
              final suppliers = widget.controller.suppliers;
              final selectedSupplier = widget.controller.selectedSupplier.value;

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Fournisseur',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: widget.controller.loadSuppliers,
                        icon: const Icon(Icons.refresh),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (widget.controller.isSuppliersLoading.value &&
                      suppliers.isEmpty)
                    const Center(child: CircularProgressIndicator())
                  else if (suppliers.isEmpty)
                    const Text(
                      'Aucun fournisseur agréé disponible pour le moment.',
                      style: TextStyle(color: AppColors.textSecondary),
                    )
                  else
                    DropdownButtonFormField<int>(
                      initialValue: selectedSupplier?.id,
                      decoration: const InputDecoration(
                        labelText: 'Sélectionnez un fournisseur',
                        prefixIcon: Icon(Icons.storefront_outlined),
                      ),
                      items: suppliers
                          .map(
                            (supplier) => DropdownMenuItem<int>(
                              value: supplier.id,
                              child: Text(
                                '${supplier.shopName} (${supplier.activeProductsCount} articles)',
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        SupplierModel? supplier;
                        for (final candidate in suppliers) {
                          if (candidate.id == value) {
                            supplier = candidate;
                            break;
                          }
                        }
                        widget.controller.selectSupplier(supplier);
                      },
                    ),
                  if (selectedSupplier != null) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            selectedSupplier.shopName,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            Formatters.phone(selectedSupplier.phone),
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${selectedSupplier.activeProductsCount} articles disponibles',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              );
            }),
          ),
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: Obx(() {
              final supplier = widget.controller.selectedSupplier.value;
              final products = widget.controller.supplierProducts;

              final filteredProducts = products.where((p) {
                final query = _searchQuery.value.toLowerCase().trim();
                if (query.isEmpty) return true;
                final nameMatch = p.name.toLowerCase().contains(query);
                final descMatch =
                    (p.description ?? '').toLowerCase().contains(query);
                final skuMatch = (p.sku ?? '').toLowerCase().contains(query);
                return nameMatch || descMatch || skuMatch;
              }).toList();

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Articles du catalogue',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      TextButton.icon(
                        onPressed:
                            supplier == null ? null : widget.onAddCustomItem,
                        icon: const Icon(Icons.add_circle_outline),
                        label: const Text('Article hors catalogue'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (supplier != null && products.isNotEmpty) ...[
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: TextField(
                        controller: _searchController,
                        onChanged: (val) => _searchQuery.value = val,
                        decoration: InputDecoration(
                          hintText: 'Rechercher un article...',
                          prefixIcon: const Icon(
                            Icons.search,
                            color: AppColors.textSecondary,
                          ),
                          suffixIcon: Obx(
                            () => _searchQuery.value.isNotEmpty
                                ? IconButton(
                                    icon: const Icon(
                                      Icons.clear,
                                      color: AppColors.textSecondary,
                                    ),
                                    onPressed: () {
                                      _searchController.clear();
                                      _searchQuery.value = '';
                                    },
                                  )
                                : const SizedBox.shrink(),
                          ),
                          filled: true,
                          fillColor: Colors.white,
                          contentPadding: const EdgeInsets.symmetric(
                            vertical: 0,
                            horizontal: 16,
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide:
                                const BorderSide(color: Color(0xFFE2E8F0)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(
                              color: AppColors.accent,
                              width: 1.5,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                  if (supplier == null)
                    const Text(
                      'Choisissez un fournisseur pour afficher son catalogue.',
                      style: TextStyle(color: AppColors.textSecondary),
                    )
                  else if (widget.controller.isCatalogLoading.value &&
                      products.isEmpty)
                    const Center(child: CircularProgressIndicator())
                  else if (products.isEmpty)
                    const Text(
                      'Ce fournisseur n\'a pas encore publié d\'article actif.',
                      style: TextStyle(color: AppColors.textSecondary),
                    )
                  else if (filteredProducts.isEmpty)
                    const Text(
                      'Aucun article ne correspond à votre recherche.',
                      style: TextStyle(color: AppColors.textSecondary),
                    )
                  else
                    ...filteredProducts.map(
                      (product) => SupplierProductTile(
                        product: product,
                        onAdd: () =>
                            widget.controller.addCatalogProduct(product),
                      ),
                    ),
                ],
              );
            }),
          ),
          const SizedBox(height: 16),
          Obx(() {
            final items = widget.controller.draftItems;

            return JcodeSectionCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Demande en cours',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (items.isEmpty)
                    const Text(
                      'Ajoutez des articles du catalogue ou des articles hors catalogue pour préparer la commande.',
                      style: TextStyle(color: AppColors.textSecondary),
                    )
                  else
                    ...items.map(
                      (item) => JcodeDraftItemTile(
                        item: item,
                        onIncrement: () =>
                            widget.controller.updateDraftQuantity(
                          item,
                          item.quantity + 1,
                        ),
                        onDecrement: () =>
                            widget.controller.updateDraftQuantity(
                          item,
                          item.quantity - 1,
                        ),
                        onRemove: () => widget.controller.removeDraftItem(item),
                      ),
                    ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Montant matériaux',
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        Text(
                          Formatters.fcfa(widget.controller.draftTotal),
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: AppColors.success,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: widget.controller.isLoading.value
                          ? null
                          : () {
                              final missionId = int.tryParse(
                                widget.missionIdCtrl.text.trim(),
                              );
                              if (missionId == null || missionId <= 0) {
                                Get.snackbar(
                                  'Mission invalide',
                                  'Renseignez un ID de mission valide.',
                                  snackPosition: SnackPosition.TOP,
                                );
                                return;
                              }
                              widget.controller
                                  .generateJcodeForDraft(missionId);
                            },
                      icon: widget.controller.isLoading.value
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.qr_code_2),
                      label: Text(
                        widget.controller.isLoading.value
                            ? 'Génération...'
                            : 'Générer le J-Code',
                      ),
                    ),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}
