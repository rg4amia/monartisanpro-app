import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../data/models/devis_model.dart';
import '../controllers/devis_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
  static const success = Color(0xFF10B981);
  static const successLight = Color(0xFFD1FAE5);
  static const warning = Color(0xFFF59E0B);
  static const warningLight = Color(0xFFFEF3C7);
  static const danger = Color(0xFFEF4444);
}

/// Vue de création de devis pour l'artisan
///
/// WORKFLOW:
/// 1. Client crée mission + sélectionne artisan → Notification artisan
/// 2. **Artisan crée devis (cette vue)** → Notification client
/// 3. Client valide/refuse devis
class DevisCreationScreen extends StatelessWidget {
  const DevisCreationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(DevisController());
    final missionId = Get.arguments as int?;

    if (missionId != null) {
      controller.missionId = missionId;
    }

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
                    _InfoCard(missionId: missionId),
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
  const _InfoCard({this.missionId});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.primaryLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.primary.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline, color: _C.primary, size: 24),
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
                const Text(
                  'Détaillez les lignes de main d\'œuvre et matériaux, puis définissez les jalons de paiement.',
                  style: TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Lignes Section ───────────────────────────────────────────────────────────
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
              'Lignes du devis',
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
          if (controller.lignes.isEmpty) {
            return _EmptyState(
              icon: Icons.receipt_long_outlined,
              message: 'Aucune ligne ajoutée',
              hint: 'Ajoutez des lignes de main d\'œuvre et matériaux',
            );
          }

          return Column(
            children: controller.lignes
                .asMap()
                .entries
                .map((entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _LigneCard(
                        ligne: entry.value,
                        index: entry.key,
                        onDelete: () => controller.removeLigne(entry.key),
                      ),
                    ))
                .toList(),
          );
        }),
      ],
    );
  }

  void _showAddLigneDialog(BuildContext context, DevisController controller) {
    String selectedType = 'mo';
    final descController = TextEditingController();
    final montantController = TextEditingController();
    final quantityController = TextEditingController(text: '1');
    final unitPriceController = TextEditingController();
    final skuController = TextEditingController();

    Get.dialog(
      StatefulBuilder(
        builder: (context, setState) => Dialog(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Ajouter une ligne',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  'Type',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _RadioButton(
                        label: 'Main d\'œuvre',
                        value: 'mo',
                        groupValue: selectedType,
                        onChanged: (v) => setState(() => selectedType = v!),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _RadioButton(
                        label: 'Matériaux',
                        value: 'mat',
                        groupValue: selectedType,
                        onChanged: (v) => setState(() => selectedType = v!),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
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
                    hintText: selectedType == 'mo'
                        ? 'Ex: Pose de carrelage 20m²'
                        : 'Ex: Carrelage 60x60 beige',
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
                if (selectedType == 'mo') ...[
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
                        borderSide:
                            const BorderSide(color: _C.primary, width: 2),
                      ),
                    ),
                  ),
                ] else ...[
                  const Text(
                    'Quantité',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: _C.ink,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: quantityController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      hintText: '1',
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
                        borderSide:
                            const BorderSide(color: _C.primary, width: 2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Prix unitaire (FCFA)',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: _C.ink,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: unitPriceController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    onChanged: (_) => setState(() {}),
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
                        borderSide:
                            const BorderSide(color: _C.primary, width: 2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'SKU / Référence (optionnel)',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: _C.ink,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: skuController,
                    decoration: InputDecoration(
                      hintText: 'Ex: MAT-001',
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
                        borderSide:
                            const BorderSide(color: _C.primary, width: 2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _C.warningLight,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      'Total matériaux: ${_formatFCFA((int.tryParse(quantityController.text.trim()) ?? 0) * (int.tryParse(unitPriceController.text.trim()) ?? 0))}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: _C.warning,
                      ),
                    ),
                  ),
                ],
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
                          if (desc.isEmpty) {
                            Get.snackbar(
                              'Erreur',
                              'Veuillez renseigner une description',
                              snackPosition: SnackPosition.TOP,
                            );
                            return;
                          }

                          if (selectedType == 'mo') {
                            final montant =
                                int.tryParse(montantController.text.trim());
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
                          } else {
                            final quantity =
                                int.tryParse(quantityController.text.trim());
                            final unitPrice =
                                int.tryParse(unitPriceController.text.trim());

                            if (quantity == null ||
                                quantity <= 0 ||
                                unitPrice == null ||
                                unitPrice <= 0) {
                              Get.snackbar(
                                'Erreur',
                                'Quantité ou prix unitaire invalide',
                                snackPosition: SnackPosition.TOP,
                              );
                              return;
                            }

                            controller.addLigne(
                              type: 'mat',
                              description: desc,
                              montant: quantity * unitPrice,
                              source: 'custom',
                              quantity: quantity,
                              unitPrice: unitPrice,
                              sku: skuController.text.trim().isEmpty
                                  ? null
                                  : skuController.text.trim(),
                            );
                          }

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
                      Get.back(result: true);
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

class _RadioButton extends StatelessWidget {
  final String label;
  final String value;
  final String groupValue;
  final ValueChanged<String?> onChanged;

  const _RadioButton({
    required this.label,
    required this.value,
    required this.groupValue,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final isSelected = value == groupValue;
    return GestureDetector(
      onTap: () => onChanged(value),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? _C.primaryLight : _C.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? _C.primary : _C.subtle,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 20,
              height: 20,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: isSelected ? _C.primary : _C.muted,
                  width: 2,
                ),
                color: isSelected ? _C.primary : _C.surface,
              ),
              child: isSelected
                  ? const Center(
                      child: Icon(
                        Icons.check,
                        size: 12,
                        color: Colors.white,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                  color: isSelected ? _C.primary : _C.ink,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
