import 'package:get/get.dart';
import '../controllers/auth_controller.dart';

class AuthBinding extends Bindings {
  @override
  void dependencies() {
    // Use Get.put instead of lazyPut to keep the controller alive during auth flow
    Get.put<AuthController>(AuthController(), permanent: false);
  }
}
