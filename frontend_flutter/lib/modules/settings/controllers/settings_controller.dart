import 'dart:async';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/services/push_identity.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/phone_format.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../../data/repositories/wallet_repository.dart';

class SettingsController extends GetxController {
  final AuthRepository _authRepo = AuthRepository();
  final WalletRepository _walletRepo = WalletRepository();
  final MissionRepository _missionRepo = MissionRepository();
  final UserRepository _userRepo = UserRepository();

  final userName = ''.obs;
  final userPhone = ''.obs;
  final userRole = ''.obs;
  final kycStatus = ''.obs;
  final walletBalance = 0.obs;
  final ordersCount = 0.obs;
  final isLoading = false.obs;

  final notificationSoundEnabled = true.obs;
  final dataSaverEnabled = true.obs;

  final paymentPhone = ''.obs;
  final preferredPaymentProvider = 'wave'.obs;
  final isSavingPaymentPhone = false.obs;
  final paymentPhoneError = Rx<String?>(null);

  /// Vrai une fois le code de confirmation envoyé au numéro du compte.
  final paymentPhoneCodeSent = false.obs;

  @override
  void onInit() {
    super.onInit();
    userName.value = StorageService.getName() ?? '';
    userPhone.value = StorageService.getPhone() ?? '';
    userRole.value = StorageService.getRole() ?? '';
    kycStatus.value = StorageService.getKycStatus() ?? 'en_attente';
    notificationSoundEnabled.value =
        StorageService.isNotificationSoundEnabled();
    dataSaverEnabled.value = StorageService.isDataSaverEnabled();
    _loadData();
  }

  /// Un compte qui reçoit des gains (artisan, fournisseur, livreur) confirme
  /// tout changement de numéro de paiement par un code reçu sur le numéro du
  /// compte. Revenir à ce numéro, ou garder le même, n'en demande pas.
  bool paymentPhoneNeedsCode(String newPaymentPhone) {
    if (userRole.value == 'client') return false;
    final next = normalizeIvorianPhone(newPaymentPhone);
    return next != normalizeIvorianPhone(paymentPhone.value) &&
        next != normalizeIvorianPhone(userPhone.value);
  }

  Future<bool> requestPaymentPhoneCode() async {
    isSavingPaymentPhone.value = true;
    paymentPhoneError.value = null;
    try {
      await _userRepo.requestPaymentPhoneCode();
      paymentPhoneCodeSent.value = true;
      return true;
    } catch (e) {
      paymentPhoneError.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isSavingPaymentPhone.value = false;
    }
  }

  Future<bool> updatePaymentPhone({
    required String newPaymentPhone,
    required String provider,
    String? code,
  }) async {
    final userId = StorageService.getUserId();
    if (userId == null) return false;
    isSavingPaymentPhone.value = true;
    paymentPhoneError.value = null;
    try {
      await _userRepo.updateProfile(
        userId: userId,
        paymentPhone: newPaymentPhone.trim(),
        preferredPaymentProvider: provider,
        paymentPhoneCode: code,
      );
      paymentPhone.value = newPaymentPhone.trim();
      preferredPaymentProvider.value = provider;
      paymentPhoneCodeSent.value = false;
      return true;
    } catch (e) {
      paymentPhoneError.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isSavingPaymentPhone.value = false;
    }
  }

  void toggleNotificationSound(bool value) {
    notificationSoundEnabled.value = value;
    StorageService.setNotificationSoundEnabled(value);
  }

  void toggleDataSaver(bool value) {
    dataSaverEnabled.value = value;
    StorageService.setDataSaverEnabled(value);
  }

  Future<void> deleteAccount() async {
    final userId = StorageService.getUserId();
    if (userId == null) return;
    isLoading.value = true;
    try {
      await _userRepo.deleteAccount(userId: userId);
      await PushIdentity.unlink();
      await StorageService.clearAll();
      unawaited(Get.offAllNamed(Routes.login));
    } catch (_) {
      // Ignorer silencieusement en cas d'erreur de réseau ou autre
      await PushIdentity.unlink();
      await StorageService.clearAll();
      unawaited(Get.offAllNamed(Routes.login));
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _loadData() async {
    isLoading.value = true;
    try {
      // Load current user profile for payment phone & provider
      final me = await _authRepo.me();
      paymentPhone.value = me.paymentPhone ?? '';
      if (me.preferredPaymentProvider != null &&
          me.preferredPaymentProvider!.isNotEmpty) {
        preferredPaymentProvider.value = me.preferredPaymentProvider!;
      }

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
      // révoque le token côté serveur, vide le stockage et purge les CacheStore
      await _authRepo.logout();
      // le cache missions/jalons a son propre service
      await _missionRepo.clearCache();
    } finally {
      isLoading.value = false;
      unawaited(Get.offAllNamed(Routes.login));
    }
  }

  final isChangingPhone = false.obs;
  final changePhoneError = Rx<String?>(null);
  final isChangePhoneOtpSent = false.obs;

  Future<bool> requestChangePhone(String newPhone) async {
    isChangingPhone.value = true;
    changePhoneError.value = null;
    try {
      await _authRepo.changePhoneConnected(newPhone: newPhone);
      isChangePhoneOtpSent.value = true;
      return true;
    } catch (e) {
      changePhoneError.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isChangingPhone.value = false;
    }
  }

  Future<bool> confirmChangePhone(String newPhone, String otp) async {
    isChangingPhone.value = true;
    changePhoneError.value = null;
    try {
      final res =
          await _authRepo.changePhoneConnected(newPhone: newPhone, otp: otp);
      if (res['success'] == true) {
        userPhone.value = newPhone;
        StorageService.savePhone(newPhone);
        isChangePhoneOtpSent.value = false;
        return true;
      }
      return false;
    } catch (e) {
      changePhoneError.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isChangingPhone.value = false;
    }
  }
}
