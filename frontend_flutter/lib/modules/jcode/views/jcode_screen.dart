import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/jcode_controller.dart';
import '../widgets/jcode/jcode_composer_view.dart';
import '../widgets/jcode/jcode_detail.dart';

class JcodeScreen extends StatefulWidget {
  const JcodeScreen({super.key});

  @override
  State<JcodeScreen> createState() => _JcodeScreenState();
}

class _JcodeScreenState extends State<JcodeScreen> {
  late final JcodeController controller;
  final TextEditingController _missionIdCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    controller = Get.find<JcodeController>();

    final args = Get.arguments;
    if (args is int) {
      _missionIdCtrl.text = args.toString();
    } else if (args is Map<String, dynamic> && args['missionId'] != null) {
      _missionIdCtrl.text = args['missionId'].toString();
    }
  }

  @override
  void dispose() {
    _missionIdCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('J-Code Matériaux'),
        actions: [
          IconButton(
            onPressed: controller.loadActiveJcode,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value &&
            controller.activeJcode.value == null) {
          return const Center(child: CircularProgressIndicator());
        }

        final jcode = controller.activeJcode.value;
        if (jcode != null) {
          return JcodeDetail(jcode: jcode);
        }

        return JcodeComposerView(
          controller: controller,
          missionIdCtrl: _missionIdCtrl,
          onAddCustomItem: _showCustomItemDialog,
        );
      }),
    );
  }

  Future<void> _showCustomItemDialog() async {
    final nameCtrl = TextEditingController();
    final skuCtrl = TextEditingController();
    final quantityCtrl = TextEditingController(text: '1');
    final priceCtrl = TextEditingController();

    await Get.dialog(
      AlertDialog(
        title: const Text('Article hors catalogue'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(
                  labelText: 'Nom de l\'article',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: skuCtrl,
                decoration: const InputDecoration(
                  labelText: 'SKU / Référence (optionnel)',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: quantityCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Quantité'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: priceCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Prix unitaire (FCFA)',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: Get.back,
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () {
              final name = nameCtrl.text.trim();
              final quantity = int.tryParse(quantityCtrl.text.trim());
              final unitPrice = int.tryParse(priceCtrl.text.trim());

              if (name.isEmpty ||
                  quantity == null ||
                  quantity <= 0 ||
                  unitPrice == null ||
                  unitPrice <= 0) {
                Get.snackbar(
                  'Champs invalides',
                  'Renseignez un nom, une quantité et un prix unitaire valides.',
                  snackPosition: SnackPosition.TOP,
                );
                return;
              }

              controller.addCustomItem(
                name: name,
                sku: skuCtrl.text.trim(),
                quantity: quantity,
                unitPrice: unitPrice,
              );
              Get.back();
            },
            child: const Text('Ajouter'),
          ),
        ],
      ),
    );

    nameCtrl.dispose();
    skuCtrl.dispose();
    quantityCtrl.dispose();
    priceCtrl.dispose();
  }
}
