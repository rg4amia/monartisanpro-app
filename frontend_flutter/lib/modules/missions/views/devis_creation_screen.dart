import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';
import '../controllers/devis_controller.dart';
import '../../orders/controllers/artisan_cart_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.secondary;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
  static const success = AppColors.success;
  static const successLight = AppColors.supplierSoft;
  static const warning = AppColors.accent;
  static const warningLight = AppColors.artisanSoft;
  static const danger = AppColors.danger;
}

/// Vue de création de devis pour l'artisan
///
/// WORKFLOW:
/// 1. Client crée mission + sélectionne artisan → Notification artisan
/// 2. **Artisan crée devis (cette vue)** → Notification client
/// 3. Client valide/refuse devis
class DevisCreationScreen extends StatefulWidget {
  const DevisCreationScreen({super.key});

  @override
  State<DevisCreationScreen> createState() => _DevisCreationScreenState();
}

class _DevisCreationScreenState extends State<DevisCreationScreen> {
  late final DevisController controller;
  MissionModel? mission;
  int? missionId;

  @override
  void initState() {
    super.initState();
    controller = Get.isRegistered<DevisController>()
        ? Get.find<DevisController>()
        : Get.put(DevisController());

    final args = Get.arguments;
    if (args is MissionModel) {
      mission = args;
      missionId = args.id;
    } else if (args is int) {
      missionId = args;
    }

    if (missionId != null) {
      controller.prepareDraftForMission(missionId!);
    }

    controller.loadSuppliers();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _InfoCard(
                      missionId: missionId,
                      mission: mission,
                    ),
                    const SizedBox(height: 16),
                    const _WorkflowCard(),
                    const SizedBox(height: 24),
                    _SupplierSection(controller: controller),
                    const SizedBox(height: 24),
                    _MaterialsSection(controller: controller),
                    const SizedBox(height: 24),
                    _LignesSection(controller: controller),
                    const SizedBox(height: 24),
                    _JalonsSection(controller: controller),
                    const SizedBox(height: 24),
                    _RecapSection(controller: controller),
                    const SizedBox(height: 100),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: _SubmitButton(
        controller: controller,
        missionId: missionId,
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Créer un devis',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ),
          const SizedBox(width: 40),
        ],
      ),
    );
  }
}

// ─── Info Card ────────────────────────────────────────────────────────────────
class _InfoCard extends StatelessWidget {
  final int? missionId;
  final MissionModel? mission;

  const _InfoCard({
    this.missionId,
    this.mission,
  });

  @override
  Widget build(BuildContext context) {
    final missionDescription = mission?.description?.trim();
    final location = mission?.location?.trim();
    final clientName = mission?.clientName?.trim();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.primaryLight,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.primary.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.assignment_outlined, color: _C.primary, size: 24),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Mission #${missionId ?? 'N/A'}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: _C.primary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      missionDescription?.isNotEmpty == true
                          ? missionDescription!
                          : 'Détaillez les lignes de main d\'œuvre et matériaux, puis définissez les jalons de paiement.',
                      style: const TextStyle(
                        fontSize: 13,
                        color: _C.muted,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (clientName != null && clientName.isNotEmpty) ...[
            const SizedBox(height: 14),
            _InfoLine(
              icon: Icons.person_outline,
              text: 'Client: $clientName',
            ),
          ],
          if (location != null && location.isNotEmpty) ...[
            const SizedBox(height: 8),
            _InfoLine(
              icon: Icons.place_outlined,
              text: location,
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (mission?.category?.trim().isNotEmpty == true)
                _MetaPill(
                  icon: Icons.category_outlined,
                  label: mission!.category!.trim(),
                ),
              if (mission?.urgency?.trim().isNotEmpty == true)
                _MetaPill(
                  icon: Icons.flash_on_outlined,
                  label: 'Urgence ${mission!.urgencyLabel.toLowerCase()}',
                ),
              if (mission?.needsReferent == true)
                const _MetaPill(
                  icon: Icons.verified_user_outlined,
                  label: 'Référent requis',
                  color: _C.warningLight,
                  textColor: _C.warning,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WorkflowCard extends StatelessWidget {
  const _WorkflowCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Rappel du parcours',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          SizedBox(height: 8),
          Text(
            '1. Chiffrez la main d\'œuvre et les matériaux.',
            style: TextStyle(fontSize: 13, color: _C.muted),
          ),
          SizedBox(height: 4),
          Text(
            '2. Répartissez les paiements en jalons cohérents avec le chantier.',
            style: TextStyle(fontSize: 13, color: _C.muted),
          ),
          SizedBox(height: 4),
          Text(
            '3. Après validation client, le ratio matériaux / main d\'œuvre devient immuable.',
            style: TextStyle(fontSize: 13, color: _C.muted, height: 1.35),
          ),
          SizedBox(height: 12),
          _RuleBanner(),
        ],
      ),
    );
  }
}

class _RuleBanner extends StatelessWidget {
  const _RuleBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _C.successLight.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Text(
        'Le total des jalons doit égaler le total du devis. Les déblocages main d\'œuvre se feront ensuite avec OTP client.',
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: _C.success,
          height: 1.35,
        ),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final IconData icon;
  final String text;

