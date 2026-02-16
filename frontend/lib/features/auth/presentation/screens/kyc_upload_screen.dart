import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/kyc_controller.dart';
import 'kyc_status_screen.dart';

class KycUploadScreen extends StatefulWidget {
  const KycUploadScreen({super.key});

  @override
  State<KycUploadScreen> createState() => _KycUploadScreenState();
}

class _KycUploadScreenState extends State<KycUploadScreen> {
  final _kycController = Get.put(KycController());
  final _imagePicker = ImagePicker();

  final List<DocumentTypeOption> _documentTypes = [
    DocumentTypeOption(
      type: 'cni',
      label: 'Carte Nationale d\'Identité (CNI)',
      icon: Icons.credit_card,
    ),
    DocumentTypeOption(
      type: 'passport',
      label: 'Passeport',
      icon: Icons.book_outlined,
    ),
    DocumentTypeOption(
      type: 'attestation',
      label: 'Attestation d\'Identité',
      icon: Icons.description_outlined,
    ),
  ];

  Future<void> _pickDocument() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (image != null) {
        _kycController.setDocumentPath(image.path);
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner l\'image: $e',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _takeSelfie() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
        preferredCameraDevice: CameraDevice.front,
      );

      if (image != null) {
        _kycController.setSelfiePath(image.path);
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de prendre la photo: $e',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _handleUpload() async {
    final success = await _kycController.uploadDocuments();

    if (success) {
      Get.snackbar(
        'Succès',
        'Documents envoyés pour vérification!',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      // Navigate to KYC status screen
      Get.off(() => const KycStatusScreen());
    } else {
      Get.snackbar(
        'Erreur',
        _kycController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Vérification d\'identité'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Info Banner
              Container(
                padding: const EdgeInsets.all(Spacing.base),
                decoration: BoxDecoration(
                  color: AppColors.info.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  border: Border.all(
                    color: AppColors.info.withValues(alpha: 0.3),
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.info_outline,
                      color: AppColors.info,
                      size: 24,
                    ),
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Text(
                        'Nous avons besoin de vérifier votre identité pour assurer la sécurité de la plateforme.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: Spacing.xl),

              // Document Type Selection
              Text(
                'Type de document',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: Spacing.md),

              Obx(() => Column(
                    children: _documentTypes
                        .map((docType) => _buildDocumentTypeCard(docType))
                        .toList(),
                  )),
              const SizedBox(height: Spacing.xl),

              // Document Upload
              Text(
                'Photo du document',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: Spacing.md),

              Obx(() => _kycController.documentPath.value.isEmpty
                  ? _buildUploadButton(
                      icon: Icons.upload_file_outlined,
                      label: 'Sélectionner le document',
                      onTap: _pickDocument,
                    )
                  : _buildImagePreview(
                      _kycController.documentPath.value,
                      onRemove: () => _kycController.setDocumentPath(''),
                    )),
              const SizedBox(height: Spacing.xl),

              // Selfie Upload
              Text(
                'Photo de vous (Selfie)',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                'Optionnel mais recommandé pour une vérification rapide',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
              ),
              const SizedBox(height: Spacing.md),

              Obx(() => _kycController.selfiePath.value.isEmpty
                  ? _buildUploadButton(
                      icon: Icons.camera_alt_outlined,
                      label: 'Prendre un selfie',
                      onTap: _takeSelfie,
                    )
                  : _buildImagePreview(
                      _kycController.selfiePath.value,
                      onRemove: () => _kycController.setSelfiePath(''),
                    )),
              const SizedBox(height: Spacing.xxxl),

              // Submit Button
              Obx(() => ElevatedButton(
                    onPressed: _kycController.isUploading.value ||
                            _kycController.documentPath.value.isEmpty
                        ? null
                        : _handleUpload,
                    child: _kycController.isUploading.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor:
                                  AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text('Soumettre pour vérification'),
                  )),
              const SizedBox(height: Spacing.md),

              // Privacy Note
              Text(
                'Vos documents sont chiffrés et stockés de manière sécurisée. Ils ne seront utilisés que pour la vérification de votre identité.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.lightTextSecondary,
                      fontStyle: FontStyle.italic,
                    ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDocumentTypeCard(DocumentTypeOption docType) {
    final isSelected = _kycController.documentType.value == docType.type;

    return Padding(
      padding: const EdgeInsets.only(bottom: Spacing.md),
      child: InkWell(
        onTap: () => _kycController.setDocumentType(docType.type),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        child: Container(
          padding: const EdgeInsets.all(Spacing.base),
          decoration: BoxDecoration(
            color: isSelected
                ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
                : null,
            borderRadius: BorderRadius.circular(Spacing.radiusMd),
            border: Border.all(
              color: isSelected
                  ? Theme.of(context).colorScheme.primary
                  : AppColors.lightTextTertiary,
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(
                docType.icon,
                color: isSelected
                    ? Theme.of(context).colorScheme.primary
                    : AppColors.lightTextSecondary,
                size: 28,
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Text(
                  docType.label,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: isSelected
                            ? Theme.of(context).colorScheme.primary
                            : null,
                        fontWeight: isSelected ? FontWeight.w600 : null,
                      ),
                ),
              ),
              if (isSelected)
                Icon(
                  Icons.check_circle,
                  color: Theme.of(context).colorScheme.primary,
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildUploadButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Spacing.radiusMd),
      child: Container(
        padding: const EdgeInsets.all(Spacing.xl),
        decoration: BoxDecoration(
          color: AppColors.lightBackground,
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          border: Border.all(
            color: AppColors.lightTextTertiary,
            width: 2,
            style: BorderStyle.solid,
          ),
        ),
        child: Column(
          children: [
            Icon(
              icon,
              size: 48,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: Spacing.md),
            Text(
              label,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildImagePreview(String imagePath, {required VoidCallback onRemove}) {
    return Stack(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          child: Image.file(
            File(imagePath),
            height: 200,
            width: double.infinity,
            fit: BoxFit.cover,
          ),
        ),
        Positioned(
          top: Spacing.sm,
          right: Spacing.sm,
          child: IconButton(
            onPressed: onRemove,
            icon: const Icon(Icons.close),
            style: IconButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
          ),
        ),
      ],
    );
  }
}

class DocumentTypeOption {
  final String type;
  final String label;
  final IconData icon;

  DocumentTypeOption({
    required this.type,
    required this.label,
    required this.icon,
  });
}
