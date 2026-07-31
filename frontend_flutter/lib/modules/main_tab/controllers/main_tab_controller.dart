import 'package:get/get.dart';
import '../../../core/storage/storage_service.dart';

import 'package:onesignal_flutter/onesignal_flutter.dart';

class MainTabController extends GetxController {
  final currentIndex = 0.obs;
  final role = Rx<String?>(null);

  @override
  void onInit() {
    super.onInit();
    role.value = StorageService.getRole();
    
    final userId = StorageService.getUserId();
    if (userId != null) {
      try {
        OneSignal.login(userId.toString());
      } catch (_) {}
    }
  }

  void changeTab(int index) => currentIndex.value = index;

  void switchSpace(String newRole) {
    StorageService.saveRole(newRole);
    role.value = newRole;
    currentIndex.value = 0;
  }

  bool get isClient => role.value == 'client';
  bool get isArtisan => role.value == 'artisan';
  bool get isFournisseur => role.value == 'fournisseur';
  bool get isDriver => role.value == 'driver' || role.value == 'livreur' || role.value == 'LIVREUR';
}
