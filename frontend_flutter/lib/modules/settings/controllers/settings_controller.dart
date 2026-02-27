import 'package:get/get.dart';
import '../../../core/storage/storage_service.dart';
import '../../../app/routes/app_routes.dart';

class SettingsController extends GetxController {
  final userName = ''.obs;
  final userPhone = ''.obs;
  final userRole = ''.obs;
  final kycStatus = ''.obs;

  @override
  void onInit() {
    super.onInit();
    userName.value = StorageService.getName() ?? '';
    userPhone.value = StorageService.getPhone() ?? '';
    userRole.value = StorageService.getRole() ?? '';
    kycStatus.value = StorageService.getKycStatus() ?? 'en_attente';
  }

  Future<void> logout() async {
    await StorageService.clearAll();
    Get.offAllNamed(Routes.login);
  }
}
