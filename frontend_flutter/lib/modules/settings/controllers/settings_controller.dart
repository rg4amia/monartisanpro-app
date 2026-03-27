import 'package:get/get.dart';
import '../../../app/routes/app_routes.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../../data/repositories/wallet_repository.dart';
import '../../../core/storage/storage_service.dart';

class SettingsController extends GetxController {
  final AuthRepository _authRepo = AuthRepository();
  final WalletRepository _walletRepo = WalletRepository();
  final MissionRepository _missionRepo = MissionRepository();

  final userName = ''.obs;
  final userPhone = ''.obs;
  final userRole = ''.obs;
  final kycStatus = ''.obs;
  final walletBalance = 0.obs;
  final ordersCount = 0.obs;
  final isLoading = false.obs;

  @override
  void onInit() {
    super.onInit();
    userName.value = StorageService.getName() ?? '';
    userPhone.value = StorageService.getPhone() ?? '';
    userRole.value = StorageService.getRole() ?? '';
    kycStatus.value = StorageService.getKycStatus() ?? 'en_attente';
    _loadData();
  }

  Future<void> _loadData() async {
    isLoading.value = true;
    try {
      // Load wallet balance
      final balance = await _walletRepo.getBalance();
      walletBalance.value = balance['total'] ?? 0;

      // Load missions count
      final missions = await _missionRepo.getMissions();
      ordersCount.value = missions.length;
    } catch (_) {
      // Keep default values
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> logout() async {
    isLoading.value = true;
    try {
      await _authRepo.logout(); // révoque le token côté serveur puis vide le stockage
    } finally {
      isLoading.value = false;
      Get.offAllNamed(Routes.login);
    }
  }
}
