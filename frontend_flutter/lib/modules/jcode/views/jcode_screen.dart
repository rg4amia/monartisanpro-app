import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/jcode_controller.dart';

class JcodeScreen extends GetView<JcodeController> {
  const JcodeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('J-Code Matériaux')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }
        final jcode = controller.activeJcode.value;
        if (jcode == null) {
          return _EmptyState(
            onGenerate: () => _showGenerateDialog(context),
          );
        }
        return _JcodeDetail(jcode: jcode);
      }),
      floatingActionButton: Obx(() {
        if (controller.activeJcode.value != null) return const SizedBox();
        return FloatingActionButton.extended(
          onPressed: () => _showGenerateDialog(context),
          icon: const Icon(Icons.add),
          label: const Text('Générer J-Code'),
        );
      }),
    );
  }

  void _showGenerateDialog(BuildContext context) {
    final missionIdCtrl = TextEditingController();
    final montantCtrl = TextEditingController();
    Get.dialog(
      AlertDialog(
        title: const Text('Générer un J-Code'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: missionIdCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'ID de la mission'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: montantCtrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Montant matériaux (FCFA)',
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
              final missionId = int.tryParse(missionIdCtrl.text);
              final montant = int.tryParse(montantCtrl.text);
              if (missionId != null && montant != null && montant > 0) {
                Get.back();
                controller.generateJcode(missionId, montant);
              }
            },
            child: const Text('Générer'),
          ),
        ],
      ),
    );
  }
}

class _JcodeDetail extends StatelessWidget {
  final dynamic jcode;
  const _JcodeDetail({required this.jcode});

  @override
  Widget build(BuildContext context) {
    final isActif = jcode.statut == 'actif';
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          // QR Code
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  if (isActif)
                    QrImageView(
                      data: jcode.id.toString(),
                      version: QrVersions.auto,
                      size: 200,
                    )
                  else
                    const Icon(Icons.qr_code, size: 200, color: AppColors.border),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      jcode.code,
                      style: const TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 4,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  _StatusBadge(statut: jcode.statut),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Détails
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  _DetailRow(
                    label: 'Montant',
                    value: Formatters.fcfa(jcode.montant as int),
                    valueStyle: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                      color: AppColors.success,
                    ),
                  ),
                  const Divider(height: 16),
                  _DetailRow(
                    label: 'Expire le',
                    value: Formatters.dateTime(jcode.expiresAt),
                  ),
                  if (jcode.scannedAt != null) ...[
                    const Divider(height: 16),
                    _DetailRow(
                      label: 'Scanné le',
                      value: Formatters.dateTime(jcode.scannedAt),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Code USSD
          Card(
            child: ListTile(
              leading: const Icon(Icons.phone_in_talk, color: AppColors.primary),
              title: const Text('Code USSD'),
              subtitle: Text(
                jcode.ussdCode ?? '*144#',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  letterSpacing: 2,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final VoidCallback onGenerate;
  const _EmptyState({required this.onGenerate});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.qr_code_2, size: 80, color: AppColors.border),
          const SizedBox(height: 16),
          const Text(
            'Aucun J-Code actif',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w600,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Générez un J-Code pour commander\ndes matériaux auprès d\'un fournisseur',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textMuted),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: onGenerate,
            icon: const Icon(Icons.add),
            label: const Text('Générer un J-Code'),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String statut;
  const _StatusBadge({required this.statut});

  @override
  Widget build(BuildContext context) {
    final colors = {
      'actif': AppColors.success,
      'utilise': AppColors.info,
      'expire': AppColors.danger,
    };
    final color = colors[statut] ?? AppColors.textSecondary;
    return Chip(
      label: Text(
        Formatters.jcodeStatus(statut),
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;
  final TextStyle? valueStyle;
  const _DetailRow({required this.label, required this.value, this.valueStyle});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary)),
        Text(value,
            style: valueStyle ??
                const TextStyle(fontWeight: FontWeight.w600)),
      ],
    );
  }
}
