import 'package:get/get.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/models/user_model.dart';

class AuthController extends GetxController {
  final AuthRepository _repo = AuthRepository();

  final phone = ''.obs;
  final otp = ''.obs;
  final role = Rx<String?>(null);
  final name = ''.obs;
  final isLoading = false.obs;
  final otpSent = false.obs;
  final errorMsg = Rx<String?>(null);

  // KYC
  final cniPath = Rx<String?>(null);
  final selfiePath = Rx<String?>(null);
  final kycStep = 0.obs;

  bool get canSendOtp =>
      phone.value.length >= 14 && phone.value.startsWith('+225');

  bool get canVerifyOtp => otp.value.length == 4;

  Future<void> sendOtp() async {
    if (!canSendOtp) return;
    isLoading.value = true;
    errorMsg.value = null;
    try {
      await _repo.sendOtp(phone.value);
      otpSent.value = true;
    } catch (e) {
      errorMsg.value = _parseError(e);
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> verifyOtp() async {
    if (!canVerifyOtp) return false;
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.verifyOtp(phone.value, otp.value);
      StorageService.savePhone(phone.value);

      final hasCompletedProfile = result['has_completed_profile'] as bool;

      // Si le profil est complet, l'utilisateur est connecté
      if (hasCompletedProfile) {
        final user = result['user'];
        if (user != null) {
          // Sauvegarder les infos utilisateur
          // StorageService.saveUser(user);
        }
      }

      return hasCompletedProfile;
    } catch (e) {
      errorMsg.value = _parseError(e);
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> selectRole(String selectedRole) async {
    role.value = selectedRole;
    StorageService.saveRole(selectedRole);
    // Role is now selected in login screen, no navigation needed here
  }

  Future<void> register() async {
    if (name.value.trim().isEmpty || role.value == null) return;
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.register(
        phone: phone.value,
        role: role.value!,
        name: name.value.trim(),
      );

      final user = result['user'] as UserModel;

      StorageService.saveUserId(user.id);
      StorageService.saveName(user.name ?? name.value.trim());
      StorageService.saveKycStatus(user.kycStatus);
      StorageService.saveRole(user.role);
      StorageService.setOnboarded(true);

      // Token is already saved in repository
    } catch (e) {
      errorMsg.value = _parseError(e);
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> uploadCni() async {
    if (cniPath.value == null) return;
    isLoading.value = true;
    try {
      await _repo.uploadCni(cniPath.value!);
      kycStep.value = kycStep.value < 2 ? kycStep.value + 1 : kycStep.value;
    } catch (e) {
      errorMsg.value = _parseError(e);
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> uploadSelfie() async {
    if (selfiePath.value == null) return;
    isLoading.value = true;
    try {
      await _repo.uploadSelfie(selfiePath.value!);
      kycStep.value = 2;
    } catch (e) {
      errorMsg.value = _parseError(e);
    } finally {
      isLoading.value = false;
    }
  }

  String _parseError(dynamic e) {
    return e.toString().replaceAll('Exception: ', '');
  }
}
