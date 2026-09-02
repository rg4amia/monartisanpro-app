import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/sector_model.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../home/controllers/home_controller.dart';

class UpdateProfileController extends GetxController {
  final UserRepository _userRepo = UserRepository();
  final AuthRepository _authRepo = AuthRepository();
  final ImagePicker _picker = ImagePicker();

  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  final isLoading = false.obs;
  final isProfileLoading = false.obs;
  final isArtisan = false.obs;
  final isProActor = false.obs;
  final nightInterventionsEnabled = false.obs;
  final profileImagePath = Rx<String?>(null);

  // CNMCI
  final cnmciNumberController = TextEditingController();
  final cnmciStatus = 'non_renseigne'.obs;
  final cnmciCardUrl = Rx<String?>(null);
  final cnmciCardImagePath = Rx<String?>(null);

  // Mobile Money pour reversement des gains (Artisans, Livreurs, Fournisseurs)
  final paymentPhoneController = TextEditingController();
  final selectedPaymentProvider = 'wave'.obs;

  // Localisation
  final selectedLatitude = Rxn<double>();
  final selectedLongitude = Rxn<double>();
  final selectedAddress = 'Aucun emplacement défini'.obs;
  final canEditLocation = false.obs;

  // Catégorie / Sous-catégorie
  final selectedSectorId = Rxn<int>();
  final selectedTradeId = Rxn<int>();
  final selectedSectorName = Rxn<String>();
  final selectedTradeName = Rxn<String>();

  @override
  void onInit() {
    super.onInit();
    _loadUserData();
    _loadCurrentProfile();
  }

  void _loadUserData() {
    nameController.text = StorageService.getName() ?? '';
    phoneController.text = StorageService.getPhone() ?? '';
    final role = StorageService.getRole();
    isArtisan.value = role == 'artisan';
    isProActor.value = role == 'artisan' ||
        role == 'fournisseur' ||
        role == 'driver' ||
        role == 'livreur' ||
        role == 'LIVREUR';
    canEditLocation.value = isProActor.value;
  }

  Future<void> _loadCurrentProfile() async {
    isProfileLoading.value = true;

    try {
      final user = await _authRepo.me();

      if ((user.name ?? '').trim().isNotEmpty) {
        nameController.text = user.name!.trim();
      }

      isArtisan.value = user.role == 'artisan';
      isProActor.value = user.role == 'artisan' ||
          user.role == 'fournisseur' ||
          user.role == 'driver' ||
          user.role == 'livreur' ||
          user.role == 'LIVREUR';
      canEditLocation.value = isProActor.value;

      if (user.lat != null && user.lng != null) {
        selectedLatitude.value = user.lat;
        selectedLongitude.value = user.lng;
        selectedAddress.value =
            'Position configurée (${user.lat!.toStringAsFixed(4)}, ${user.lng!.toStringAsFixed(4)})';
      }

      nightInterventionsEnabled.value = user.nightInterventionAvailable;

      selectedSectorId.value = user.sectorId;
      selectedTradeId.value = user.tradeId;
      selectedSectorName.value = user.sectorName;
      selectedTradeName.value = user.tradeName;

      cnmciNumberController.text = user.cnmciNumber ?? '';
      cnmciStatus.value = user.cnmciStatus;
      cnmciCardUrl.value = user.cnmciCardUrl;

      paymentPhoneController.text = user.paymentPhone ?? '';
      if (user.preferredPaymentProvider != null &&
          user.preferredPaymentProvider!.isNotEmpty) {
        selectedPaymentProvider.value = user.preferredPaymentProvider!;
      }

      StorageService.saveRole(user.role);
      StorageService.saveName(user.name ?? nameController.text.trim());
      StorageService.saveKycStatus(user.kycStatus);
    } catch (_) {
      // Conserver les valeurs locales si l'API n'est pas joignable.
    } finally {
      isProfileLoading.value = false;
    }
  }

  Future<void> selectLocationOnMap() async {
    final result = await Get.toNamed(Routes.locationPicker);
    if (result != null && result is Map) {
      selectedLatitude.value = result['latitude'] as double?;
      selectedLongitude.value = result['longitude'] as double?;
      selectedAddress.value =
          result['address'] as String? ?? 'Position sélectionnée';
    }
  }

