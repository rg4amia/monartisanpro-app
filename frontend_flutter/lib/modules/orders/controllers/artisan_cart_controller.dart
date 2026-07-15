import 'package:get/get.dart';
import '../../../data/models/supplier_product_model.dart';

class ArtisanCartController extends GetxController {
  // Panier réactif : product_id -> quantity
  final cart = <int, int>{}.obs;

  // Cache des objets produits associés : product_id -> product details
  final products = <int, SupplierProductModel>{}.obs;

  void addToCart(SupplierProductModel product) {
    final qty = cart[product.id] ?? 0;
    if (qty < product.stockQuantity) {
      cart[product.id] = qty + 1;
      products[product.id] = product;
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
      products.remove(product.id);
    }
  }

  void clearCart() {
    cart.clear();
    products.clear();
  }

  int getProductQuantity(int productId) {
    return cart[productId] ?? 0;
  }

  int get cartCount => cart.values.fold(0, (sum, val) => sum + val);

  int get totalAmount {
    int total = 0;
    for (var entry in cart.entries) {
      final product = products[entry.key];
      if (product != null) {
        total += product.unitPrice * entry.value;
      }
    }
    return total;
  }

  List<Map<String, dynamic>> getCartLinesForDevis() {
    final lines = <Map<String, dynamic>>[];
    for (var entry in cart.entries) {
      final product = products[entry.key];
      if (product != null) {
        lines.add({
          'type': 'mat',
          'description': product.name,
          'montant': product.unitPrice * entry.value,
          'unit_price': product.unitPrice,
          'quantity': entry.value,
          'source': 'catalog',
          'supplier_product_id': product.id,
        });
      }
    }
    return lines;
  }
}
