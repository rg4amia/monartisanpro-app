import 'package:get/get.dart';

import '../controllers/recruitment_engagement_controller.dart';

class RecruitmentEngagementBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<RecruitmentEngagementController>(
      () => RecruitmentEngagementController(),
    );
  }
}
