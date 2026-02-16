import 'package:get/get.dart';
import '../controllers/home_controller.dart';
import '../../data/repositories/home_repository.dart';

/// Binding pour la page d'accueil
/// Gère l'injection de dépendances pour HomeController
class HomeBinding extends Bindings {
  @override
  void dependencies() {
    // Repository
    Get.lazyPut<HomeRepository>(() => HomeRepository());

    // Controller
    Get.lazyPut<HomeController>(() => HomeController());
  }
}
