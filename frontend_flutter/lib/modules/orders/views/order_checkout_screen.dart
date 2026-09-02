import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../controllers/order_controller.dart';

class OrderCheckoutScreen extends StatefulWidget {
  const OrderCheckoutScreen({super.key});

  @override
  State<OrderCheckoutScreen> createState() => _OrderCheckoutScreenState();
}

class _OrderCheckoutScreenState extends State<OrderCheckoutScreen> {
  final OrderController controller = Get.find<OrderController>();
  final TextEditingController _promoController = TextEditingController();

  late int supplierId;
  late List<Map<String, dynamic>> items;

  String deliveryMode = 'delivery';
  String vehicleClass = 'moto';
  double surgeMultiplier = 1.0;
  
  double _promoDiscount = 0.0;
  String? _appliedPromoCode;
  bool _isCheckingPromo = false;
  
  /// Flag réactif pour éviter toute double soumission après succès.
  final _orderSubmitted = false.obs;

  @override
  void initState() {
    super.initState();
    final args = Get.arguments as Map<String, dynamic>? ?? {};
    supplierId = args['supplier_id'] ?? 0;
    items = List<Map<String, dynamic>>.from(args['items'] ?? []);
  }

  @override
  void dispose() {
    _promoController.dispose();
    super.dispose();
  }

  int get effectiveSupplierId {
    if (supplierId > 1) return supplierId;
    if (controller.selectedSupplier.value != null && controller.selectedSupplier.value!.id > 0) {
      return controller.selectedSupplier.value!.id;
    }
    if (controller.supplierProducts.isNotEmpty) {
      final firstWithSupplier = controller.supplierProducts.where((p) => p.supplierId > 0).firstOrNull;
      if (firstWithSupplier != null) return firstWithSupplier.supplierId;
    }
    return supplierId > 0 ? supplierId : 1;
  }

  int get deliveryCost {
    if (deliveryMode != 'delivery') return 0;
    int base = 1250;
    int addon = 0;
    if (vehicleClass == 'voiture') {
      addon = 1500;
    } else if (vehicleClass == 'cargo') {
      addon = 3000;
    }
    return ((base + addon) * surgeMultiplier).round();
  }

  int get totalOrderAmount {
    int total = controller.subtotal + controller.platformFee + deliveryCost - _promoDiscount.round();
    return total < 0 ? 0 : total;
  }

