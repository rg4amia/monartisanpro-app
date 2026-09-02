import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/devis_controller.dart';
import '../widgets/devis_creation/ai_assistant_card.dart';
import '../widgets/devis_creation/creation_app_bar.dart';
import '../widgets/devis_creation/jalons_section.dart';
import '../widgets/devis_creation/labor_section.dart';
import '../widgets/devis_creation/materials_section.dart';
import '../widgets/devis_creation/mission_info_card.dart';
import '../widgets/devis_creation/recap_section.dart';
import '../widgets/devis_creation/submit_button.dart';
import '../widgets/devis_creation/supplier_section.dart';
import '../widgets/devis_creation/workflow_card.dart';

/// Vue de création de devis pour l'artisan.
///
/// WORKFLOW :
/// 1. Client crée mission + sélectionne artisan → notification artisan
/// 2. **Artisan crée devis (cette vue)** → notification client
/// 3. Client valide / refuse le devis
class DevisCreationScreen extends StatefulWidget {
  const DevisCreationScreen({super.key});

  @override
  State<DevisCreationScreen> createState() => _DevisCreationScreenState();
}

class _DevisCreationScreenState extends State<DevisCreationScreen> {
  late final DevisController controller;
  MissionModel? mission;
  int? missionId;

  @override
  void initState() {
    super.initState();
    controller = Get.isRegistered<DevisController>()
        ? Get.find<DevisController>()
        : Get.put(DevisController());

    final args = Get.arguments;
    if (args is MissionModel) {
      mission = args;
      missionId = args.id;
    } else if (args is int) {
      missionId = args;
    }

    if (missionId != null) {
      controller.prepareDraftForMission(missionId!);
    }

    controller.loadSuppliers();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Column(
          children: [
            const CreationAppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    MissionInfoCard(missionId: missionId, mission: mission),
                    const SizedBox(height: 16),
                    const WorkflowCard(),
                    const SizedBox(height: 24),
                    AiAssistantCard(controller: controller),
                    const SizedBox(height: 24),
                    SupplierSection(controller: controller),
                    const SizedBox(height: 24),
                    MaterialsSection(controller: controller),
                    const SizedBox(height: 24),
                    LaborSection(controller: controller),
                    const SizedBox(height: 24),
                    JalonsSection(controller: controller),
                    const SizedBox(height: 24),
                    RecapSection(controller: controller),
                    const SizedBox(height: 100),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: SubmitButton(
        controller: controller,
        missionId: missionId,
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }
}
