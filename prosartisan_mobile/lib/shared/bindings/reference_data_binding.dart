import 'package:get/get.dart';
import '../controllers/reference_data_controller.dart';
import '../data/repositories/reference_data_repository.dart';

class ReferenceDataBinding extends Bindings {
  @override
  void dependencies() {
    // Repository
    Get.lazyPut<ReferenceDataRepository>(
      () => ReferenceDataRepository(),
      fenix: true,
    );

    // Controller
    Get.lazyPut<ReferenceDataController>(
      () => ReferenceDataController(),
      fenix: true,
    );
  }
}