  const _InfoLine({
    required this.icon,
    required this.text,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: _C.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              color: _C.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _MetaPill extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final Color textColor;

  const _MetaPill({
    required this.icon,
    required this.label,
    this.color = Colors.white,
    this.textColor = _C.ink,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: textColor),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: textColor,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Fournisseur Section ──────────────────────────────────────────────────────
class _SupplierSection extends StatelessWidget {
  const _SupplierSection({required this.controller});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Obx(() {
        final isLoading = controller.isSuppliersLoading.value;
        final suppliers = controller.suppliers;
        final selectedSupplier = controller.selectedSupplier.value;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Quincaillerie partenaire',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Choisissez le fournisseur qui servira de base au devis matériaux. Tous les articles catalogue viendront de cette même quincaillerie.',
              style: TextStyle(
                fontSize: 12,
                color: _C.muted,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 16),
            if (isLoading && suppliers.isEmpty)
              const Center(child: CircularProgressIndicator())
            else
              DropdownButtonFormField<int>(
                initialValue: _selectedSupplierId(selectedSupplier, suppliers),
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: 'Fournisseur',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.primary, width: 2),
                  ),
                ),
                items: suppliers
                    .map(
                      (supplier) => DropdownMenuItem<int>(
                        value: supplier.id,
                        child: Text(
                          supplier.shopName,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(),
                onChanged: isLoading
                    ? null
                    : (value) {
                        controller.selectSupplier(
                          _findSupplierById(suppliers, value),
                        );
                      },
              ),
            if (selectedSupplier != null) ...[
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: _C.primaryLight,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      selectedSupplier.shopName,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: _C.primary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    _SupplierInfoRow(
                      icon: Icons.person_outline,
                      text: selectedSupplier.name,
                    ),
                    const SizedBox(height: 6),
                    _SupplierInfoRow(
                      icon: Icons.phone_outlined,
                      text: selectedSupplier.phone,
                    ),
                    const SizedBox(height: 6),
                    _SupplierInfoRow(
                      icon: Icons.inventory_2_outlined,
                      text:
                          '${selectedSupplier.activeProductsCount} article(s) actifs dans le catalogue',
                    ),
                  ],
                ),
              ),
            ],
          ],
        );
      }),
    );
  }

  int? _selectedSupplierId(
    SupplierModel? selectedSupplier,
    List<SupplierModel> suppliers,
  ) {
    if (selectedSupplier == null) return null;
    for (final supplier in suppliers) {
      if (supplier.id == selectedSupplier.id) {
        return supplier.id;
      }
    }
    return null;
  }

  SupplierModel? _findSupplierById(List<SupplierModel> suppliers, int? id) {
    if (id == null) return null;
    for (final supplier in suppliers) {
      if (supplier.id == id) {
        return supplier;
      }
    }
    return null;
  }
}

