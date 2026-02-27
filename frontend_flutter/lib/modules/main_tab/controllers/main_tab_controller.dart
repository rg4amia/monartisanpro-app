import 'package:get/get.dart';
import '../../../core/storage/storage_service.dart';

class MainTabController extends GetxController {
  final currentIndex = 0.obs;
  final role = Rx<String?>(null);

  @override
  void onInit() {
    super.onInit();
    role.value = StorageService.getRole();
  }

  void changeTab(int index) => currentIndex.value = index;

  bool get isClient => role.value == 'client';
  bool get isArtisan => role.value == 'artisan';
  bool get isFournisseur => role.value == 'fournisseur';
}