  Future<void> pickProfileImage() async {
    try {
      // Show bottom sheet to choose camera or gallery
      final source = await Get.bottomSheet<ImageSource>(
        Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFE5E7EB),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Choisir la source de la photo',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 20),
              _SourceOption(
                icon: Icons.camera_alt,
                label: 'Prendre une photo',
                onTap: () => Get.back(result: ImageSource.camera),
              ),
              const SizedBox(height: 12),
              _SourceOption(
                icon: Icons.photo_library,
                label: 'Choisir depuis la galerie',
                onTap: () => Get.back(result: ImageSource.gallery),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Annuler'),
              ),
            ],
          ),
        ),
        isDismissible: true,
        enableDrag: true,
      );

      if (source == null) return;

      final image = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1024,
        maxHeight: 1024,
      );

      if (image != null) {
        profileImagePath.value = image.path;
        Get.snackbar(
          'Photo sélectionnée',
          'La photo de profil sera téléchargée lors de l\'enregistrement',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: const Color(0xFFD1FAE5),
          colorText: const Color(0xFF10B981),
        );
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Échec du choix de l\'image : ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFFEF4444),
      );
    }
  }

  Future<void> pickCnmciCardImage() async {
    try {
      final source = await Get.bottomSheet<ImageSource>(
        Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFE5E7EB),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Choisir la photo de la carte CNMCI',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 20),
              _SourceOption(
                icon: Icons.camera_alt,
                label: 'Prendre une photo',
                onTap: () => Get.back(result: ImageSource.camera),
              ),
              const SizedBox(height: 12),
              _SourceOption(
                icon: Icons.photo_library,
                label: 'Choisir depuis la galerie',
                onTap: () => Get.back(result: ImageSource.gallery),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Annuler'),
              ),
            ],
          ),
        ),
        isDismissible: true,
        enableDrag: true,
      );

      if (source == null) return;

      final image = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1024,
        maxHeight: 1024,
      );

      if (image != null) {
        cnmciCardImagePath.value = image.path;
        Get.snackbar(
          'Carte CNMCI sélectionnée',
          'La photo de votre carte sera téléchargée lors de l\'enregistrement',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: const Color(0xFFD1FAE5),
          colorText: const Color(0xFF10B981),
        );
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Échec du choix de l\'image : ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFFEF4444),
      );
    }
  }

  Future<void> selectCategoryAndSubcategory() async {
    final result = await Get.toNamed(Routes.services);
    if (result != null && result is Map) {
      final sector = result['sector'] as SectorModel?;
      final trade = result['trade'] as TradeModel?;
      if (sector != null && trade != null) {
        selectedSectorId.value = sector.id;
        selectedTradeId.value = trade.id;
        selectedSectorName.value = sector.name;
        selectedTradeName.value = trade.name;
      }
    }
  }

  Future<void> updateProfile() async {
    if (nameController.text.trim().isEmpty) {
      Get.snackbar(
        'Erreur',
        'Le nom ne peut pas être vide',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFFEF4444),
      );
      return;
    }

    isLoading.value = true;
    try {
      final userId = StorageService.getUserId();
      if (userId == null) {
        // User session is invalid, show friendly error
        Get.snackbar(
          'Erreur de session',
          'Veuillez vous reconnecter pour mettre à jour votre profil',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: const Color(0xFFFEE2E2),
          colorText: const Color(0xFFEF4444),
          duration: const Duration(seconds: 3),
        );
        return;
      }

      await _userRepo.updateProfile(
        userId: userId,
        name: nameController.text.trim(),
        nightInterventionAvailable:
            isArtisan.value ? nightInterventionsEnabled.value : null,
        sectorId: isArtisan.value ? selectedSectorId.value : null,
        tradeId: isArtisan.value ? selectedTradeId.value : null,
        paymentPhone: paymentPhoneController.text.trim().isNotEmpty
            ? paymentPhoneController.text.trim()
            : null,
        preferredPaymentProvider: selectedPaymentProvider.value,
      );

      if (isArtisan.value &&
          (cnmciNumberController.text.isNotEmpty ||
              cnmciCardImagePath.value != null)) {
        await _userRepo.updateCnmci(
          userId: userId,
          cnmciNumber: cnmciNumberController.text.trim(),
          cardImagePath: cnmciCardImagePath.value,
        );
      }

      // Si une localisation a été spécifiée sur la carte, on la met à jour
      if (selectedLatitude.value != null && selectedLongitude.value != null) {
        await _userRepo.updateLocation(
          userId: userId,
          lat: selectedLatitude.value!,
          lng: selectedLongitude.value!,
        );
      }

      // Update local storage
      StorageService.saveName(nameController.text.trim());

      // Reload profile to synchronize local model coordinates & details
      await _loadCurrentProfile();

      try {
        final homeCtrl = Get.find<HomeController>();
        homeCtrl.userName.value = nameController.text.trim();
        // Trigger reload of nearby artisans or self position
        if (selectedLatitude.value != null && selectedLongitude.value != null) {
          homeCtrl.refreshLocationAndArtisans(
            selectedLatitude.value!,
            selectedLongitude.value!,
          );
        }
      } catch (_) {}

      Get.back();
      Get.snackbar(
        'Succès',
        'Profil mis à jour avec succès',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFD1FAE5),
        colorText: const Color(0xFF10B981),
      );
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Échec de la mise à jour du profil : ${ErrorHandler.getErrorMessage(e)}',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFFEF4444),
      );
    } finally {
      isLoading.value = false;
    }
  }

  @override
  void onClose() {
    nameController.dispose();
    phoneController.dispose();
    emailController.dispose();
    cnmciNumberController.dispose();
    paymentPhoneController.dispose();
    super.onClose();
  }
}

// ─── Source Option Widget ─────────────────────────────────────────────────────
class _SourceOption extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _SourceOption({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          border: Border.all(color: const Color(0xFFE5E7EB)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: const Color(0xFFEEF2FF),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: const Color(0xFF4F46E5)),
            ),
            const SizedBox(width: 16),
            Text(
              label,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: Color(0xFF111827),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
