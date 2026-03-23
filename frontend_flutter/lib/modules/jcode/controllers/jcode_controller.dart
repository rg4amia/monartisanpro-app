import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/jcode_item_model.dart';
import '../../../data/models/jcode_model.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';
import '../../../data/repositories/devis_repository.dart';
import '../../../data/repositories/jcode_repository.dart';
import '../../../data/repositories/supplier_catalog_repository.dart';

class JcodeController extends GetxController {
  final JcodeRepository _repo = JcodeRepository();
  final SupplierCatalogRepository _catalogRepo = SupplierCatalogRepository();
  final DevisRepository _devisRepo = DevisRepository();

  final activeJcode = Rx<JcodeModel?>(null);
  final scanResult = Rx<Map<String, dynamic>?>(null);
  final suppliers = <SupplierModel>[].obs;
  final supplierProducts = <SupplierProductModel>[].obs;
  final myProducts = <SupplierProductModel>[].obs;
  final draftItems = <JcodeItemModel>[].obs;
  final selectedSupplier = Rx<SupplierModel?>(null);
  final isLoading = false.obs;
  final isScanning = false.obs;
  final isSuppliersLoading = false.obs;
  final isCatalogLoading = false.obs;
  final isSavingProduct = false.obs;
  final isImportingDevis = false.obs;

  String? get role => StorageService.getRole();
  int get draftTotal => draftItems.fold(0, (sum, item) => sum + item.subtotal);

  @override
  void onInit() {
    super.onInit();
    loadActiveJcode();

    if (role == 'artisan') {
      loadSuppliers();
    }

    if (role == 'fournisseur') {
      loadMyCatalogProducts();
    }
  }

