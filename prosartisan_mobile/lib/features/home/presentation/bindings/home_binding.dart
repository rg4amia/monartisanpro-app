import 'package:get/get.dart';
import '../controllers/home_controller.dart';

/// Binding pour la page d'accueil
/// Gère l'injection de dépendances pour HomeController
class HomeBinding extends Bindings {
  @override
  void dependencies() {
    Get.lazyPut<HomeController>(() => HomeController());
  }
}
