import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/auth_controller.dart';

class KycScreen extends StatelessWidget {
  const KycScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<AuthController>();
    final picker = ImagePicker();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Vérification KYC')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 20),
              // Progress indicator
              Obx(() => _ProgressSteps(currentStep: c.kycStep.value)),
              const SizedBox(height: 28),

              // Name
              const Text('Nom complet',
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary)),
              const SizedBox(height: 8),
              TextFormField(
                onChanged: (v) => c.name.value = v,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  hintText: 'Prénom et nom',
                  prefixIcon:
                      Icon(Icons.person_outline, color: AppColors.textMuted),
                ),
              ),
              const SizedBox(height: 24),

              // CNI upload
              const Text('Pièce d\'identité (CNI)',
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary)),
              const SizedBox(height: 8),
              Obx(() => _UploadTile(
                    label: 'Photo de la CNI',
                    icon: Icons.badge_outlined,
                    filePath: c.cniPath.value,
                    onTap: () async {
                      final img = await picker.pickImage(
                          source: ImageSource.camera,
                          imageQuality: 85);
                      if (img != null) {
                        c.cniPath.value = img.path;
                        c.kycStep.value = 1;
                      }
                    },
                  )),
              const SizedBox(height: 16),

              // Selfie upload
              const Text('Selfie avec liveness',
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary)),
              const SizedBox(height: 8),
              Obx(() => _UploadTile(
                    label: 'Prendre un selfie',
                    icon: Icons.face_outlined,
                    filePath: c.selfiePath.value,
                    onTap: () async {
                      final img = await picker.pickImage(
                          source: ImageSource.camera,
                          preferredCameraDevice: CameraDevice.front,
                          imageQuality: 85);
                      if (img != null) {
                        c.selfiePath.value = img.path;
                        c.kycStep.value = 2;
                      }
                    },
                  )),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.info.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.info_outline, color: AppColors.info, size: 16),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Vos documents sont chiffrés et utilisés uniquement pour la vérification.',
                        style: TextStyle(fontSize: 12, color: AppColors.info),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),

              Obx(() {
                if (c.errorMsg.value != null) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(c.errorMsg.value!,
                        style: const TextStyle(
                            color: AppColors.danger, fontSize: 13)),
                  );
                }
                return const SizedBox.shrink();
              }),

              Obx(() => ElevatedButton(
                    onPressed: c.isLoading.value
                        ? null
                        : (c.name.value.trim().isNotEmpty
                            ? c.register
                            : null),
                    child: c.isLoading.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Finaliser l\'inscription'),
                  )),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProgressSteps extends StatelessWidget {
  final int currentStep;

  const _ProgressSteps({required this.currentStep});

  @override
  Widget build(BuildContext context) {
    const steps = ['Identité', 'CNI', 'Selfie', 'Vérification', 'Activé'];
    return Column(
      children: [
        Row(
          children: List.generate(steps.length * 2 - 1, (i) {
            if (i.isOdd) {
              return Expanded(
                child: Container(
                  height: 2,
                  color: i ~/ 2 < currentStep
                      ? AppColors.success
                      : AppColors.border,
                ),
              );
            }
            final step = i ~/ 2;
            final done = step < currentStep;
            final active = step == currentStep;
            return Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: done
                    ? AppColors.success
                    : active
                        ? AppColors.primary
                        : AppColors.border,
              ),
              child: Center(
                child: done
                    ? const Icon(Icons.check, size: 14, color: Colors.white)
                    : Text('${step + 1}',
                        style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: active
                                ? Colors.white
                                : AppColors.textSecondary)),
              ),
            );
          }),
        ),
      ],
    );
  }
}

class _UploadTile extends StatelessWidget {
  final String label;
  final IconData icon;
  final String? filePath;
  final VoidCallback onTap;

  const _UploadTile({
    required this.label,
    required this.icon,
    required this.filePath,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final done = filePath != null;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: done
              ? AppColors.success.withValues(alpha: 0.08)
              : AppColors.card,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
              color: done ? AppColors.success : AppColors.border, width: 1.5),
        ),
        child: Row(
          children: [
            Icon(done ? Icons.check_circle : icon,
                color: done ? AppColors.success : AppColors.textMuted),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                done ? 'Document ajouté ✓' : label,
                style: TextStyle(
                    fontSize: 14,
                    color: done ? AppColors.success : AppColors.textSecondary,
                    fontWeight:
                        done ? FontWeight.w600 : FontWeight.normal),
              ),
            ),
            Icon(
              done ? Icons.edit_outlined : Icons.add_photo_alternate_outlined,
              color: done ? AppColors.success : AppColors.primary,
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}
