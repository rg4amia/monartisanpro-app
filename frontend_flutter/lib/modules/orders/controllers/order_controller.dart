import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../data/repositories/supplier_catalog_repository.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';

class OrderController extends GetxController {
  final OrderRepository _repo = OrderRepository();
  final SupplierCatalogRepository _catalogRepo = SupplierCatalogRepository();

  final isSubmitting = false.obs;
  final isLoading = false.obs;
  final errorMsg = RxnString();

  // Liste des fournisseurs agréés
  final approvedSuppliers = <SupplierModel>[].obs;
  
  // Catalogue du fournisseur sélectionné
  final supplierProducts = <SupplierProductModel>[].obs;
  final selectedSupplier = Rxn<SupplierModel>();

  // Panier réactif : product_id -> quantity
  final cart = <int, int>{}.obs;

  @override
  void onInit() {
    super.onInit();
    loadApprovedSuppliers();
  }

  // Charger les fournisseurs agréés
  Future<void> loadApprovedSuppliers({String? search}) async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final list = await _catalogRepo.getApprovedSuppliers(search: search);
      approvedSuppliers.assignAll(list);
    } catch (e) {
      errorMsg.value = 'Erreur lors du chargement des fournisseurs';
    } finally {
      isLoading.value = false;
    }
  }

  // Charger le catalogue d'un fournisseur
  Future<void> loadSupplierProducts(int supplierId) async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final list = await _catalogRepo.getSupplierProducts(supplierId);
      supplierProducts.assignAll(list);
    } catch (e) {
      errorMsg.value = 'Erreur lors du chargement du catalogue';
    } finally {
      isLoading.value = false;
    }
  }

  // Sélectionner un fournisseur (avec confirmation si panier non vide)
  void selectSupplier(SupplierModel supplier, {Function()? onConfirmed}) {
    if (selectedSupplier.value != null &&
        selectedSupplier.value!.id != supplier.id &&
        cart.isNotEmpty) {
      Get.dialog(
        AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Changer de quincaillerie ?', style: TextStyle(fontWeight: FontWeight.bold)),
          content: Text(
            'Votre panier contient actuellement $cartCount article(s) chez "${selectedSupplier.value!.shopName}". Souhaitez-vous le vider pour commander chez "${supplier.shopName}" ?',
            style: const TextStyle(fontSize: 14, color: AppColors.textSecondary),
          ),
          actions: [
            TextButton(
              onPressed: () => Get.back(),
              child: const Text('Conserver mon panier', style: TextStyle(color: AppColors.textSecondary)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.danger,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: () {
                Get.back();
                clearCart();
                selectedSupplier.value = supplier;
                loadSupplierProducts(supplier.id);
                if (onConfirmed != null) onConfirmed();
              },
              child: const Text('Vider et Continuer', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
      return;
    }

    selectedSupplier.value = supplier;
    loadSupplierProducts(supplier.id);
    if (onConfirmed != null) onConfirmed();
  }

  // Gestion du panier
  void addToCart(SupplierProductModel product) {
    final qty = cart[product.id] ?? 0;
    if (qty < product.stockQuantity) {
      cart[product.id] = qty + 1;
    } else {
      Get.snackbar('Stock limite', 'Quantité maximale disponible atteinte');
    }
  }

  void removeFromCart(SupplierProductModel product) {
    final qty = cart[product.id] ?? 0;
    if (qty > 1) {
      cart[product.id] = qty - 1;
    } else {
      cart.remove(product.id);
    }
  }

  void clearCart() {
    cart.clear();
  }

  int getProductQuantity(int productId) {
    return cart[productId] ?? 0;
  }

  int get cartCount => cart.values.fold(0, (sum, val) => sum + val);

  int get subtotal {
    int total = 0;
    for (var entry in cart.entries) {
      final product = supplierProducts.firstWhereOrNull((p) => p.id == entry.key);
      if (product != null) {
        total += product.unitPrice * entry.value;
      }
    }
    return total;
  }

  int get platformFee {
    // 3% de frais plateforme
    return (subtotal * 0.03).round();
  }

  int get totalTtc => subtotal + platformFee;

  List<Map<String, dynamic>> getCartItemsPayload() {
    final payload = <Map<String, dynamic>>[];
    for (var entry in cart.entries) {
      payload.add({
        'supplier_product_id': entry.key,
        'quantity': entry.value,
      });
    }
    return payload;
  }

  Future<bool> createOrder({
    required int supplierId,
    required String deliveryMode,
    required List<Map<String, dynamic>> items,
    String? vehicleClass,
    double? surgeMultiplier,
    String? promoCode,
  }) async {
    if (isSubmitting.value) return false;
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      await _repo.createOrder(
        supplierId: supplierId,
        deliveryMode: deliveryMode,
        items: items,
        vehicleClass: vehicleClass,
        surgeMultiplier: surgeMultiplier,
        promoCode: promoCode,
      );
      Get.snackbar(
        'Succès',
        'Commande créée avec succès en compte séquestre !',
        backgroundColor: const Color(0xFF24734F),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );
      clearCart();
      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      Get.snackbar(
        'Erreur',
        errorMsg.value ?? 'Impossible de créer la commande',
        backgroundColor: const Color(0xFFC55E50),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 4),
      );
      return false;
    } catch (e) {
      errorMsg.value = 'Erreur inattendue : $e';
      Get.snackbar(
        'Erreur',
        errorMsg.value!,
        backgroundColor: const Color(0xFFC55E50),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  String _handleDioError(DioException e) {
    if (e.response != null) {
      dynamic data = e.response!.data;
      if (data is String) {
        try {
          data = jsonDecode(data);
        } catch (_) {}
      }
      if (data is Map) {
        if (data.containsKey('errors')) {
          final errors = data['errors'] as Map;
          if (errors.isNotEmpty) {
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return firstError.first.toString();
            }
            if (firstError is String) {
              return firstError;
            }
          }
        }
        if (data.containsKey('message')) {
          return data['message'].toString();
        }
      }
      return 'Erreur ${e.response?.statusCode} du serveur';
    }
    if (e.message != null && e.message!.isNotEmpty) {
      return 'Erreur réseau : ${e.message}';
    }
    return 'Erreur de connexion';
  }
}
