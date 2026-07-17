import 'package:dio/dio.dart';
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
  final cguAccepted = false.obs;
  final currentUser = Rx<UserModel?>(null);

  // Reset Account / Lost phone
  final resetOldPhone = ''.obs;
  final resetNewPhone = ''.obs;
  final resetName = ''.obs;
  final resetRole = Rx<String?>(null);
  final resetOtp = ''.obs;
  final isResetOtpSent = false.obs;
  final isResetting = false.obs;

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
        final userData = result['user'];
        if (userData != null) {
          // Parse user data and save to storage
          final user = userData is UserModel
              ? userData
              : UserModel.fromJson(userData as Map<String, dynamic>);

          currentUser.value = user;

          StorageService.saveUserId(user.id);
          StorageService.saveName(user.name ?? '');
          StorageService.saveRole(user.role);
          StorageService.saveKycStatus(user.kycStatus);
          StorageService.setOnboarded(true);
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
        cguAccepted: cguAccepted.value,
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

  Future<bool> acceptCgu() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final res = await _repo.acceptCgu();
      final userData = res['user'];
      if (userData != null) {
        final user = userData is UserModel
            ? userData
            : UserModel.fromJson(userData as Map<String, dynamic>);
        StorageService.saveUserId(user.id);
        StorageService.saveName(user.name ?? '');
        StorageService.saveRole(user.role);
        StorageService.saveKycStatus(user.kycStatus);
      }
      return true;
    } catch (e) {
      errorMsg.value = _parseError(e);
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> uploadCni() async {
    if (cniPath.value == null) return;
    isLoading.value = true;
    errorMsg.value = null;
    
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

  bool get canSendResetOtp =>
      resetOldPhone.value.length >= 14 &&
      resetNewPhone.value.length >= 14 &&
      resetName.value.trim().isNotEmpty &&
      resetRole.value != null;

  bool get canConfirmResetOtp => resetOtp.value.length == 4;

  Future<void> requestResetPhone() async {
    if (!canSendResetOtp) return;
    isResetting.value = true;
    errorMsg.value = null;
    try {
      await _repo.requestResetPhoneLost(
        oldPhone: resetOldPhone.value,
        newPhone: resetNewPhone.value,
        name: resetName.value.trim(),
        role: resetRole.value!,
      );
      isResetOtpSent.value = true;
    } catch (e) {
      errorMsg.value = _parseError(e);
    } finally {
      isResetting.value = false;
    }
  }

  Future<bool> confirmResetPhone() async {
    if (!canConfirmResetOtp) return false;
    isResetting.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.confirmResetPhoneLost(
        oldPhone: resetOldPhone.value,
        newPhone: resetNewPhone.value,
        name: resetName.value.trim(),
        role: resetRole.value!,
        otp: resetOtp.value,
      );

      final userData = result['user'];
      if (userData != null) {
        final user = userData is UserModel
            ? userData
            : UserModel.fromJson(userData as Map<String, dynamic>);

        StorageService.saveUserId(user.id);
        StorageService.savePhone(resetNewPhone.value);
        StorageService.saveName(user.name ?? '');
        StorageService.saveRole(user.role);
        StorageService.saveKycStatus(user.kycStatus);
        StorageService.setOnboarded(true);
      }
      return true;
    } catch (e) {
      errorMsg.value = _parseError(e);
      return false;
    } finally {
      isResetting.value = false;
    }
  }

  Future<void> resetLocalSession() async {
    isLoading.value = true;
    try {
      await StorageService.clearAll();
      phone.value = '';
      otp.value = '';
      role.value = null;
      name.value = '';
      otpSent.value = false;
      errorMsg.value = null;
      
      resetOldPhone.value = '';
      resetNewPhone.value = '';
      resetName.value = '';
      resetRole.value = null;
      resetOtp.value = '';
      isResetOtpSent.value = false;
    } finally {
      isLoading.value = false;
    }
  }

  String _parseError(dynamic e) {
    if (e is DioException) {
      if (e.response?.statusCode == 401) {
        return "Votre session a expiré ou vos identifiants sont incorrects. Veuillez vous reconnecter.";
      }
      if (e.response?.statusCode == 403) {
        return "Accès refusé. Vous n'avez pas les permissions nécessaires.";
      }
      if (e.response?.statusCode == 422) {
        if (e.response?.data != null && e.response?.data is Map) {
          final message = e.response?.data['message'];
          if (message != null) return message.toString();
        }
        return "Les données fournies sont invalides.";
      }
      if (e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout) {
        return "Délai d'attente dépassé. Veuillez vérifier votre connexion internet.";
      }
      if (e.type == DioExceptionType.connectionError) {
        return "Impossible de contacter le serveur. Veuillez vérifier votre connexion.";
      }

      if (e.response?.data != null && e.response?.data is Map) {
        final message = e.response?.data['message'];
        if (message != null) return message.toString();
      }
      return "Une erreur réseau est survenue (${e.response?.statusCode ?? 'Inconnue'}).";
    }
    return e.toString().replaceAll('Exception: ', '');
  }
}
