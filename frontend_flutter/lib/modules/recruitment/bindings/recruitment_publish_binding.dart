import 'package:get/get.dart';

import '../controllers/recruitment_publish_controller.dart';

class RecruitmentPublishBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<RecruitmentPublishController>(
      () => RecruitmentPublishController(),
    );
  }
}
