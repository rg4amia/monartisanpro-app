import 'package:get/get.dart';
import '../controllers/artisan_controller.dart';

class ArtisansBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<ArtisanController>(() => ArtisanController());
  }
}
