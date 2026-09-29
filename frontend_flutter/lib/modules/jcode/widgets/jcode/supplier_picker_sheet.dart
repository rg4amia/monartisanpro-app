import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/supplier_model.dart';
import '../../../orders/utils/supplier_grouping.dart';
import '../../../services/utils/service_icon_helper.dart';

/// Choix d'un fournisseur agréé, présenté par secteur d'activité comme dans
/// l'espace client. Renvoie le fournisseur choisi, ou null si l'artisan ferme
/// la feuille sans choisir.
Future<SupplierModel?> showSupplierPickerSheet(
  BuildContext context, {
  required List<SupplierModel> suppliers,
  int? selectedId,
}) {
  return showModalBottomSheet<SupplierModel>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => SupplierPickerSheet(
      suppliers: suppliers,
      selectedId: selectedId,
    ),
  );
}

class SupplierPickerSheet extends StatelessWidget {
  const SupplierPickerSheet({
    super.key,
    required this.suppliers,
    this.selectedId,
  });

  final List<SupplierModel> suppliers;
  final int? selectedId;

  @override
  Widget build(BuildContext context) {
    final groups = groupSuppliersBySector(suppliers);

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.8,
        ),
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          children: [
            const Text(
              'Choisir un fournisseur',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            for (final group in groups) ...[
              Padding(
                padding: const EdgeInsets.only(top: 12, bottom: 4),
                child: Row(
                  children: [
                    Icon(
                      group.sector == null
                          ? Icons.storefront_rounded
                          : ServiceIconHelper.getSectorIcon(
                              group.sector!.name,
                              group.sector!.icon,
                            ),
                      size: 18,
                      color: group.sector == null
                          ? AppColors.textSecondary
                          : ServiceIconHelper.getSectorColor(
                              group.sector!.name,
                              group.sector!.color,
                            ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      group.label,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
              for (final supplier in group.suppliers)
                ListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 8),
                  leading: const Icon(Icons.store, color: AppColors.primary),
                  title: Text(supplier.shopName),
                  subtitle: Text(
                    [
                      if (supplier.trade != null) supplier.trade!,
                      '${supplier.activeProductsCount} article${supplier.activeProductsCount > 1 ? 's' : ''}',
                    ].join(' · '),
                  ),
                  trailing: supplier.id == selectedId
                      ? const Icon(Icons.check_circle, color: AppColors.success)
                      : null,
                  onTap: () => Get.back(result: supplier),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Confirmation avant de retirer des articles du bon en préparation.
Future<bool> confirmDropItems(String message) async {
  final confirmed = await Get.dialog<bool>(
    AlertDialog(
      title: const Text('Retirer des articles ?'),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Get.back(result: false),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: () => Get.back(result: true),
          child: const Text('Continuer'),
        ),
      ],
    ),
  );
  return confirmed ?? false;
}
