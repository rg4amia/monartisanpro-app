import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/jcode_item_model.dart';
import '../../../data/models/jcode_model.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';
import '../controllers/jcode_controller.dart';

class JcodeScreen extends StatefulWidget {
  const JcodeScreen({super.key});

  @override
  State<JcodeScreen> createState() => _JcodeScreenState();
}

class _JcodeScreenState extends State<JcodeScreen> {
  late final JcodeController controller;
  final TextEditingController _missionIdCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    controller = Get.find<JcodeController>();

    final args = Get.arguments;
    if (args is int) {
      _missionIdCtrl.text = args.toString();
    } else if (args is Map<String, dynamic> && args['missionId'] != null) {
      _missionIdCtrl.text = args['missionId'].toString();
    }
  }

  @override
  void dispose() {
    _missionIdCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('J-Code Matériaux'),
        actions: [
          IconButton(
            onPressed: controller.loadActiveJcode,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value &&
            controller.activeJcode.value == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final jcode = controller.activeJcode.value;
        if (jcode != null) {
          return _JcodeDetail(jcode: jcode);
        }

        return _ComposerView(
          controller: controller,
          missionIdCtrl: _missionIdCtrl,
          onAddCustomItem: _showCustomItemDialog,
        );
      }),
    );
  }

  Future<void> _showCustomItemDialog() async {
    final nameCtrl = TextEditingController();
    final skuCtrl = TextEditingController();
    final quantityCtrl = TextEditingController(text: '1');
    final priceCtrl = TextEditingController();

    await Get.dialog(
      AlertDialog(
        title: const Text('Article hors catalogue'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(
                  labelText: 'Nom de l\'article',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: skuCtrl,
                decoration: const InputDecoration(
                  labelText: 'SKU / Référence (optionnel)',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: quantityCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Quantité'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: priceCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Prix unitaire (FCFA)',
                ),
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
            onPressed: () {
              final name = nameCtrl.text.trim();
              final quantity = int.tryParse(quantityCtrl.text.trim());
              final unitPrice = int.tryParse(priceCtrl.text.trim());

              if (name.isEmpty ||
                  quantity == null ||
                  quantity <= 0 ||
                  unitPrice == null ||
                  unitPrice <= 0) {
                Get.snackbar(
                  'Champs invalides',
                  'Renseignez un nom, une quantité et un prix unitaire valides.',
                  snackPosition: SnackPosition.TOP,
                );
                return;
              }

              controller.addCustomItem(
                name: name,
                sku: skuCtrl.text.trim(),
                quantity: quantity,
                unitPrice: unitPrice,
              );
              Get.back();
            },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );

    nameCtrl.dispose();
    skuCtrl.dispose();
    quantityCtrl.dispose();
    priceCtrl.dispose();
  }
}

class _ComposerView extends StatefulWidget {
  final JcodeController controller;
  final TextEditingController missionIdCtrl;
  final Future<void> Function() onAddCustomItem;

  const _ComposerView({
    required this.controller,
    required this.missionIdCtrl,
    required this.onAddCustomItem,
  });

  @override
  State<_ComposerView> createState() => _ComposerViewState();
}

class _ComposerViewState extends State<_ComposerView> {
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
          _SectionCard(
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
                              final missionId =
                                  int.tryParse(widget.missionIdCtrl.text.trim());
                              if (missionId == null || missionId <= 0) {
                                Get.snackbar(
                                  'Mission invalide',
                                  'Renseignez d\'abord un ID de mission valide pour importer le devis.',
                                  snackPosition: SnackPosition.TOP,
                                );
                                return;
                              }
                              widget.controller.importMaterialsFromMission(missionId);
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
          _SectionCard(
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
                  if (widget.controller.isSuppliersLoading.value && suppliers.isEmpty)
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
          _SectionCard(
            child: Obx(() {
              final supplier = widget.controller.selectedSupplier.value;
              final products = widget.controller.supplierProducts;

              final filteredProducts = products.where((p) {
                final query = _searchQuery.value.toLowerCase().trim();
                if (query.isEmpty) return true;
                final nameMatch = p.name.toLowerCase().contains(query);
                final descMatch = (p.description ?? '').toLowerCase().contains(query);
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
                        onPressed: supplier == null ? null : widget.onAddCustomItem,
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
                          prefixIcon: const Icon(Icons.search, color: AppColors.textSecondary),
                          suffixIcon: Obx(() => _searchQuery.value.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.clear, color: AppColors.textSecondary),
                                  onPressed: () {
                                    _searchController.clear();
                                    _searchQuery.value = '';
                                  },
                                )
                              : const SizedBox.shrink()),
                          filled: true,
                          fillColor: Colors.white,
                          contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: AppColors.accent, width: 1.5),
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
                      (product) => _SupplierProductTile(
                        product: product,
                        onAdd: () => widget.controller.addCatalogProduct(product),
                      ),
                    ),
                ],
              );
            }),
          ),
          const SizedBox(height: 16),
          Obx(() {
            final items = widget.controller.draftItems;

            return _SectionCard(
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
                      (item) => _DraftItemTile(
                        item: item,
                        onIncrement: () => widget.controller.updateDraftQuantity(
                          item,
                          item.quantity + 1,
                        ),
                        onDecrement: () => widget.controller.updateDraftQuantity(
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
                              final missionId =
                                  int.tryParse(widget.missionIdCtrl.text.trim());
                              if (missionId == null || missionId <= 0) {
                                Get.snackbar(
                                  'Mission invalide',
                                  'Renseignez un ID de mission valide.',
                                  snackPosition: SnackPosition.TOP,
                                );
                                return;
                              }
                              widget.controller.generateJcodeForDraft(missionId);
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

class _JcodeDetail extends StatelessWidget {
  final JcodeModel jcode;

  const _JcodeDetail({required this.jcode});

  @override
  Widget build(BuildContext context) {
    final isActif = jcode.statut == 'actif';

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        _SectionCard(
          child: Column(
            children: [
              if (isActif)
                QrImageView(
                  data: jcode.id.toString(),
                  version: QrVersions.auto,
                  size: 190,
                )
              else
                const Icon(Icons.qr_code, size: 180, color: AppColors.border),
              const SizedBox(height: 18),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  jcode.code,
                  style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 4,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(height: 10),
              _StatusBadge(statut: jcode.statut),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _SectionCard(
          child: Column(
            children: [
              _DetailRow(
                label: 'Mission',
                value: '#${jcode.missionId}',
              ),
              const Divider(height: 20),
              _DetailRow(
                label: 'Montant',
                value: Formatters.fcfa(jcode.montant),
                valueStyle: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  color: AppColors.success,
                ),
              ),
              const Divider(height: 20),
              _DetailRow(
                label: 'Expire le',
                value: Formatters.dateTime(jcode.expiresAt),
              ),
              if (jcode.scannedAt != null) ...[
                const Divider(height: 20),
                _DetailRow(
                  label: 'Scanné le',
                  value: Formatters.dateTime(jcode.scannedAt!),
                ),
              ],
            ],
          ),
        ),
        if (jcode.supplier != null) ...[
          const SizedBox(height: 16),
          _SectionCard(
            child: ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.storefront, color: AppColors.success),
              ),
              title: Text(
                jcode.supplier!.shopName,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(Formatters.phone(jcode.supplier!.phone)),
            ),
          ),
        ],
        if (jcode.items.isNotEmpty) ...[
          const SizedBox(height: 16),
          _SectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Articles demandés',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                ...jcode.items.map((item) => _ServedItemTile(item: item)),
              ],
            ),
          ),
        ],
        const SizedBox(height: 16),
        _SectionCard(
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.phone_in_talk, color: AppColors.primary),
            title: const Text('Code USSD'),
            subtitle: Text(
              jcode.ussdCode ?? '*144#',
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 18,
                letterSpacing: 2,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SupplierProductTile extends StatelessWidget {
  final SupplierProductModel product;
  final VoidCallback onAdd;

  const _SupplierProductTile({
    required this.product,
    required this.onAdd,
  });

  @override
  Widget build(BuildContext context) {
    final outOfStock = product.stockQuantity <= 0;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: AppColors.border.withValues(alpha: 0.8)),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.inventory_2_outlined,
                    color: AppColors.primary, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    if ((product.sku ?? '').isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        'SKU: ${product.sku}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if ((product.description ?? '').isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              product.description!,
              style: const TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    Formatters.fcfa(product.unitPrice),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.success,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    outOfStock ? 'Rupture de stock' : 'Stock: ${product.stockQuantity}',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: outOfStock ? AppColors.danger : AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: outOfStock ? null : onAdd,
                icon: const Icon(Icons.add, size: 16),
                label: const Text('Ajouter'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DraftItemTile extends StatelessWidget {
  final JcodeItemModel item;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final VoidCallback onRemove;

  const _DraftItemTile({
    required this.item,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    final badgeColor = item.isCatalog ? AppColors.info : AppColors.warning;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  item.isCatalog ? 'Catalogue' : 'Personnalisé',
                  style: TextStyle(
                    color: badgeColor,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              IconButton(
                onPressed: onRemove,
                icon: const Icon(Icons.delete_outline, color: AppColors.danger),
              ),
            ],
          ),
          if ((item.sku ?? '').isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                item.sku!,
                style: const TextStyle(color: AppColors.textSecondary),
              ),
            ),
          Row(
            children: [
              Text(
                Formatters.fcfa(item.unitPrice),
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Spacer(),
              _QtyButton(icon: Icons.remove, onTap: onDecrement),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text(
                  '${item.quantity}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
              ),
              _QtyButton(icon: Icons.add, onTap: onIncrement),
            ],
          ),
          const SizedBox(height: 10),
          Align(
            alignment: Alignment.centerRight,
            child: Text(
              Formatters.fcfa(item.subtotal),
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ServedItemTile extends StatelessWidget {
  final JcodeItemModel item;

  const _ServedItemTile({required this.item});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${item.quantity} x ${Formatters.fcfa(item.unitPrice)}',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                Formatters.fcfa(item.subtotal),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              _StatusDot(
                label: item.status == 'served' ? 'Servi' : 'Demandé',
                color: item.status == 'served'
                    ? AppColors.success
                    : AppColors.warning,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final Widget child;

  const _SectionCard({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadowColor.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: child,
    );
  }
}

class _InfoChip extends StatelessWidget {
  final String label;
  final Color color;

  const _InfoChip({required this.label, required this.color});

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

class _QtyButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _QtyButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 30,
        height: 30,
        decoration: BoxDecoration(
          color: AppColors.primary.withValues(alpha: 0.08),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, size: 18, color: AppColors.primary),
      ),
    );
  }
}

class _StatusDot extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusDot({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String statut;

  const _StatusBadge({required this.statut});

  @override
  Widget build(BuildContext context) {
    final colors = {
      'actif': AppColors.success,
      'utilise': AppColors.info,
      'expire': AppColors.danger,
    };
    final color = colors[statut] ?? AppColors.textSecondary;
    return Chip(
      label: Text(
        Formatters.jcodeStatus(statut),
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final TextStyle? valueStyle;

  const _DetailRow({
    required this.label,
    required this.value,
    this.valueStyle,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary)),
        Text(
          value,
          style: valueStyle ?? const TextStyle(fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