  Future<void> _applyPromo() async {
    final code = _promoController.text.trim().toUpperCase();
    if (code.isEmpty) {
      Get.snackbar(
        'Code promo',
        'Veuillez saisir un code promo (ex: PROS225)',
        backgroundColor: const Color(0xFFC55E50),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    setState(() => _isCheckingPromo = true);
    try {
      final client = ApiClient();
      final res = await client.post(
        '/promo-codes/verify',
        data: {
          'code': code,
          'amount': controller.subtotal,
        },
      );

      if (res.data != null && res.data['success'] == true) {
        final data = res.data['data'] as Map<String, dynamic>;
        final discountAmount = (data['discount_amount'] as num?)?.toDouble() ?? 0.0;
        final discountType = data['discount_type'] as String? ?? 'percent';
        final discountVal = data['discount_value'];

        setState(() {
          _promoDiscount = discountAmount;
          _appliedPromoCode = code;
        });

        final detail = discountType == 'percent' ? '-$discountVal%' : '-$discountVal FCFA';
        Get.snackbar(
          'Code promo validé !',
          '${res.data['message']} ($detail)',
          backgroundColor: const Color(0xFF24734F),
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
      } else {
        setState(() {
          _promoDiscount = 0;
          _appliedPromoCode = null;
        });
        Get.snackbar(
          'Code promo invalide',
          res.data?['message'] ?? 'Ce code promo n\'est pas applicable.',
          backgroundColor: const Color(0xFFC55E50),
          colorText: Colors.white,
          snackPosition: SnackPosition.TOP,
        );
      }
    } on DioException catch (e) {
      setState(() {
        _promoDiscount = 0;
        _appliedPromoCode = null;
      });
      String msg = 'Code promo invalide ou expiré.';
      if (e.response?.data is Map && e.response!.data['message'] != null) {
        msg = e.response!.data['message'].toString();
      }
      Get.snackbar(
        'Code promo',
        msg,
        backgroundColor: const Color(0xFFC55E50),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de vérifier le code promo',
        backgroundColor: const Color(0xFFC55E50),
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      if (mounted) setState(() => _isCheckingPromo = false);
    }
  }

  void _removePromo() {
    setState(() {
      _promoDiscount = 0;
      _appliedPromoCode = null;
      _promoController.clear();
    });
  }

  void _submit() async {
    // Protection anti double-submit : flag local + contrôle du controller
    if (_orderSubmitted.value || controller.isSubmitting.value) return;
    _orderSubmitted.value = true;

    final success = await controller.createOrder(
      supplierId: effectiveSupplierId,
      deliveryMode: deliveryMode,
      items: items.isNotEmpty ? items : controller.getCartItemsPayload(),
      vehicleClass: deliveryMode == 'delivery' ? vehicleClass : null,
      surgeMultiplier: deliveryMode == 'delivery' ? surgeMultiplier : null,
      promoCode: _appliedPromoCode,
    );

    if (success) {
      unawaited(Get.dialog(
        AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: Color(0xFF10B981), size: 28),
              SizedBox(width: 10),
              Text('Commande confirmée', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
            ],
          ),
          content: const Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Votre commande a été enregistrée avec succès !',
                style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: Color(0xFF1F2937)),
              ),
              SizedBox(height: 8),
              Text(
                'Le paiement est sécurisé en compte séquestre et le fournisseur a été notifié. Vous recevrez des SMS de suivi.',
                style: TextStyle(fontSize: 13, color: Color(0xFF4B5563), height: 1.4),
              ),
            ],
          ),
          actions: [
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2F6FED),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              ),
              onPressed: () {
                Get.back(); // Fermer la modale
                Get.back(); // Retour au catalogue
              },
              child: const Text('OK, Parfait', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
        barrierDismissible: false,
      ));
    } else {
      // En cas d'échec, permettre une nouvelle tentative
      _orderSubmitted.value = false;
    }
  }

  Widget _buildStepHeader(String stepNum, String title) {
    return Row(
      children: [
        Container(
          height: 22,
          width: 22,
          decoration: const BoxDecoration(
            color: Color(0xFF24734F),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.check, size: 13, color: Colors.white),
        ),
        const SizedBox(width: 8),
        Text(
          '$stepNum. $title',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppColors.textPrimary, letterSpacing: 0.5),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final shopName = controller.selectedSupplier.value?.shopName ?? 'Quincaillerie';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Passer votre commande',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
        ),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0.5,
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ÉTAPE 1 : ADRESSE CLIENT
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildStepHeader('1', 'ADRESSE DU DESTINATAIRE'),
                  const Divider(height: 24, color: Color(0xFFEDF2F7)),
                  const Text(
                    'Inza Bamba',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppColors.textPrimary),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    '28 BP 124 ABIDJAN 28, Cocody Mermoz\nAbidjan-Lagunes • Côte d\'Ivoire\nTél: +225 0141498208',
                    style: TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.4),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ÉTAPE 2 : DÉTAILS DE LIVRAISON
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildStepHeader('2', 'DÉTAILS DE RÉCUPÉRATION'),
                  const Divider(height: 24, color: Color(0xFFEDF2F7)),
                  
                  // Mode de récupération
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'pickup',
                        label: Text('Retrait magasin'),
                        icon: Icon(Icons.storefront, size: 18),
                      ),
                      ButtonSegment(
                        value: 'delivery',
                        label: Text('Livraison'),
                        icon: Icon(Icons.delivery_dining, size: 18),
                      ),
                    ],
                    selected: {deliveryMode},
                    onSelectionChanged: (set) {
                      setState(() => deliveryMode = set.first);
                    },
                    style: SegmentedButton.styleFrom(
                      selectedBackgroundColor: AppColors.primary.withValues(alpha: 0.12),
                      selectedForegroundColor: AppColors.primary,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 16),

                  if (deliveryMode == 'delivery') ...[
                    // Type de véhicule
                    const Text(
                      'Type de véhicule requis',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: vehicleClass,
                      decoration: InputDecoration(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: AppColors.primary),
                        ),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'moto', child: Text('Moto (Standard)')),
                        DropdownMenuItem(value: 'voiture', child: Text('Voiture (+1 500 FCFA)')),
                        DropdownMenuItem(value: 'cargo', child: Text('Cargo (+3 000 FCFA)')),
                      ],
                      onChanged: (val) => setState(() => vehicleClass = val!),
                    ),
                    const SizedBox(height: 16),

                    // Surge Pricing
                    const Text(
                      'Majoration de course (Surge)',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                    ),
                    Slider(
                      value: surgeMultiplier,
                      min: 1.0,
                      max: 3.0,
                      divisions: 20,
                      activeColor: AppColors.primary,
                      inactiveColor: const Color(0xFFE2E8F0),
                      label: '${surgeMultiplier.toStringAsFixed(1)}x',
                      onChanged: (val) => setState(() => surgeMultiplier = val),
                    ),
                    Text(
                      'Multiplicateur actuel : ${surgeMultiplier.toStringAsFixed(1)}x',
                      style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                    ),
                  ],

                  const Divider(height: 32, color: Color(0xFFEDF2F7)),
                  
                  // Liste d'expédition
                  Text(
                    'Expédition 1/1 — Vendu par $shopName',
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                  ),
                  const SizedBox(height: 12),
                  ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: controller.cart.length,
                    itemBuilder: (context, index) {
                      final productId = controller.cart.keys.elementAt(index);
                      final qty = controller.cart.values.elementAt(index);
                      final product = controller.supplierProducts.firstWhereOrNull((p) => p.id == productId);

                      if (product == null) return const SizedBox.shrink();
                      final itemPrice = (product.unitPrice * qty);

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8.0),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                '${product.name} (x$qty)',
                                style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                              ),
                            ),
                            Text(
                              '${itemPrice.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // ÉTAPE 3 : MODE DE PAIEMENT
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildStepHeader('3', 'MODE DE PAIEMENT'),
                  const Divider(height: 24, color: Color(0xFFEDF2F7)),
                  
                  // Option Wave CI
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1EA6D6).withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF1EA6D6).withValues(alpha: 0.3)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1EA6D6),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(Icons.account_balance_wallet, color: Colors.white, size: 18),
                        ),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Wave CI / Orange Money (Séquestre)',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppColors.textPrimary),
                              ),
                              SizedBox(height: 2),
                              Text(
                                'Fonds bloqués et libérés à la livraison',
                                style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.check_circle, color: Color(0xFF1EA6D6), size: 20),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // CODE PROMO
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _appliedPromoCode != null ? const Color(0xFF24734F) : const Color(0xFFE2E8F0),
                  width: _appliedPromoCode != null ? 1.5 : 1.0,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          Icon(
                            _appliedPromoCode != null ? Icons.check_circle_rounded : Icons.local_offer_outlined,
                            size: 18,
                            color: _appliedPromoCode != null ? const Color(0xFF24734F) : AppColors.primary,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _appliedPromoCode != null ? 'Code promo appliqué' : 'Avez-vous un code promo ?',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              color: _appliedPromoCode != null ? const Color(0xFF24734F) : AppColors.textPrimary,
                            ),
                          ),
                        ],
                      ),
                      if (_appliedPromoCode != null)
                        InkWell(
                          onTap: _removePromo,
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFC55E50).withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Row(
                              children: [
                                Icon(Icons.close, size: 14, color: Color(0xFFC55E50)),
                                SizedBox(width: 4),
                                Text(
                                  'Retirer',
                                  style: TextStyle(color: Color(0xFFC55E50), fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (_appliedPromoCode != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: const Color(0xFF24734F).withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            _appliedPromoCode!,
                            style: const TextStyle(
                              fontFamily: 'monospace',
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                              color: Color(0xFF24734F),
                              letterSpacing: 1.2,
                            ),
                          ),
                          Text(
                            '-${_promoDiscount.round().toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF24734F)),
                          ),
                        ],
                      ),
                    )
                  else
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _promoController,
                            textCapitalization: TextCapitalization.characters,
                            style: const TextStyle(color: Color(0xFF1E293B), fontWeight: FontWeight.bold, fontSize: 14),
                            decoration: InputDecoration(
                              hintText: 'Ex: PROS225',
                              hintStyle: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                                borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                                borderSide: const BorderSide(color: Color(0xFFCBD5E1)),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                                borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        ElevatedButton(
                          onPressed: _isCheckingPromo ? null : _applyPromo,
                          style: ElevatedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            elevation: 0,
                          ),
                          child: _isCheckingPromo
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('APPLIQUER', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                        ),
                      ],
                    ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // RÉCAPITULATIF FINANCIER
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Résumé de la commande',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                  ),
                  const Divider(height: 24, color: Color(0xFFEDF2F7)),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Total Articles', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                      Text(
                        '${controller.subtotal.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.textPrimary, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Frais de Livraison', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                      Text(
                        '${deliveryCost.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.textPrimary, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),

                  if (_promoDiscount > 0) ...[
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Remise Code Promo', style: TextStyle(color: Color(0xFF24734F), fontSize: 13, fontWeight: FontWeight.bold)),
                        Text(
                          '-${_promoDiscount.round().toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                          style: const TextStyle(color: Color(0xFF24734F), fontSize: 13, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ],
                  const Divider(height: 24, color: Color(0xFFEDF2F7)),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total Général',
                        style: TextStyle(color: AppColors.textPrimary, fontSize: 15, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        '${totalOrderAmount.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA',
                        style: const TextStyle(color: AppColors.primary, fontSize: 17, fontWeight: FontWeight.w900),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // CONFIRMER LA COMMANDE BUTTON
            Obx(() => ElevatedButton(
              onPressed: (controller.isSubmitting.value || _orderSubmitted.value) ? null : _submit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: const Color(0xFFE28A32), // Jumia styled orange tone
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
              child: controller.isSubmitting.value
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : const Text(
                      'Confirmer la commande',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    ),
            )),
          ],
        ),
      ),
    );
  }
}
