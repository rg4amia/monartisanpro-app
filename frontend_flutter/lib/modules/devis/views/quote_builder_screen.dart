import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/devis_controller.dart';

class QuoteBuilderScreen extends GetView<DevisController> {
  const QuoteBuilderScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final missionId = (Get.arguments as Map?)?['missionId'] as int? ?? 0;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Créer un devis'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Lignes MO
          _SectionHeader(
            title: 'Main d\'œuvre',
            color: AppColors.accent,
            onAdd: () => _showAddLigneDialog(context, 'mo'),
          ),
          Obx(
            () => Column(
              children: controller.lignes
                  .asMap()
                  .entries
                  .where((e) => e.value.type == 'mo')
                  .map(
                    (e) => _LigneTile(
                      ligne: e.value,
                      onDelete: () => controller.removeLigne(e.key),
                    ),
                  )
                  .toList(),
            ),
          ),
          const SizedBox(height: 16),

          // Lignes Matériaux
          _SectionHeader(
            title: 'Matériaux',
            color: AppColors.success,
            onAdd: () => _showAddLigneDialog(context, 'mat'),
          ),
          Obx(
            () => Column(
              children: controller.lignes
                  .asMap()
                  .entries
                  .where((e) => e.value.type == 'mat')
                  .map(
                    (e) => _LigneTile(
                      ligne: e.value,
                      onDelete: () => controller.removeLigne(e.key),
                    ),
                  )
                  .toList(),
            ),
          ),
          const SizedBox(height: 16),

          // Jalons
          _SectionHeader(
            title: 'Jalons de paiement',
            color: AppColors.primary,
            onAdd: () => _showAddJalonDialog(context),
          ),
          Obx(
            () => Column(
              children: controller.jalons
                  .asMap()
                  .entries
                  .map(
                    (e) => _JalonTile(
                      jalon: e.value,
                      onDelete: () => controller.removeJalon(e.key),
                    ),
                  )
                  .toList(),
            ),
          ),
          const SizedBox(height: 24),

          // Totaux
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Obx(
                () => Column(
                  children: [
                    _TotalRow(
                      label: 'Main d\'œuvre',
                      amount: controller.totalMo,
                      color: AppColors.accent,
                    ),
                    _TotalRow(
                      label: 'Matériaux',
                      amount: controller.totalMat,
                      color: AppColors.success,
                    ),
                    const Divider(height: 16),
                    _TotalRow(
                      label: 'Total',
                      amount: controller.total,
                      color: AppColors.primary,
                      isBold: true,
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),

          Obx(
            () => ElevatedButton(
              onPressed: controller.isLoading.value ||
                      controller.lignes.isEmpty ||
                      controller.jalons.isEmpty
                  ? null
                  : () => controller.submitDevis(missionId),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(54),
              ),
              child: controller.isLoading.value
                  ? const CircularProgressIndicator(color: Colors.white)
                  : const Text(
                      'Envoyer le devis',
                      style: TextStyle(fontSize: 16),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  void _showAddLigneDialog(BuildContext context, String type) {
    final descCtrl = TextEditingController();
    final montantCtrl = TextEditingController();
    Get.dialog(
      AlertDialog(
        title:
            Text(type == 'mo' ? 'Ajouter main d\'œuvre' : 'Ajouter matériau'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: descCtrl,
              decoration: const InputDecoration(labelText: 'Description'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: montantCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Montant (FCFA)',
                suffixText: 'FCFA',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              final montant = int.tryParse(montantCtrl.text) ?? 0;
              if (descCtrl.text.trim().isNotEmpty && montant > 0) {
                controller.addLigne(type, descCtrl.text.trim(), montant);
                Get.back();
              }
            },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );
  }

  void _showAddJalonDialog(BuildContext context) {
    final descCtrl = TextEditingController();
    final montantCtrl = TextEditingController();
    final dateCtrl = TextEditingController();
    Get.dialog(
      AlertDialog(
        title: const Text('Ajouter un jalon'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: descCtrl,
              decoration:
                  const InputDecoration(labelText: 'Description du jalon'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: montantCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Montant (FCFA)',
                suffixText: 'FCFA',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: dateCtrl,
              readOnly: true,
              decoration: const InputDecoration(
                labelText: 'Date cible',
                suffixIcon: Icon(Icons.calendar_today, size: 18),
              ),
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: DateTime.now().add(const Duration(days: 7)),
                  firstDate: DateTime.now(),
                  lastDate: DateTime.now().add(const Duration(days: 365)),
                );
                if (picked != null) {
                  dateCtrl.text = picked.toIso8601String().substring(0, 10);
                }
              },
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              final montant = int.tryParse(montantCtrl.text) ?? 0;
              if (descCtrl.text.trim().isNotEmpty &&
                  montant > 0 &&
                  dateCtrl.text.isNotEmpty) {
                controller.addJalon(
                  descCtrl.text.trim(),
                  montant,
                  dateCtrl.text,
                );
                Get.back();
              }
            },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final Color color;
  final VoidCallback onAdd;
  const _SectionHeader({
    required this.title,
    required this.color,
    required this.onAdd,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            title,
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 15,
              color: color,
            ),
          ),
          IconButton(
            onPressed: onAdd,
            icon: Icon(Icons.add_circle, color: color),
          ),
        ],
      ),
    );
  }
}

class _LigneTile extends StatelessWidget {
  final dynamic ligne;
  final VoidCallback onDelete;
  const _LigneTile({required this.ligne, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(ligne.description),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              Formatters.fcfa(ligne.montant),
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, color: AppColors.danger),
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _JalonTile extends StatelessWidget {
  final dynamic jalon;
  final VoidCallback onDelete;
  const _JalonTile({required this.jalon, required this.onDelete});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          radius: 14,
          backgroundColor: AppColors.primary.withValues(alpha: 0.1),
          child: Text(
            '${jalon.ordre}',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: AppColors.primary,
            ),
          ),
        ),
        title: Text(jalon.description),
        subtitle: jalon.dateCible != null
            ? Text(Formatters.date(jalon.dateCible))
            : null,
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              Formatters.fcfa(jalon.montant),
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, color: AppColors.danger),
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  final String label;
  final int amount;
  final Color color;
  final bool isBold;
  const _TotalRow({
    required this.label,
    required this.amount,
    required this.color,
    this.isBold = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              fontSize: isBold ? 16 : 14,
            ),
          ),
          Text(
            Formatters.fcfa(amount),
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              fontSize: isBold ? 16 : 14,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}
