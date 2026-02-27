import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/devis_controller.dart';

class QuoteScreen extends GetView<DevisController> {
  const QuoteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Devis'),
        actions: [
          Obx(() {
            if (controller.devis.value == null) return const SizedBox();
            final statut = controller.devis.value!.statut;
            if (statut == 'soumis') {
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: _StatusChip(statut: statut),
              );
            }
            return const SizedBox();
          }),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }
        final devis = controller.devis.value;
        if (devis == null) {
          return const Center(child: Text('Aucun devis disponible'));
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _SectionCard(
              title: 'Lignes du devis',
              child: Column(
                children: [
                  ...devis.lignes.map((l) => _LigneTile(ligne: l)),
                  const Divider(height: 24),
                  _TotalRow(
                    label: 'Main d\'œuvre',
                    amount: devis.totalMo,
                    color: AppColors.accent,
                  ),
                  _TotalRow(
                    label: 'Matériaux',
                    amount: devis.totalMat,
                    color: AppColors.success,
                  ),
                  const SizedBox(height: 8),
                  _TotalRow(
                    label: 'Total',
                    amount: devis.totalMo + devis.totalMat,
                    color: AppColors.primary,
                    isBold: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _SectionCard(
              title: 'Jalons de paiement',
              child: Column(
                children: devis.jalons
                    .map((j) => _JalonTile(jalon: j))
                    .toList(),
              ),
            ),
            const SizedBox(height: 24),
            if (devis.statut == 'soumis') ...[
              ElevatedButton(
                onPressed: () => controller.acceptDevis(devis.id),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.success,
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text('Accepter le devis'),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => controller.refuseDevis(devis.id),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.danger,
                  side: const BorderSide(color: AppColors.danger),
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text('Refuser le devis'),
              ),
            ],
            if (devis.statut == 'accepte')
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.success),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle, color: AppColors.success),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Text(
                        'Devis accepté — la mission est financée',
                        style: TextStyle(
                          color: AppColors.success,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      }),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;
  const _SectionCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 16,
                color: AppColors.primary,
              ),
            ),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }
}

class _LigneTile extends StatelessWidget {
  final dynamic ligne;
  const _LigneTile({required this.ligne});

  @override
  Widget build(BuildContext context) {
    final isMo = ligne.type == 'mo';
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: (isMo ? AppColors.accent : AppColors.success)
                  .withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(4),
            ),
            child: Text(
              isMo ? 'MO' : 'MAT',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: isMo ? AppColors.accent : AppColors.success,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(ligne.description),
          ),
          Text(
            Formatters.fcfa(ligne.montant),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _JalonTile extends StatelessWidget {
  final dynamic jalon;
  const _JalonTile({required this.jalon});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          CircleAvatar(
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
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  jalon.description,
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
                if (jalon.dateCible != null)
                  Text(
                    Formatters.date(jalon.dateCible),
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          Text(
            Formatters.fcfa(jalon.montant),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
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

class _StatusChip extends StatelessWidget {
  final String statut;
  const _StatusChip({required this.statut});

  @override
  Widget build(BuildContext context) {
    final colors = {
      'brouillon': Colors.grey,
      'soumis': Colors.orange,
      'accepte': AppColors.success,
      'refuse': AppColors.danger,
    };
    final labels = {
      'brouillon': 'Brouillon',
      'soumis': 'En attente',
      'accepte': 'Accepté',
      'refuse': 'Refusé',
    };
    final color = colors[statut] ?? Colors.grey;
    return Chip(
      label: Text(
        labels[statut] ?? statut,
        style: const TextStyle(fontSize: 12, color: Colors.white),
      ),
      backgroundColor: color,
      padding: EdgeInsets.zero,
    );
  }
}
