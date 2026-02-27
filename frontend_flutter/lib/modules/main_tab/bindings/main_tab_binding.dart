import 'package:get/get.dart';
import '../controllers/main_tab_controller.dart';
import '../../home/controllers/home_controller.dart';
import '../../missions/controllers/missions_controller.dart';
import '../../notifications/controllers/notifications_controller.dart';
import '../../settings/controllers/settings_controller.dart';

class MainTabBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<MainTabController>(() => MainTabController());
    Get.lazyPut<HomeController>(() => HomeController());
    Get.lazyPut<MissionsController>(() => MissionsController());
    Get.lazyPut<NotificationsController>(() => NotificationsController());
    Get.lazyPut<SettingsController>(() => SettingsController());
  }
}