  Future<void> loadActiveJcode() async {
    isLoading.value = true;
    try {
      activeJcode.value = await _repo.getActiveJcode();
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadSuppliers({String? search}) async {
    isSuppliersLoading.value = true;
    try {
      suppliers.value = await _catalogRepo.getApprovedSuppliers(search: search);
    } catch (e) {
      _showError('Impossible de charger les fournisseurs', e);
    } finally {
      isSuppliersLoading.value = false;
    }
  }

  Future<void> selectSupplier(SupplierModel? supplier) async {
    final previousSupplierId = selectedSupplier.value?.id;
    selectedSupplier.value = supplier;

    if (supplier == null) {
      supplierProducts.clear();
      draftItems.removeWhere((item) => item.isCatalog);
      return;
    }

    if (previousSupplierId != supplier.id) {
      draftItems.removeWhere((item) => item.isCatalog);
    }

    await loadSupplierProducts(supplier.id);
  }

  Future<void> loadSupplierProducts(int supplierId) async {
    isCatalogLoading.value = true;
    try {
      supplierProducts.value =
          await _catalogRepo.getSupplierProducts(supplierId);
    } catch (e) {
      supplierProducts.clear();
      _showError('Impossible de charger les articles du fournisseur', e);
    } finally {
      isCatalogLoading.value = false;
    }
  }

  void addCatalogProduct(SupplierProductModel product) {
    final index = draftItems.indexWhere(
      (item) => item.supplierProductId == product.id && item.isCatalog,
    );

    if (index >= 0) {
      final current = draftItems[index];
      updateDraftQuantity(current, current.quantity + 1);
      return;
    }

    if (product.stockQuantity <= 0) {
      Get.snackbar(
        'Stock indisponible',
        'Cet article n\'est plus disponible dans le stock du fournisseur.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFE74C3C),
        colorText: Colors.white,
      );
      return;
    }

    draftItems.add(
      JcodeItemModel(
        supplierProductId: product.id,
        source: 'catalog',
        name: product.name,
        sku: product.sku,
        quantity: 1,
        unitPrice: product.unitPrice,
        subtotal: product.unitPrice,
      ),
    );
  }

  void addCustomItem({
    required String name,
    String? sku,
    required int quantity,
    required int unitPrice,
  }) {
    draftItems.add(
      JcodeItemModel(
        source: 'custom',
        name: name.trim(),
        sku: sku?.trim().isEmpty == true ? null : sku?.trim(),
        quantity: quantity,
        unitPrice: unitPrice,
        subtotal: quantity * unitPrice,
      ),
    );
  }

  void updateDraftQuantity(JcodeItemModel item, int quantity) {
    final index = _findDraftIndex(item);
    if (index < 0) return;

    if (quantity <= 0) {
      draftItems.removeAt(index);
      return;
    }

    if (item.isCatalog) {
      SupplierProductModel? product;
      for (final candidate in supplierProducts) {
        if (candidate.id == item.supplierProductId) {
          product = candidate;
          break;
        }
      }
      if (product != null && quantity > product.stockQuantity) {
        Get.snackbar(
          'Quantité indisponible',
          'Stock disponible: ${product.stockQuantity}',
          snackPosition: SnackPosition.TOP,
        );
        return;
      }
    }

    draftItems[index] = item.copyWith(quantity: quantity);
  }

  void removeDraftItem(JcodeItemModel item) {
    final index = _findDraftIndex(item);
    if (index >= 0) {
      draftItems.removeAt(index);
    }
  }

  void resetComposer() {
    selectedSupplier.value = null;
    supplierProducts.clear();
    draftItems.clear();
  }

  Future<void> generateJcodeForDraft(int missionId) async {
    final supplier = selectedSupplier.value;
    if (supplier == null) {
      Get.snackbar(
        'Fournisseur requis',
        'Choisissez un fournisseur avant de générer le J-Code.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    if (draftItems.isEmpty) {
      Get.snackbar(
        'Articles requis',
        'Ajoutez au moins un article à cette demande.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    isLoading.value = true;
    try {
      final jcode = await _repo.createJcode(
        missionId: missionId,
        fournisseurId: supplier.id,
        montant: draftTotal,
        items: draftItems.toList(),
      );
      activeJcode.value = jcode;
      resetComposer();
      Get.snackbar(
        'J-Code généré',
        'Commande matériaux créée pour ${supplier.shopName}.',
        snackPosition: SnackPosition.TOP,
      );
    } catch (e) {
      _showError('Impossible de générer le J-Code', e);
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> importMaterialsFromMission(int missionId) async {
    isImportingDevis.value = true;
    try {
      final devis = await _resolveMissionDevis(missionId);
      final materials = devis.materialLines;

      if (materials.isEmpty) {
        Get.snackbar(
          'Aucun matériau',
          'Le devis ne contient aucune ligne matériaux à importer.',
          snackPosition: SnackPosition.TOP,
        );
        return;
      }

      draftItems
          .assignAll(materials.map((ligne) => ligne.toJcodeItem()).toList());

      Get.snackbar(
        'Matériaux importés',
        '${materials.length} ligne(s) matériaux importée(s) du devis #${devis.id}.',
        snackPosition: SnackPosition.TOP,
      );
    } catch (e) {
      _showError('Impossible d\'importer le devis matériaux', e);
    } finally {
      isImportingDevis.value = false;
    }
  }

  Future<void> loadMyCatalogProducts() async {
    isCatalogLoading.value = true;
    try {
      myProducts.value = await _catalogRepo.getMyProducts();
    } catch (e) {
      myProducts.clear();
      _showError('Impossible de charger votre catalogue', e);
    } finally {
      isCatalogLoading.value = false;
    }
  }

  Future<void> saveSupplierProduct({
    int? productId,
    required String name,
    String? sku,
    String? description,
    required int unitPrice,
    required int stockQuantity,
    String? imageUrl,
    bool isActive = true,
  }) async {
    isSavingProduct.value = true;
    try {
      final product = SupplierProductModel(
        id: productId ?? 0,
        supplierId: 0,
        name: name.trim(),
        sku: sku?.trim().isEmpty == true ? null : sku?.trim(),
        description:
            description?.trim().isEmpty == true ? null : description?.trim(),
        unitPrice: unitPrice,
        stockQuantity: stockQuantity,
        imageUrl: imageUrl?.trim().isEmpty == true ? null : imageUrl?.trim(),
        isActive: isActive,
      );

      if (productId == null) {
        await _catalogRepo.createProduct(product);
      } else {
        await _catalogRepo.updateProduct(product);
      }

      await loadMyCatalogProducts();
      Get.snackbar(
        'Catalogue mis à jour',
        productId == null
            ? 'L\'article a été ajouté au catalogue.'
            : 'L\'article a été mis à jour.',
        snackPosition: SnackPosition.TOP,
      );
    } catch (e) {
      _showError('Impossible d\'enregistrer l\'article', e);
      rethrow;
    } finally {
      isSavingProduct.value = false;
    }
  }

  Future<void> archiveSupplierProduct(SupplierProductModel product) async {
    isSavingProduct.value = true;
    try {
      await _catalogRepo.archiveProduct(product.id);
      await loadMyCatalogProducts();
      Get.snackbar(
        'Article retiré',
        '${product.name} a été retiré du catalogue.',
        snackPosition: SnackPosition.TOP,
      );
    } catch (e) {
      _showError('Impossible de retirer l\'article', e);
    } finally {
      isSavingProduct.value = false;
    }
  }

  Future<void> scanJcode(String identifier, double lat, double lng) async {
    isScanning.value = true;
    try {
      final result = await _repo.scanJcode(
        identifier: identifier,
        lat: lat,
        lng: lng,
      );
      scanResult.value = result;
      Get.toNamed(Routes.transactionConfirm, arguments: result);
    } catch (e) {
      Get.snackbar(
        'Scan refusé',
        e.toString().replaceAll('Exception:', '').trim(),
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFE74C3C),
        colorText: Colors.white,
      );
    } finally {
      isScanning.value = false;
    }
  }

  int _findDraftIndex(JcodeItemModel item) {
    return draftItems.indexWhere((candidate) {
      if (identical(candidate, item)) return true;

      if (candidate.isCatalog && item.isCatalog) {
        return candidate.supplierProductId == item.supplierProductId;
      }

      return candidate.isCustom &&
          item.isCustom &&
          candidate.name == item.name &&
          candidate.sku == item.sku &&
          candidate.unitPrice == item.unitPrice &&
          candidate.quantity == item.quantity;
    });
  }

  void _showError(String title, Object error) {
    Get.snackbar(
      title,
      error.toString().replaceAll('Exception:', '').trim(),
      snackPosition: SnackPosition.TOP,
      backgroundColor: const Color(0xFFE74C3C),
      colorText: Colors.white,
    );
  }

  Future<DevisModel> _resolveMissionDevis(int missionId) async {
    final devisList = await _devisRepo.getMissionDevis(missionId);

    if (devisList.isEmpty) {
      throw Exception('Aucun devis trouvé pour cette mission.');
    }

    for (final devis in devisList) {
      if (devis.statut == 'accepte') {
        return devis;
      }
    }

    for (final devis in devisList) {
      if (devis.statut == 'soumis') {
        return devis;
      }
    }

    return devisList.first;
  }
}
