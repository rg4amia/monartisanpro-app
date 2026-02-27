import 'package:get/get.dart';
import '../controllers/missions_controller.dart';

class MissionsBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<MissionsController>(() => MissionsController());
  }
}
