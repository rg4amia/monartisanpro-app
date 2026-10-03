import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../orders/views/supplier_litiges_screen.dart';
import '../../wallet/views/driver_cashout_screen.dart';

/// Une rubrique de « Mon historique ».
class HistoryEntry {
  const HistoryEntry({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.open,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback open;
}

/// Rubriques d'historique du rôle connecté. Le rôle canonique du livreur est
/// `livreur` ; `driver` subsiste sur d'anciennes sessions.
List<HistoryEntry> historyEntriesFor(String role) {
  switch (role) {
    case 'artisan':
      return [
        HistoryEntry(
          icon: Icons.receipt_long_outlined,
          title: 'Paiements',
          subtitle: 'Portefeuille et opérations',
          open: () => Get.toNamed(Routes.wallet),
        ),
        HistoryEntry(
          icon: Icons.payments_outlined,
          title: 'Versements',
          subtitle: 'Virements Mobile Money aboutis, en cours, échoués',
          open: () => Get.toNamed(Routes.receivedPayouts),
        ),
        HistoryEntry(
          icon: Icons.construction_outlined,
          title: 'Chantiers',
          subtitle: 'En cours, terminés, annulés',
          open: () => Get.toNamed(Routes.missions),
        ),
        HistoryEntry(
          icon: Icons.gavel_outlined,
          title: 'Litiges',
          subtitle: 'Ouverts et résolus, avec leur décision',
          open: () => Get.toNamed(Routes.myLitiges),
        ),
        HistoryEntry(
          icon: Icons.confirmation_number_outlined,
          title: 'Bons matériels',
          subtitle: 'Bons émis et retraits en quincaillerie',
          open: () => Get.toNamed(Routes.jcode),
        ),
      ];
    case 'fournisseur':
      return [
        HistoryEntry(
          icon: Icons.receipt_long_outlined,
          title: 'Paiements',
          subtitle: 'Opérations de votre compte',
          open: () => Get.toNamed(Routes.wallet),
        ),
        HistoryEntry(
          icon: Icons.inventory_2_outlined,
          title: 'Commandes',
          subtitle: 'À traiter et passées',
          open: () => Get.toNamed(Routes.supplierOrders),
        ),
        HistoryEntry(
          icon: Icons.account_balance_outlined,
          title: 'Virements',
          subtitle: 'Paiements reçus de ProsArtisan',
          open: () => Get.toNamed(Routes.supplierCashouts),
        ),
        HistoryEntry(
          icon: Icons.gavel_outlined,
          title: 'Litiges de commandes',
          subtitle: 'Commandes contestées et décision rendue',
          open: () => Get.toNamed(Routes.orderDisputes),
        ),
        HistoryEntry(
          icon: Icons.gpp_maybe_outlined,
          title: 'Litiges de chantiers',
          subtitle: 'Chantiers fournis ayant connu un litige',
          open: () => Get.to(() => const SupplierLitigesScreen()),
        ),
      ];
    case 'livreur':
    case 'driver':
      return [
        HistoryEntry(
          icon: Icons.receipt_long_outlined,
          title: 'Gains',
          subtitle: 'Portefeuille et opérations',
          open: () => Get.toNamed(Routes.wallet),
        ),
        HistoryEntry(
          icon: Icons.delivery_dining_outlined,
          title: 'Courses',
          subtitle: 'Livrées et annulées',
          open: () => Get.toNamed(Routes.driverDeliveries),
        ),
        HistoryEntry(
          icon: Icons.account_balance_outlined,
          title: 'Retraits',
          subtitle: 'Demandes de retrait de vos gains',
          open: () => Get.to(() => const DriverCashoutScreen()),
        ),
        HistoryEntry(
          icon: Icons.gavel_outlined,
          title: 'Courses en litige',
          subtitle: 'Livraisons contestées et décision rendue',
          open: () => Get.toNamed(Routes.orderDisputes),
        ),
      ];
    case 'referent':
      return [
        HistoryEntry(
          icon: Icons.fact_check_outlined,
          title: 'Inspections réalisées',
          subtitle: 'Chantiers que vous avez contrôlés',
          open: () => Get.toNamed(Routes.referentInspections),
        ),
        HistoryEntry(
          icon: Icons.gavel_outlined,
          title: 'Litiges',
          subtitle: 'Chantiers à visiter et visités',
          open: () => Get.toNamed(Routes.referentLitiges),
        ),
      ];
    default:
      return [
        HistoryEntry(
          icon: Icons.receipt_long_outlined,
          title: 'Paiements',
          subtitle: 'Acomptes, étapes, remboursements',
          open: () => Get.toNamed(Routes.wallet),
        ),
        HistoryEntry(
          icon: Icons.assignment_outlined,
          title: 'Missions',
          subtitle: 'En cours, terminées, annulées',
          open: () => Get.toNamed(Routes.missions),
        ),
        HistoryEntry(
          icon: Icons.gavel_outlined,
          title: 'Litiges',
          subtitle: 'Ouverts et résolus, avec leur décision',
          open: () => Get.toNamed(Routes.myLitiges),
        ),
        HistoryEntry(
          icon: Icons.shopping_bag_outlined,
          title: 'Commandes de matériaux',
          subtitle: 'En cours et passées',
          open: () => Get.toNamed(Routes.clientOrders),
        ),
        HistoryEntry(
          icon: Icons.report_gmailerrorred_outlined,
          title: 'Litiges de commandes',
          subtitle: 'Commandes contestées et décision rendue',
          open: () => Get.toNamed(Routes.orderDisputes),
        ),
      ];
  }
}

/// « Mon historique » : point d'entrée unique vers les historiques du rôle.
class HistoryHubScreen extends StatelessWidget {
  const HistoryHubScreen({this.role, super.key});

  /// Injectable pour les tests ; sinon le rôle de la session.
  final String? role;

  @override
  Widget build(BuildContext context) {
    final entries = historyEntriesFor(role ?? StorageService.getRole() ?? '');

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mon historique'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        itemCount: entries.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final entry = entries[index];

          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            child: ListTile(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(18),
              ),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              leading: Icon(entry.icon, color: AppColors.primary),
              title: Text(
                entry.title,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              subtitle: Text(entry.subtitle),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: entry.open,
            ),
          );
        },
      ),
    );
  }
}
