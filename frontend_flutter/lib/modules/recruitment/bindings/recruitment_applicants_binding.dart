import 'package:get/get.dart';

import '../controllers/recruitment_applicants_controller.dart';

class RecruitmentApplicantsBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<RecruitmentApplicantsController>(
      () => RecruitmentApplicantsController(),
    );
  }
}
