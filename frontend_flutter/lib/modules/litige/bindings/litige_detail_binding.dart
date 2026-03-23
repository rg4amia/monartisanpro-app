import 'package:get/get.dart';

import '../controllers/litige_detail_controller.dart';

class LitigeDetailBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<LitigeDetailController>(() => LitigeDetailController());
  }
}
