import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../controllers/home_controller.dart';
import 'client_mobile_money_card.dart';
import 'dashboard_mini_card.dart';
import 'expense_progress.dart';
import 'section_header.dart';
import 'top_artisans_section.dart';
import 'top_drivers_section.dart';
import 'top_suppliers_section.dart';

/// Onglet « Tableau de Bord » de l'espace client : compteurs devis/litiges,
/// compte Mobile Money, dépenses par catégorie et classements
/// artisans / fournisseurs / livreurs.
class ClientDashboardView extends StatelessWidget {
  const ClientDashboardView({super.key, required this.controller});

  final HomeController controller;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: DashboardMiniCard(
                title: 'Devis Acceptés',
                value: '${controller.acceptedDevisCount.value}',
                icon: Icons.assignment_turned_in_rounded,
                color: AppColors.success,
                bg: AppColors.success.withValues(alpha: 0.08),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: DashboardMiniCard(
                title: 'Devis Refusés',
                value: '${controller.refusedDevisCount.value}',
                icon: Icons.assignment_late_rounded,
                color: AppColors.danger,
                bg: AppColors.danger.withValues(alpha: 0.08),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: DashboardMiniCard(
                title: 'Litiges Actifs',
                value: '${controller.disputesCount.value}',
                icon: Icons.gavel_rounded,
                color: AppColors.warning,
                bg: AppColors.warning.withValues(alpha: 0.08),
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        ClientMobileMoneyCard(controller: controller),
        const SizedBox(height: 24),
        const SectionHeader(title: 'Dépenses par catégorie'),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            children: controller.expensesByCategory.entries.map((e) {
              final total = controller.expensesByCategory.values.isEmpty
                  ? 0
                  : controller.expensesByCategory.values
                      .reduce((a, b) => a + b);
              final pct = total > 0 ? e.value / total : 0.0;
              return Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: ExpenseProgress(
                  category: e.key,
                  amount: e.value,
                  percentage: pct,
                  color: _categoryColor(e.key),
                ),
              );
            }).toList(),
          ),
        ),
        const SizedBox(height: 24),
        const SectionHeader(title: 'Artisans les mieux notés'),
        const SizedBox(height: 12),
        TopArtisansSection(controller: controller),
        const SizedBox(height: 24),
        SectionHeader(
          title: 'Fournisseurs les mieux notés',
          trailing: TextButton(
            onPressed: () => Get.toNamed(Routes.clientSuppliers),
            child: const Text('Voir tout'),
          ),
        ),
        const SizedBox(height: 12),
        TopSuppliersSection(controller: controller),
        const SizedBox(height: 24),
        const SectionHeader(title: 'Livreurs les mieux notés'),
        const SizedBox(height: 12),
        TopDriversSection(controller: controller),
      ],
    );
  }

  Color _categoryColor(String cat) {
    switch (cat.toLowerCase()) {
      case 'maçonnerie':
        return AppColors.primary;
      case 'électricité':
        return AppColors.warning;
      case 'plomberie':
        return AppColors.client;
      case 'peinture':
        return AppColors.success;
      default:
        return AppColors.accent;
    }
  }
}
