import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/missions_controller.dart';

/// Ouvre l'écran de création de devis pour [mission] et, au retour d'un
/// devis créé, rafraîchit la mission et notifie l'artisan.
///
/// [isAvenant] : soumission du devis complémentaire (matériaux) après un
/// devis initial de déplacement/diagnostic déjà accepté et financé.
Future<void> openDevisCreation(
  MissionModel mission, {
  bool isAvenant = false,
}) async {
  final result = await Get.toNamed(
    Routes.devisCreation,
    arguments: {'mission': mission, 'isAvenant': isAvenant},
  );
  if (result != true || !Get.isRegistered<MissionsController>()) {
    return;
  }

  WidgetsBinding.instance.addPostFrameCallback((_) {
    final controller = Get.find<MissionsController>();
    controller.loadMission(mission.id, forceRefresh: true);

    Get.snackbar(
      isAvenant ? 'Avenant créé' : 'Devis créé',
      isAvenant
          ? 'Votre devis complémentaire a été envoyé au client pour validation.'
          : 'Votre devis a été envoyé au client pour validation.',
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 3),
    );
  });
}
