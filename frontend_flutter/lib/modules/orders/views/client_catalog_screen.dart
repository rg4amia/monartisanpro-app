import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/artisan_cart_controller.dart';
import '../controllers/order_controller.dart';
import 'order_checkout_screen.dart';

class ClientCatalogScreen extends StatefulWidget {
  const ClientCatalogScreen({super.key});

  @override
  State<ClientCatalogScreen> createState() => _ClientCatalogScreenState();
}

class _ClientCatalogScreenState extends State<ClientCatalogScreen> {
  final TextEditingController _searchController = TextEditingController();
  final RxString _searchQuery = ''.obs;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final OrderController controller = Get.find<OrderController>();
    final ArtisanCartController artisanCart =
        Get.isRegistered<ArtisanCartController>()
            ? Get.find<ArtisanCartController>()
            : Get.put(ArtisanCartController());

    final bool isArtisan = StorageService.getRole() == 'artisan';
    final supplier = controller.selectedSupplier.value;
    final shopName = supplier?.shopName ?? 'Quincaillerie';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              shopName,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 17,
                color: AppColors.textPrimary,
              ),
            ),
            const Text(
              'Catalogue des articles',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.normal,
              ),
            ),
          ],
        ),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        iconTheme: const IconThemeData(color: AppColors.textPrimary),
        elevation: 0.5,
        actions: [
          Obx(() {
            final count =
                isArtisan ? artisanCart.cartCount : controller.cartCount;
            if (count == 0) return const SizedBox.shrink();
            return IconButton(
              icon: const Icon(Icons.delete_outline, color: AppColors.danger),
              tooltip: 'Vider le panier',
              onPressed: () {
                Get.dialog(
                  AlertDialog(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    title: const Text(
                      'Vider le panier ?',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                    content: const Text(
                      'Voulez-vous vraiment retirer tous les articles de votre panier ?',
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Get.back(),
                        child: const Text(
                          'Annuler',
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      ),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.danger,
                        ),
                        onPressed: () {
                          Get.back();
                          if (isArtisan) {
                            artisanCart.clearCart();
                          } else {
                            controller.clearCart();
                          }
                          Get.snackbar(
                            'Panier vidé',
                            'Tous les articles ont été retirés',
                          );
                        },
                        child: const Text(
                          'Vider',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                );
              },
            );
          }),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: (val) => _searchQuery.value = val,
              decoration: InputDecoration(
                hintText: 'Rechercher un article...',
                prefixIcon:
                    const Icon(Icons.search, color: AppColors.textSecondary),
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
                contentPadding:
                    const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide:
                      const BorderSide(color: AppColors.primary, width: 1.5),
                ),
              ),
            ),
          ),
          Expanded(
            child: Obx(() {
              if (controller.isLoading.value) {
                return const Center(
                  child: CircularProgressIndicator(color: AppColors.primary),
                );
              }

              if (controller.supplierProducts.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.category_outlined,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Aucun article disponible pour le moment',
                        style: TextStyle(color: Colors.grey[600], fontSize: 15),
                      ),
                    ],
                  ),
                );
              }

              final products = controller.supplierProducts.where((p) {
                final query = _searchQuery.value.toLowerCase().trim();
                if (query.isEmpty) return true;
                final nameMatch = p.name.toLowerCase().contains(query);
                final descMatch =
                    (p.description ?? '').toLowerCase().contains(query);
                final skuMatch = (p.sku ?? '').toLowerCase().contains(query);
                return nameMatch || descMatch || skuMatch;
              }).toList();

              if (products.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.search_off_outlined,
                        size: 64,
                        color: Colors.grey[400],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Aucun article ne correspond à votre recherche',
                        style: TextStyle(color: Colors.grey[600], fontSize: 15),
                      ),
                    ],
                  ),
                );
              }

              return ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                itemCount: products.length,
                itemBuilder: (context, index) {
                  final product = products[index];

                  // Calcul du prix unitaire TTC pour l'affichage client (+3% de frais plateforme)
                  // L'artisan voit le prix HT/fournisseur car les taxes/commissions sont appliquées globalement dans le devis
                  final int displayPrice = isArtisan
                      ? product.unitPrice
                      : (product.unitPrice * 1.03).round();
                  final priceFormatted =
                      displayPrice.toString().replaceAllMapped(
                            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
                            (Match m) => '${m[1]} ',
                          );

                  return Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                      side: const BorderSide(color: Color(0xFFE2E8F0)),
                    ),
                    color: Colors.white,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Image du produit avec fallback icône
                          Container(
                            height: 72,
                            width: 72,
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(12),
                              border:
                                  Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: (product.imageUrl != null &&
                                      product.imageUrl!.trim().isNotEmpty)
                                  ? Image.network(
                                      product.imageUrl!,
                                      fit: BoxFit.cover,
                                      errorBuilder:
                                          (context, error, stackTrace) =>
                                              const Icon(
                                        Icons.build_circle_outlined,
                                        color: AppColors.primary,
                                        size: 32,
                                      ),
                                    )
                                  : const Icon(
                                      Icons.build_circle_outlined,
                                      color: AppColors.primary,
                                      size: 32,
                                    ),
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  product.name,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  product.description ??
                                      'Aucune description disponible',
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                    fontSize: 13,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 8,
                                        vertical: 2,
                                      ),
                                      decoration: BoxDecoration(
                                        color: product.stockQuantity > 0
                                            ? Colors.green
                                                .withValues(alpha: 0.1)
                                            : Colors.red.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        product.stockQuantity > 0
                                            ? 'En stock: ${product.stockQuantity}'
                                            : 'Rupture de stock',
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          color: product.stockQuantity > 0
                                              ? Colors.green[800]
                                              : Colors.red[800],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      '$priceFormatted FCFA',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w900,
                                        fontSize: 16,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                    // Contrôles de quantité réactifs
                                    Row(
                                      children: [
                                        IconButton(
                                          icon: const Icon(
                                            Icons.remove_circle_outline,
                                            color: AppColors.textSecondary,
                                          ),
                                          onPressed: () {
                                            if (isArtisan) {
                                              artisanCart
                                                  .removeFromCart(product);
                                            } else {
                                              controller
                                                  .removeFromCart(product);
                                            }
                                          },
                                        ),
                                        Obx(() {
                                          final count = isArtisan
                                              ? artisanCart.getProductQuantity(
                                                  product.id,
                                                )
                                              : controller.getProductQuantity(
                                                  product.id,
                                                );
                                          return Text(
                                            '$count',
                                            style: const TextStyle(
                                              fontSize: 15,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          );
                                        }),
                                        IconButton(
                                          icon: const Icon(
                                            Icons.add_circle_outline,
                                            color: AppColors.primary,
                                          ),
                                          onPressed: () {
                                            if (isArtisan) {
                                              artisanCart.addToCart(product);
                                            } else {
                                              controller.addToCart(product);
                                            }
                                          },
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              );
            }),
          ),
        ],
      ),
      bottomNavigationBar: Obx(() {
        final count = isArtisan ? artisanCart.cartCount : controller.cartCount;
        if (count == 0) return const SizedBox.shrink();

        final int totalDisplayPrice =
            isArtisan ? artisanCart.totalAmount : controller.totalTtc;
        final totalFormatted = totalDisplayPrice.toString().replaceAllMapped(
              RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
              (Match m) => '${m[1]} ',
            );

        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 12,
                offset: const Offset(0, -4),
              ),
            ],
            borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            '$count ${count > 1 ? "articles" : "article"}',
                            style: const TextStyle(
                              color: AppColors.primary,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        const Text(
                          'Total TTC : ',
                          style: TextStyle(
                            fontSize: 13,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        Text(
                          '$totalFormatted FCFA',
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      if (isArtisan) {
                        Get.back();
                        Get.snackbar(
                          'Panier Devis mis à jour',
                          'Vos articles ont été ajoutés au panier pour le devis.',
                          backgroundColor: AppColors.success,
                          colorText: Colors.white,
                        );
                      } else {
                        Get.to(
                          () => const OrderCheckoutScreen(),
                          arguments: {
                            'supplier_id': supplier?.id,
                            'items': controller.getCartItemsPayload(),
                          },
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                    ),
                    icon: const Icon(Icons.shopping_cart_checkout, size: 20),
                    label: Text(
                      isArtisan ? 'TERMINER MES AJOUTS' : 'PASSER LA COMMANDE',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      }),
    );
  }
}
