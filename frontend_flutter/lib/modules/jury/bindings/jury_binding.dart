import 'package:get/get.dart';

import '../controllers/jury_dossier_detail_controller.dart';
import '../controllers/jury_dossiers_controller.dart';

class JuryDossiersBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<JuryDossiersController>(() => JuryDossiersController());
  }
}

class JuryDossierDetailBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<JuryDossierDetailController>(
      () => JuryDossierDetailController(),
    );
  }
}