class _SupplierInfoRow extends StatelessWidget {
  const _SupplierInfoRow({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 15, color: _C.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              color: _C.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

// ─── Matériaux Section ───────────────────────────────────────────────────────
class _MaterialsSection extends StatelessWidget {
  const _MaterialsSection({required this.controller});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Obx(() {
        final selectedSupplier = controller.selectedSupplier.value;
        final products = controller.supplierProducts;
        final materialLines = controller.materialLines;
        final isLoading = controller.isCatalogLoading.value;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Matériaux du devis',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: Wrap(
                    spacing: 12,
                    runSpacing: 8,
                    alignment: WrapAlignment.start,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      TextButton.icon(
                        onPressed: () {
                          final ArtisanCartController artisanCart = Get.isRegistered<ArtisanCartController>()
                              ? Get.find<ArtisanCartController>()
                              : Get.put(ArtisanCartController());

                          if (artisanCart.cart.isEmpty) {
                            Get.snackbar(
                              'Panier vide',
                              'Votre panier ne contient aucun article à importer.',
                              backgroundColor: _C.danger,
                              colorText: Colors.white,
                            );
                            return;
                          }

                          controller.importArtisanCart(artisanCart.getCartLinesForDevis());
                          Get.snackbar(
                            'Panier importé',
                            '${artisanCart.cartCount} articles ont été importés dans votre devis.',
                            backgroundColor: _C.success,
                            colorText: Colors.white,
                          );
                        },
                        icon: const Icon(Icons.download_rounded, size: 16),
                        label: const Text('Importer panier', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        style: TextButton.styleFrom(
                          foregroundColor: _C.success,
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                      ),
                      TextButton.icon(
                        onPressed: selectedSupplier == null
                            ? null
                            : () => _showCustomMaterialDialog(context),
                        icon: const Icon(Icons.add_circle_outline, size: 16),
                        label: const Text('Hors catalogue', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        style: TextButton.styleFrom(
                          foregroundColor: _C.primary,
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                      ),
                    ],
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
                color: _C.muted,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 16),
            if (selectedSupplier == null)
              const _EmptyState(
                icon: Icons.storefront_outlined,
                message: 'Aucun fournisseur sélectionné',
                hint:
                    'Le devis matériaux doit partir d\'une quincaillerie partenaire avant ajout des articles.',
              )
            else if (isLoading)
              const Center(child: CircularProgressIndicator())
            else ...[
              if (products.isEmpty)
                const _EmptyState(
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
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 10),
              if (materialLines.isEmpty)
                const _EmptyState(
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
                          child: _LigneCard(
                            ligne: ligne,
                            index: 0,
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
                  color: _C.ink,
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
                        backgroundColor: _C.primary,
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
        color: _C.primaryLight.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: _C.surface,
              borderRadius: BorderRadius.circular(12),
            ),
            alignment: Alignment.center,
            child: const Icon(
              Icons.inventory_2_outlined,
              color: _C.primary,
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
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _formatFCFA(product.unitPrice),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: _C.success,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Stock: ${product.stockQuantity}${product.sku?.isNotEmpty == true ? ' • ${product.sku}' : ''}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: _C.muted,
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
                    color: _C.successLight,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$quantityInQuote dans le devis',
                    style: const TextStyle(
                      fontSize: 11,
                      color: _C.success,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ElevatedButton(
                onPressed: onAdd,
                style: ElevatedButton.styleFrom(
                  backgroundColor: _C.primary,
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

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}

// ─── Main d'oeuvre Section ───────────────────────────────────────────────────
class _LignesSection extends StatelessWidget {
  final DevisController controller;
  const _LignesSection({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Main d\'œuvre',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
            TextButton.icon(
              onPressed: () => _showAddLigneDialog(context, controller),
              icon: const Icon(Icons.add_circle_outline, size: 18),
              label: const Text('Ajouter'),
              style: TextButton.styleFrom(foregroundColor: _C.primary),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() {
          final laborLines = controller.laborLines;

          if (laborLines.isEmpty) {
            return _EmptyState(
              icon: Icons.build_circle_outlined,
              message: 'Aucune ligne de main d\'œuvre',
              hint:
                  'Ajoutez votre chiffrage de main d\'œuvre pour compléter le devis.',
            );
          }

          return Column(
            children: laborLines
                .map((entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _LigneCard(
                        ligne: entry,
                        index: 0,
                        onDelete: () => controller.removeLigneItem(entry),
                      ),
                    ))
                .toList(),
          );
        }),
      ],
    );
  }

  void _showAddLigneDialog(BuildContext context, DevisController controller) {
    final descController = TextEditingController();
    final montantController = TextEditingController();

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
                'Ajouter une ligne de main d\'œuvre',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Description',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: descController,
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: 'Ex: Pose, raccordement et finitions',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.primary, width: 2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Montant (FCFA)',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: montantController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  hintText: '0',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.primary, width: 2),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.back(),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        final desc = descController.text.trim();
                        final montant =
                            int.tryParse(montantController.text.trim());

                        if (desc.isEmpty) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez renseigner une description',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        if (montant == null || montant <= 0) {
                          Get.snackbar(
                            'Erreur',
                            'Montant invalide',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        controller.addLigne(
                          type: 'mo',
                          description: desc,
                          montant: montant,
                        );

                        Get.back();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _C.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
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

// ─── Jalons Section ───────────────────────────────────────────────────────────
class _JalonsSection extends StatelessWidget {
  final DevisController controller;
  const _JalonsSection({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Jalons de paiement',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
            TextButton.icon(
              onPressed: () => _showAddJalonDialog(context, controller),
              icon: const Icon(Icons.add_circle_outline, size: 18),
              label: const Text('Ajouter'),
              style: TextButton.styleFrom(foregroundColor: _C.primary),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() {
          if (controller.jalons.isEmpty) {
            return _EmptyState(
              icon: Icons.flag_outlined,
              message: 'Aucun jalon défini',
              hint: 'Définissez les étapes de validation du projet',
            );
          }

          return Column(
            children: controller.jalons
                .asMap()
                .entries
                .map((entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _JalonCard(
                        jalon: entry.value,
                        index: entry.key,
                        onDelete: () => controller.removeJalon(entry.key),
                      ),
                    ))
                .toList(),
          );
        }),
      ],
    );
  }

  void _showAddJalonDialog(BuildContext context, DevisController controller) {
    final descController = TextEditingController();
    final montantController = TextEditingController();
    final dateController = TextEditingController();
    DateTime? selectedDate;

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
                'Ajouter un jalon',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 20),

              // Description
              const Text(
                'Description',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: descController,
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: 'Ex: Livraison des fondations',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.primary, width: 2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Montant
              const Text(
                'Montant (FCFA)',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: montantController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  hintText: '0',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.subtle),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: _C.primary, width: 2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Date cible
              const Text(
                'Date cible',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              StatefulBuilder(
                builder: (context, setState) => TextField(
                  controller: dateController,
                  readOnly: true,
                  onTap: () async {
                    final date = await showDatePicker(
                      context: context,
                      initialDate: DateTime.now().add(const Duration(days: 7)),
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (date != null) {
                      selectedDate = date;
                      dateController.text =
                          DateFormat('dd/MM/yyyy').format(date);
                      setState(() {});
                    }
                  },
                  decoration: InputDecoration(
                    hintText: 'Sélectionner une date',
                    suffixIcon: const Icon(Icons.calendar_today, size: 20),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: _C.subtle),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: _C.subtle),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: _C.primary, width: 2),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // Actions
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.back(),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        final desc = descController.text.trim();
                        final montantStr = montantController.text.trim();

                        if (desc.isEmpty ||
                            montantStr.isEmpty ||
                            selectedDate == null) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez remplir tous les champs',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        final montant = int.tryParse(montantStr);
                        if (montant == null || montant <= 0) {
                          Get.snackbar(
                            'Erreur',
                            'Montant invalide',
                            snackPosition: SnackPosition.TOP,
                          );
                          return;
                        }

                        controller.addJalon(
                          description: desc,
                          montant: montant,
                          dateCible: selectedDate!.toIso8601String(),
                        );
                        Get.back();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _C.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
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

// ─── Recap Section ────────────────────────────────────────────────────────────
class _RecapSection extends StatelessWidget {
  final DevisController controller;
  const _RecapSection({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Récapitulatif',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 16),
          Obx(() => Column(
                children: [
                  if (controller.selectedSupplier.value != null) ...[
                    _RecapRow(
                      label: 'Fournisseur',
                      value: controller.selectedSupplier.value!.shopName,
                      valueColor: _C.primary,
                    ),
                    const SizedBox(height: 8),
                  ],
                  _RecapRow(
                    label: 'Main d\'œuvre',
                    value: _formatFCFA(controller.totalMo),
                    valueColor: _C.success,
                  ),
                  const SizedBox(height: 8),
                  _RecapRow(
                    label: 'Matériaux',
                    value: _formatFCFA(controller.totalMat),
                    valueColor: _C.warning,
                  ),
                  const Divider(height: 24),
                  _RecapRow(
                    label: 'TOTAL',
                    value: _formatFCFA(controller.totalGeneral),
                    valueColor: _C.primary,
                    isBold: true,
                  ),
                  const SizedBox(height: 8),
                  _RecapRow(
                    label: 'Ratio matériaux',
                    value:
                        '${(controller.ratioMateriaux * 100).toStringAsFixed(1)}%',
                    valueColor: _C.muted,
                  ),
                ],
              )),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}

// ─── Submit Button ────────────────────────────────────────────────────────────
class _SubmitButton extends StatelessWidget {
  final DevisController controller;
  final int? missionId;

  const _SubmitButton({
    required this.controller,
    required this.missionId,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Obx(() => ElevatedButton(
            onPressed: controller.isSubmitting.value || missionId == null
                ? null
                : () async {
                    final success = await controller.createDevis(
                      missionId: missionId!,
                    );
                    if (success) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (Navigator.of(context).canPop()) {
                          Navigator.of(context).pop(true);
                          return;
                        }
                        Get.back(result: true);
                      });
                    }
                  },
            style: ElevatedButton.styleFrom(
              backgroundColor: _C.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: controller.isSubmitting.value
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text(
                    'Envoyer le devis',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
          )),
    );
  }
}

// ─── Widgets utilitaires ──────────────────────────────────────────────────────
class _LigneCard extends StatelessWidget {
  final DevisLigne ligne;
  final int index;
  final VoidCallback onDelete;

  const _LigneCard({
    required this.ligne,
    required this.index,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final isMo = ligne.type == 'mo';
    final detail = !isMo
        ? '${ligne.resolvedQuantity} x ${_formatFCFA(ligne.resolvedUnitPrice)}'
        : null;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isMo ? _C.successLight : _C.warningLight,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              isMo ? Icons.build : Icons.category,
              size: 20,
              color: isMo ? _C.success : _C.warning,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  ligne.description,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  _formatFCFA(ligne.montant),
                  style: TextStyle(
                    fontSize: 13,
                    color: isMo ? _C.success : _C.warning,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (detail != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    detail,
                    style: const TextStyle(
                      fontSize: 12,
                      color: _C.muted,
                    ),
                  ),
                ],
              ],
            ),
          ),
          IconButton(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline, color: _C.danger, size: 20),
          ),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}

class _JalonCard extends StatelessWidget {
  final DevisJalon jalon;
  final int index;
  final VoidCallback onDelete;

  const _JalonCard({
    required this.jalon,
    required this.index,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              '${jalon.ordre}',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: _C.primary,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  jalon.description,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text(
                      _formatFCFA(jalon.montant),
                      style: const TextStyle(
                        fontSize: 13,
                        color: _C.primary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const Text(' • ', style: TextStyle(color: _C.muted)),
                    Text(
                      _formatDate(jalon.dateCible),
                      style: const TextStyle(
                        fontSize: 13,
                        color: _C.muted,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline, color: _C.danger, size: 20),
          ),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }

  String _formatDate(String date) {
    try {
      final dt = DateTime.parse(date);
      return DateFormat('dd/MM/yyyy').format(dt);
    } catch (_) {
      return date;
    }
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String message;
  final String hint;

  const _EmptyState({
    required this.icon,
    required this.message,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle, style: BorderStyle.solid),
      ),
      child: Column(
        children: [
          Icon(icon, size: 48, color: _C.muted),
          const SizedBox(height: 12),
          Text(
            message,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            hint,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: _C.muted,
            ),
          ),
        ],
      ),
    );
  }
}

class _RecapRow extends StatelessWidget {
  final String label;
  final String value;
  final Color valueColor;
  final bool isBold;

  const _RecapRow({
    required this.label,
    required this.value,
    required this.valueColor,
    this.isBold = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: isBold ? 16 : 14,
            fontWeight: isBold ? FontWeight.w700 : FontWeight.w500,
            color: _C.ink,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isBold ? 16 : 14,
            fontWeight: FontWeight.w700,
            color: valueColor,
          ),
        ),
      ],
    );
  }
}
