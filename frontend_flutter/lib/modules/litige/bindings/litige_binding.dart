import 'package:get/get.dart';
import '../controllers/litige_controller.dart';

class LitigeBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<LitigeController>(() => LitigeController());
  }
}
