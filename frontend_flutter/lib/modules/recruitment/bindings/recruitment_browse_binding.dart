import 'package:get/get.dart';

import '../controllers/recruitment_browse_controller.dart';

class RecruitmentBrowseBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<RecruitmentBrowseController>(
      () => RecruitmentBrowseController(),
    );
  }
}
