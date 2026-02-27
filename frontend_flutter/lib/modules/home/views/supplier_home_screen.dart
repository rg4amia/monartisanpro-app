import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/home_controller.dart';

class SupplierHomeScreen extends StatefulWidget {
  const SupplierHomeScreen({super.key});

  @override
  State<SupplierHomeScreen> createState() => _SupplierHomeScreenState();
}

class _SupplierHomeScreenState extends State<SupplierHomeScreen> {
  final _manualController = TextEditingController();

  @override
  void dispose() {
    _manualController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Espace Fournisseur'),
        backgroundColor: AppColors.success,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Scanner area
            GestureDetector(
              onTap: () => Get.toNamed(Routes.scanner),
              child: Container(
                width: double.infinity,
                height: 200,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.05),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                      color: AppColors.primary.withValues(alpha: 0.2),
                      width: 2),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Stack(
                      alignment: Alignment.center,
                      children: [
                        Container(
                          width: 100,
                          height: 100,
                          decoration: BoxDecoration(
                            border: Border.all(
                                color: AppColors.primary.withValues(alpha: 0.3),
                                width: 2),
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        const Icon(Icons.qr_code_scanner,
                            size: 48, color: AppColors.primary),
                      ],
                    ),
                    const SizedBox(height: 16),
                    const Text('Scanner un J-Code',
                        style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary)),
                    const SizedBox(height: 4),
                    const Text('Appuyez pour ouvrir le scanner QR',
                        style: TextStyle(
                            fontSize: 13, color: AppColors.textSecondary)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Manual code entry
            const Text('Ou saisir manuellement',
                style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary)),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _manualController,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                      hintText: 'PA-XXXX',
                      prefixIcon:
                          Icon(Icons.qr_code, color: AppColors.textMuted),
                    ),
                    onChanged: (v) {
                      if (!v.startsWith('PA-') && v.isNotEmpty) {
                        _manualController.text = 'PA-${v.replaceAll('PA-', '')}';
                        _manualController.selection =
                            TextSelection.fromPosition(
                          TextPosition(
                              offset: _manualController.text.length),
                        );
                      }
                    },
                  ),
                ),
                const SizedBox(width: 10),
                ElevatedButton(
                  onPressed: () {
                    final code = _manualController.text.trim();
                    if (code.length >= 7) {
                      Get.toNamed(Routes.transactionConfirm,
                          arguments: {'code': code});
                    }
                  },
                  style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.success,
                      minimumSize: const Size(60, 52),
                      padding: EdgeInsets.zero),
                  child: const Icon(Icons.check, color: Colors.white),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Recent transactions
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Transactions récentes',
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary)),
                Obx(() => Text('${c.activeMissions.length}',
                    style: const TextStyle(
                        fontSize: 13, color: AppColors.textSecondary))),
              ],
            ),
            const SizedBox(height: 12),
            Obx(() {
              if (c.activeMissions.isEmpty) {
                return const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('Aucune transaction récente.',
                        style: TextStyle(color: AppColors.textMuted)),
                  ),
                );
              }
              return ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: c.activeMissions.length,
                itemBuilder: (_, i) {
                  final m = c.activeMissions[i];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.card,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(Icons.receipt_outlined,
                              color: AppColors.success, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Mission #${m.id}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14,
                                      color: AppColors.textPrimary)),
                              Text(
                                  Formatters.fcfa(m.montantMateriaux),
                                  style: const TextStyle(
                                      fontSize: 13,
                                      color: AppColors.success,
                                      fontWeight: FontWeight.w600)),
                            ],
                          ),
                        ),
                        Text(
                          Formatters.date(m.createdAt),
                          style: const TextStyle(
                              fontSize: 11, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  );
                },
              );
            }),
          ],
        ),
      ),
    );
  }
}
