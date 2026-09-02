import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/supplier_model.dart';
import '../../controllers/devis_controller.dart';

/// Sélection de la quincaillerie partenaire qui sert de base au devis
/// matériaux, avec fiche récapitulative du fournisseur retenu.
class SupplierSection extends StatelessWidget {
  const SupplierSection({required this.controller, super.key});

  final DevisController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
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
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Choisissez le fournisseur qui servira de base au devis matériaux. Tous les articles catalogue viendront de cette même quincaillerie.',
              style: TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
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
                    borderSide: const BorderSide(color: AppColors.border),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.border),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide:
                        const BorderSide(color: AppColors.primary, width: 2),
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
                  color: AppColors.secondary,
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
                        color: AppColors.primary,
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
        Icon(icon, size: 15, color: AppColors.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
