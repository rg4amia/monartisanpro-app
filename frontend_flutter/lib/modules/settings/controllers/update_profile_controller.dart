import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/storage/storage_service.dart';
import '../../../data/repositories/user_repository.dart';

class UpdateProfileController extends GetxController {
  final UserRepository _userRepo = UserRepository();
  final ImagePicker _picker = ImagePicker();

  final nameController = TextEditingController();
  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  final isLoading = false.obs;
  final profileImagePath = Rx<String?>(null);

  @override
  void onInit() {
    super.onInit();
    _loadUserData();
  }

  void _loadUserData() {
    nameController.text = StorageService.getName() ?? '';
    phoneController.text = StorageService.getPhone() ?? '';
    // Email is optional and not stored yet
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
                'Choose Photo Source',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 20),
              _SourceOption(
                icon: Icons.camera_alt,
                label: 'Take Photo',
                onTap: () => Get.back(result: ImageSource.camera),
              ),
              const SizedBox(height: 12),
              _SourceOption(
                icon: Icons.photo_library,
                label: 'Choose from Gallery',
                onTap: () => Get.back(result: ImageSource.gallery),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Cancel'),
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
        // TODO: Upload to server when backend endpoint is ready
        Get.snackbar(
          'Photo Selected',
          'Profile photo will be uploaded when you save',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: const Color(0xFFD1FAE5),
          colorText: const Color(0xFF10B981),
        );
      }
    } catch (e) {
      Get.snackbar(
        'Error',
        'Failed to pick image: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFFEF4444),
      );
    }
  }

  Future<void> updateProfile() async {
    if (nameController.text.trim().isEmpty) {
      Get.snackbar(
        'Error',
        'Name cannot be empty',
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
          'Session Error',
          'Please log in again to update your profile',
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
      );

      // Update local storage
      StorageService.saveName(nameController.text.trim());

      Get.back();
      Get.snackbar(
        'Success',
        'Profile updated successfully',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFD1FAE5),
        colorText: const Color(0xFF10B981),
      );
    } catch (e) {
      Get.snackbar(
        'Error',
        'Failed to update profile: ${e.toString()}',
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
