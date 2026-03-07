import 'package:get/get.dart';
import '../controllers/settings_controller.dart';
import '../controllers/update_profile_controller.dart';

class SettingsBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<SettingsController>(() => SettingsController());
    Get.lazyPut<UpdateProfileController>(() => UpdateProfileController());
  }
}
