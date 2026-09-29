import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../controllers/jcode_controller.dart';
import 'jcode_item_tiles.dart';
import 'jcode_section_card.dart';
import 'supplier_picker_sheet.dart';

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

  Future<void> _pickSupplier(BuildContext context) async {
    final controller = widget.controller;
    final picked = await showSupplierPickerSheet(
      context,
      suppliers: controller.suppliers.toList(),
      selectedId: controller.selectedSupplier.value?.id,
    );
    if (picked == null) return;

    if (controller.switchingWouldDropItems(picked)) {
      final ok = await confirmDropItems(
        'Un bon destiné à un seul fournisseur ne contient que ses articles : '
        'les articles de catalogue déjà ajoutés seront retirés. '
        'Pour garder des articles de plusieurs fournisseurs, activez le bon multi-comptoirs.',
      );
      if (!ok) return;
    }
    await controller.selectSupplier(picked);
  }

  Future<void> _toggleMultiSupplier(bool enabled) async {
    final controller = widget.controller;
    if (!enabled && controller.leavingMultiWouldDropItems) {
      final ok = await confirmDropItems(
        'Vos articles viennent de plusieurs fournisseurs : un bon destiné à '
        'un seul fournisseur ne peut pas tous les garder. Les articles de '
        'catalogue seront retirés.',
      );
      if (!ok) return;
    }
    await controller.setMultiSupplierMode(enabled);
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
                  'Choisissez un fournisseur (ou un bon multi-comptoirs), ajoutez les articles nécessaires, puis générez le Bon Matériaux.',
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
              final isMulti = widget.controller.isMultiSupplierMode.value;

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Destination du bon',
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
                  const SizedBox(height: 8),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: isMulti
                          ? AppColors.secondary.withValues(alpha: 0.08)
                          : AppColors.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isMulti ? AppColors.secondary : AppColors.border,
                      ),
                    ),
                    child: SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      value: isMulti,
                      activeThumbColor: AppColors.secondary,
                      title: const Text(
                        'Bon multi-comptoirs',
                        style: TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 15,),
                      ),
                      subtitle: const Text(
                        'Valable chez tous les fournisseurs agréés (débits partiels possibles). Vous pouvez y réunir des articles de plusieurs catalogues.',
                        style: TextStyle(fontSize: 12),
                      ),
                      onChanged: _toggleMultiSupplier,
                    ),
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
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () => _pickSupplier(context),
                        icon: const Icon(Icons.storefront_outlined),
                        label: Text(
                          selectedSupplier == null
                              ? (isMulti
                                  ? 'Parcourir le catalogue d\'un fournisseur'
                                  : 'Choisir le fournisseur')
                              : 'Fournisseur : ${selectedSupplier.shopName} (changer)',
                        ),
                      ),
                    ),
                  if (isMulti) ...[
                    const SizedBox(height: 8),
                    const Text(
                      'Les articles déjà ajoutés restent dans le bon quand vous passez d\'un catalogue à l\'autre.',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
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
                          if (selectedSupplier.sector != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              selectedSupplier.sector!.name,
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
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
                        onPressed: supplier == null &&
                                !widget.controller.isMultiSupplierMode.value
                            ? null
                            : widget.onAddCustomItem,
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
                            : 'Générer le Bon Matériaux',
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
