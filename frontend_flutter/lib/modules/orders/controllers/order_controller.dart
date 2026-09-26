import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/payments/operator_payment_runner.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/json_readers.dart';
import '../../../data/models/payment_model.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../data/repositories/supplier_catalog_repository.dart';

class OrderController extends GetxController {
  OrderController({OperatorPaymentRunner? paymentRunner})
      : _paymentRunner = paymentRunner ?? OperatorPaymentRunner();

  final OrderRepository _repo = OrderRepository();
  final SupplierCatalogRepository _catalogRepo = SupplierCatalogRepository();
  final OperatorPaymentRunner _paymentRunner;

  /// Issue du paiement ouvert à la création de la dernière commande
  /// (Chantier 11) : `null` si le paiement n'a pas pu être ouvert — la
  /// commande reste à régler depuis « Mes commandes » avant l'échéance.
  final lastPaymentOutcome = Rxn<OperatorPaymentOutcome>();

  /// Message du serveur accompagnant la création (échec d'ouverture du
  /// paiement, par exemple).
  final lastOrderMessage = RxnString();

  final isSubmitting = false.obs;
  final isLoading = false.obs;
  final errorMsg = RxnString();

  // Liste des fournisseurs agréés
  final approvedSuppliers = <SupplierModel>[].obs;

  // Catalogue du fournisseur sélectionné
  final supplierProducts = <SupplierProductModel>[].obs;
  final selectedSupplier = Rxn<SupplierModel>();

  // Panier réactif : product_id -> quantity et cache des produits
  final cart = <int, int>{}.obs;
  final cartProducts = <int, SupplierProductModel>{}.obs;

  @override
  void onInit() {
    super.onInit();
    // `loadApprovedSuppliers` modifie `isLoading` (.obs) : si ce contrôleur
    // est instancié via `Get.put()` pendant le montage d'un widget (cas
    // courant, ex. `final controller = Get.put(OrderController());` en
    // initialiseur de champ), onInit() s'exécute au même instant et la mise
    // à jour réactive survient pendant une passe de build, faisant planter
    // le Obx qui l'observe (« setState() or markNeedsBuild() called during
    // build »). Reporté après la première frame, ce risque disparaît.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      loadApprovedSuppliers();
    });
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

  // Sélectionner un fournisseur sans contraindre ni écraser les articles d'autres quincailleries
  void selectSupplier(SupplierModel supplier, {Function()? onConfirmed}) {
    selectedSupplier.value = supplier;
    loadSupplierProducts(supplier.id);
    if (onConfirmed != null) onConfirmed();
  }

  // Gestion du panier multi-fournisseurs
  void addToCart(SupplierProductModel product) {
    cartProducts[product.id] = product;
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
      cartProducts.remove(product.id);
    }
  }

  void clearCart() {
    cart.clear();
    cartProducts.clear();
  }

  int getProductQuantity(int productId) {
    return cart[productId] ?? 0;
  }

  int get cartCount => cart.values.fold(0, (sum, val) => sum + val);

  int get subtotal {
    int total = 0;
    for (var entry in cart.entries) {
      final product = cartProducts[entry.key] ??
          supplierProducts.firstWhereOrNull((p) => p.id == entry.key);
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

  List<Map<String, dynamic>> getMultiCartPackagesPayload({
    String defaultDeliveryMode = 'delivery',
    String defaultVehicleClass = 'moto',
  }) {
    final Map<int, List<Map<String, dynamic>>> bySupplier = {};
    for (var entry in cart.entries) {
      final product = cartProducts[entry.key] ??
          supplierProducts.firstWhereOrNull((p) => p.id == entry.key);
      if (product == null) continue;
      final supId = product.supplierId;
      bySupplier.putIfAbsent(supId, () => []);
      bySupplier[supId]!.add({
        'supplier_product_id': entry.key,
        'quantity': entry.value,
      });
    }

    return bySupplier.entries.map((e) {
      return {
        'supplier_id': e.key,
        'delivery_mode': defaultDeliveryMode,
        'vehicle_class': defaultVehicleClass,
        'items': e.value,
      };
    }).toList();
  }

  Future<bool> createMultiOrders({
    required List<Map<String, dynamic>> packages,
    String? promoCode,
    int? addressId,
    String paymentProvider = 'wave',
  }) async {
    if (isSubmitting.value) return false;
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final response = await _repo.createMultiOrders(
        packages: packages,
        promoCode: promoCode,
        addressId: addressId,
        paymentProvider: paymentProvider,
        paymentPhone: StorageService.getPhone(),
      );
      clearCart();
      await _settlePayment(response);
      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      Get.snackbar(
        'Erreur',
        errorMsg.value ??
            'Impossible de créer les commandes multi-fournisseurs',
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

  Future<bool> createOrder({
    required int supplierId,
    required String deliveryMode,
    required List<Map<String, dynamic>> items,
    String? vehicleClass,
    double? surgeMultiplier,
    String? promoCode,
    int? addressId,
    String paymentProvider = 'wave',
  }) async {
    if (isSubmitting.value) return false;
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final response = await _repo.createOrder(
        supplierId: supplierId,
        deliveryMode: deliveryMode,
        items: items,
        vehicleClass: vehicleClass,
        surgeMultiplier: surgeMultiplier,
        promoCode: promoCode,
        addressId: addressId,
        paymentProvider: paymentProvider,
        paymentPhone: StorageService.getPhone(),
      );
      clearCart();
      await _settlePayment(response);
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

  /// Ouvre la page de paiement renvoyée à la création de la commande et suit
  /// sa confirmation. La commande existe déjà : un paiement non abouti ne
  /// l'annule pas, elle reste à régler depuis « Mes commandes ».
  Future<void> _settlePayment(Map<String, dynamic> response) async {
    lastPaymentOutcome.value = null;
    lastOrderMessage.value = readString(response['message']);

    final payment = readMap(response['payment']);
    if (payment == null) return;

    try {
      lastPaymentOutcome.value =
          await _paymentRunner.run(PaymentInitiationModel.fromJson(payment));
    } catch (_) {
      lastPaymentOutcome.value = OperatorPaymentOutcome.pending;
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
