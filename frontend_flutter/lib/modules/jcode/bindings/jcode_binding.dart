import 'package:get/get.dart';
import '../controllers/jcode_controller.dart';

class JcodeBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<JcodeController>(() => JcodeController());
  }
}
