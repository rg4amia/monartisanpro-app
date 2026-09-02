import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/jcode_item_model.dart';
import '../../../data/models/jcode_model.dart';
import '../controllers/jcode_controller.dart';

class JcodeServeScreen extends StatefulWidget {
  const JcodeServeScreen({super.key});

  @override
  State<JcodeServeScreen> createState() => _JcodeServeScreenState();
}

class _JcodeServeScreenState extends State<JcodeServeScreen> {
  late final JcodeController controller;
  final Map<int, int> _quantitiesToServe = {};
  bool _isLocating = false;

  @override
  void initState() {
    super.initState();
    controller = Get.find<JcodeController>();
    _initQuantities();
  }

  void _initQuantities() {
    final jcode = controller.scannedJcode.value;
    if (jcode != null) {
      for (final item in jcode.items) {
        if (item.id != null) {
          _quantitiesToServe[item.id!] = item.remainingQuantity;
        }
      }
    }
  }

  int get _totalAmountToServe {
    final jcode = controller.scannedJcode.value;
    if (jcode == null) return 0;

    int total = 0;
    for (final item in jcode.items) {
      if (item.id != null) {
        final qty = _quantitiesToServe[item.id!] ?? 0;
        total += qty * item.unitPrice;
      }
    }
    return total;
  }

  int get _totalItemsCountToServe {
    return _quantitiesToServe.values.fold(0, (sum, val) => sum + val);
  }

  Future<void> _confirmDelivery() async {
    final jcode = controller.scannedJcode.value;
    if (jcode == null) return;

    if (_totalItemsCountToServe == 0) {
      Get.snackbar(
        'Sélection requise',
        'Veuillez sélectionner au moins un article à livrer.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.warning,
        colorText: Colors.white,
      );
      return;
    }

    setState(() => _isLocating = true);

    try {
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final requestResult = await Geolocator.requestPermission();
        if (requestResult == LocationPermission.denied ||
            requestResult == LocationPermission.deniedForever) {
          throw Exception('Autorisation GPS refusée.');
        }
      }

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      final servedItems = _quantitiesToServe.entries
          .where((e) => e.value > 0)
          .map(
            (e) => {
              'jcode_item_id': e.key,
              'quantity_served': e.value,
            },
          )
          .toList();

      await controller.submitPartialServe(
        identifier: jcode.code,
        lat: pos.latitude,
        lng: pos.longitude,
        servedItems: servedItems,
      );
    } catch (e) {
      Get.snackbar(
        'Erreur GPS',
        e.toString().replaceAll('Exception:', '').trim(),
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    } finally {
      setState(() => _isLocating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Validation de Livraison'),
        centerTitle: true,
      ),
      body: Obx(() {
        final jcode = controller.scannedJcode.value;
        if (jcode == null) {
          return const Center(child: Text('J-Code non trouvé.'));
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // JCode Summary card
            _buildJcodeHeader(jcode),

            // Items list header
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'ARTICLES À SERVIR',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textSecondary,
                      letterSpacing: 1.1,
                    ),
                  ),
                  Text(
                    '${jcode.items.length} lignes',
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),

            // Scrollable list of items
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: jcode.items.length,
                itemBuilder: (context, index) {
                  final item = jcode.items[index];
                  return _buildItemCard(item);
                },
              ),
            ),

            // Action Summary panel
            _buildActionPanel(jcode),
          ],
        );
      }),
    );
  }

  Widget _buildJcodeHeader(JcodeModel jcode) {
    final remainingAmount = jcode.montantRestant;
    final progress = jcode.montant > 0
        ? (jcode.montantConsomme / jcode.montant).clamp(0.0, 1.0)
        : 0.0;

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF2C3E50), Color(0xFF34495E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.15),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'J-Code: ${jcode.code}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Mission #${jcode.missionId}',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.7),
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: jcode.isPartiallyUsed
                      ? Colors.orange.withValues(alpha: 0.2)
                      : AppColors.success.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: jcode.isPartiallyUsed
                        ? Colors.orange
                        : AppColors.success,
                    width: 1,
                  ),
                ),
                child: Text(
                  jcode.isPartiallyUsed ? 'Partiel' : 'Actif',
                  style: TextStyle(
                    color: jcode.isPartiallyUsed
                        ? Colors.orange
                        : AppColors.success,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const Divider(color: Colors.white24, height: 24),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _buildHeaderStat(
                'Montant Initial',
                Formatters.fcfa(jcode.montant),
              ),
              _buildHeaderStat(
                'Consommé',
                Formatters.fcfa(jcode.montantConsomme),
                color: Colors.orangeAccent,
              ),
              _buildHeaderStat(
                'Solde Restant',
                Formatters.fcfa(remainingAmount),
                color: Colors.greenAccent,
                isBold: true,
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: progress,
              backgroundColor: Colors.white10,
              valueColor:
                  const AlwaysStoppedAnimation<Color>(Colors.greenAccent),
              minHeight: 6,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeaderStat(
    String label,
    String value, {
    Color? color,
    bool isBold = false,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.6),
            fontSize: 11,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: TextStyle(
            color: color ?? Colors.white,
            fontSize: 14,
            fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _buildItemCard(JcodeItemModel item) {
    final hasRemaining = item.remainingQuantity > 0;
    final selectedQty = _quantitiesToServe[item.id!] ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: selectedQty > 0
              ? AppColors.primary.withValues(alpha: 0.3)
              : AppColors.border,
          width: selectedQty > 0 ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    if (item.sku != null && item.sku!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Réf: ${item.sku}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Text(
                Formatters.fcfa(item.unitPrice),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Requis : ${item.quantity} unités',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Servi : ${item.quantityServed} unités',
                    style: TextStyle(
                      fontSize: 12,
                      color: item.isServed
                          ? AppColors.success
                          : AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              if (!hasRemaining)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text(
                    'Livré',
                    style: TextStyle(
                      color: AppColors.success,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                )
              else
                Row(
                  children: [
                    _buildQtyBtn(
                      icon: Icons.remove,
                      onTap: selectedQty > 0
                          ? () => setState(() {
                                _quantitiesToServe[item.id!] = selectedQty - 1;
                              })
                          : null,
                    ),
                    Container(
                      width: 44,
                      alignment: Alignment.center,
                      child: Text(
                        '$selectedQty',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    _buildQtyBtn(
                      icon: Icons.add,
                      onTap: selectedQty < item.remainingQuantity
                          ? () => setState(() {
                                _quantitiesToServe[item.id!] = selectedQty + 1;
                              })
                          : null,
                    ),
                  ],
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQtyBtn({required IconData icon, VoidCallback? onTap}) {
    final isDisabled = onTap == null;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          color: isDisabled
              ? Colors.grey[100]
              : AppColors.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(
          icon,
          size: 18,
          color: isDisabled ? Colors.grey[400] : AppColors.primary,
        ),
      ),
    );
  }

  Widget _buildActionPanel(JcodeModel jcode) {
    final total = _totalAmountToServe;
    final itemsCount = _totalItemsCountToServe;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'TOTAL SERVI CE SCAN',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textSecondary,
                        letterSpacing: 0.8,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '$itemsCount article(s) sélectionné(s)',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ),
                Text(
                  Formatters.fcfa(total),
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: (_isLocating || controller.isScanning.value)
                  ? null
                  : _confirmDelivery,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
              child: (_isLocating || controller.isScanning.value)
                  ? Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          _isLocating
                              ? 'Obtention du GPS...'
                              : 'Validation en cours...',
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    )
                  : const Text(
                      'Valider la livraison',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
