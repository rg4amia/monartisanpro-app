import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../controllers/kyc_controller.dart';

/// KYC document upload page
class KycUploadPage extends StatefulWidget {
  final String userId;

  const KycUploadPage({super.key, required this.userId});

  @override
  State<KycUploadPage> createState() => _KycUploadPageState();
}

class _KycUploadPageState extends State<KycUploadPage> {
  final _formKey = GlobalKey<FormState>();
  final _idNumberController = TextEditingController();

  @override
  void dispose() {
    _idNumberController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final kycController = Get.find<KycController>();
    kycController.setIdNumber(_idNumberController.text.trim());

    final success = await kycController.uploadKyc(widget.userId);

    if (success) {
      Get.snackbar(
        AppStrings.kycSubmitted,
        'Vos documents sont en cours de vérification',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentSuccess,
        colorText: AppColors.textPrimary,
      );

      // Navigate to home
      Get.offAllNamed('/home');
    } else {
      Get.snackbar(
        'Erreur',
        kycController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.accentDanger,
        colorText: AppColors.textPrimary,
      );
    }
  }

  void _showImageSourceDialog(bool isIdDocument) {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.cardBg,
      shape: RoundedRectangleBorder(borderRadius: AppRadius.modalRadius),
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: Icon(Icons.camera_alt, color: AppColors.accentPrimary),
              title: Text(
                AppStrings.takePhoto,
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
              onTap: () {
                Navigator.pop(context);
                final kycController = Get.find<KycController>();
                if (isIdDocument) {
                  kycController.pickIdDocumentFromCamera();
                } else {
                  kycController.pickSelfieFromCamera();
                }
              },
            ),
            ListTile(
              leading: Icon(
                Icons.photo_library,
                color: AppColors.accentPrimary,
              ),
              title: Text(
                AppStrings.chooseFromGallery,
                style: AppTypography.body.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
              onTap: () {
                Navigator.pop(context);
                final kycController = Get.find<KycController>();
                if (isIdDocument) {
                  kycController.pickIdDocumentFromGallery();
                } else {
                  kycController.pickSelfieFromGallery();
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final kycController = Get.find<KycController>();

    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          AppStrings.kycVerification,
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Info card
                InfoCard(
                  title: 'Vérification requise',
                  subtitle: AppStrings.kycRequired,
                  icon: Icons.info,
                  backgroundColor: AppColors.accentPrimary.withValues(
                    alpha: 0.1,
                  ),
                  iconColor: AppColors.accentPrimary,
                ),

                SizedBox(height: AppSpacing.lg),

                // ID type selection
                Obx(
                  () => DropdownButtonFormField<String>(
                    initialValue: kycController.idType.value,
                    decoration: InputDecoration(
                      labelText: AppStrings.idType,
                      labelStyle: AppTypography.body.copyWith(
                        color: AppColors.textSecondary,
                      ),
                      prefixIcon: Icon(
                        Icons.badge,
                        color: AppColors.accentPrimary,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: AppRadius.inputRadius,
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: AppRadius.inputRadius,
                        borderSide: BorderSide(color: AppColors.overlayMedium),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: AppRadius.inputRadius,
                        borderSide: BorderSide(color: AppColors.accentPrimary),
                      ),
                      filled: true,
                      fillColor: AppColors.cardBg,
                    ),
                    style: AppTypography.body.copyWith(
                      color: AppColors.textPrimary,
                    ),
                    dropdownColor: AppColors.cardBg,
                    items: [
                      DropdownMenuItem(
                        value: 'CNI',
                        child: Text(
                          AppStrings.cni,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      DropdownMenuItem(
                        value: 'PASSPORT',
                        child: Text(
                          AppStrings.passport,
                          style: AppTypography.body.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        kycController.setIdType(value);
                      }
                    },
                  ),
                ),

                SizedBox(height: AppSpacing.base),

                // ID number field
                TextFormField(
                  controller: _idNumberController,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textPrimary,
                  ),
                  decoration: InputDecoration(
                    labelText: AppStrings.idNumber,
                    labelStyle: AppTypography.body.copyWith(
                      color: AppColors.textSecondary,
                    ),
                    prefixIcon: Icon(
                      Icons.numbers,
                      color: AppColors.accentPrimary,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: AppRadius.inputRadius,
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: AppRadius.inputRadius,
                      borderSide: BorderSide(color: AppColors.overlayMedium),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: AppRadius.inputRadius,
                      borderSide: BorderSide(color: AppColors.accentPrimary),
                    ),
                    errorBorder: OutlineInputBorder(
                      borderRadius: AppRadius.inputRadius,
                      borderSide: BorderSide(color: AppColors.accentDanger),
                    ),
                    filled: true,
                    fillColor: AppColors.cardBg,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.idNumberRequired;
                    }
                    return null;
                  },
                ),

                SizedBox(height: AppSpacing.lg),

                // ID document upload
                Text(
                  AppStrings.uploadIdDocument,
                  style: AppTypography.sectionTitle.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),

                SizedBox(height: AppSpacing.md),

                Obx(
                  () => _buildImageUploadCard(
                    image: kycController.idDocument.value,
                    onTap: () => _showImageSourceDialog(true),
                    icon: Icons.credit_card,
                  ),
                ),

                SizedBox(height: AppSpacing.lg),

                // Selfie upload
                Text(
                  AppStrings.uploadSelfie,
                  style: AppTypography.sectionTitle.copyWith(
                    color: AppColors.textPrimary,
                  ),
                ),

                SizedBox(height: AppSpacing.md),

                Obx(
                  () => _buildImageUploadCard(
                    image: kycController.selfie.value,
                    onTap: () => _showImageSourceDialog(false),
                    icon: Icons.face,
                  ),
                ),

                SizedBox(height: AppSpacing.xl),

                // Submit button
                Obx(
                  () => PrimaryButton(
                    text: AppStrings.submit,
                    onPressed: kycController.isLoading.value
                        ? null
                        : _handleSubmit,
                    isLoading: kycController.isLoading.value,
                  ),
                ),

                SizedBox(height: AppSpacing.base),

                // Skip button (optional)
                Material(
                  color: Colors.transparent,
                  child: InkWell(
                    onTap: () {
                      Get.offAllNamed('/home');
                    },
                    borderRadius: AppRadius.buttonRadius,
                    child: Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: AppSpacing.base,
                        vertical: AppSpacing.md,
                      ),
                      child: Center(
                        child: Text(
                          AppStrings.skip,
                          style: AppTypography.button.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildImageUploadCard({
    required dynamic image,
    required VoidCallback onTap,
    required IconData icon,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: AppRadius.cardRadius,
      child: Container(
        height: 200,
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.overlayMedium),
          borderRadius: AppRadius.cardRadius,
          color: AppColors.cardBg,
        ),
        child: image == null
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, size: 48, color: AppColors.textSecondary),
                  SizedBox(height: AppSpacing.sm),
                  Text(
                    'Appuyez pour ajouter une photo',
                    style: AppTypography.body.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              )
            : ClipRRect(
                borderRadius: AppRadius.cardRadius,
                child: Image.file(image, fit: BoxFit.cover),
              ),
      ),
    );
  }
}
