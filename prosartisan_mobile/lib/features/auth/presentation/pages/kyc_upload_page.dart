import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/constants/app_strings.dart';
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
        backgroundColor: Colors.green,
        colorText: Colors.white,
      );

      // Navigate to home
      Get.offAllNamed('/home');
    } else {
      Get.snackbar(
        'Erreur',
        kycController.errorMessage.value,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  void _showImageSourceDialog(bool isIdDocument) {
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text(AppStrings.takePhoto),
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
              leading: const Icon(Icons.photo_library),
              title: const Text(AppStrings.chooseFromGallery),
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
      appBar: AppBar(title: const Text(AppStrings.kycVerification)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24.0),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Info card
                Card(
                  color: Colors.blue.shade50,
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Row(
                      children: [
                        Icon(Icons.info, color: Colors.blue.shade700),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            AppStrings.kycRequired,
                            style: TextStyle(color: Colors.blue.shade700),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 24),

                // ID type selection
                Obx(
                  () => DropdownButtonFormField<String>(
                    initialValue: kycController.idType.value,
                    decoration: InputDecoration(
                      labelText: AppStrings.idType,
                      prefixIcon: const Icon(Icons.badge),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: 'CNI',
                        child: Text(AppStrings.cni),
                      ),
                      DropdownMenuItem(
                        value: 'PASSPORT',
                        child: Text(AppStrings.passport),
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        kycController.setIdType(value);
                      }
                    },
                  ),
                ),

                const SizedBox(height: 16),

                // ID number field
                TextFormField(
                  controller: _idNumberController,
                  decoration: InputDecoration(
                    labelText: AppStrings.idNumber,
                    prefixIcon: const Icon(Icons.numbers),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return AppStrings.idNumberRequired;
                    }
                    return null;
                  },
                ),

                const SizedBox(height: 24),

                // ID document upload
                Text(
                  AppStrings.uploadIdDocument,
                  style: Theme.of(context).textTheme.titleMedium,
                ),

                const SizedBox(height: 12),

                Obx(
                  () => _buildImageUploadCard(
                    image: kycController.idDocument.value,
                    onTap: () => _showImageSourceDialog(true),
                    icon: Icons.credit_card,
                  ),
                ),

                const SizedBox(height: 24),

                // Selfie upload
                Text(
                  AppStrings.uploadSelfie,
                  style: Theme.of(context).textTheme.titleMedium,
                ),

                const SizedBox(height: 12),

                Obx(
                  () => _buildImageUploadCard(
                    image: kycController.selfie.value,
                    onTap: () => _showImageSourceDialog(false),
                    icon: Icons.face,
                  ),
                ),

                const SizedBox(height: 32),

                // Submit button
                Obx(
                  () => ElevatedButton(
                    onPressed: kycController.isLoading.value
                        ? null
                        : _handleSubmit,
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: kycController.isLoading.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                Colors.white,
                              ),
                            ),
                          )
                        : const Text(
                            AppStrings.submit,
                            style: TextStyle(fontSize: 16),
                          ),
                  ),
                ),

                const SizedBox(height: 16),

                // Skip button (optional)
                TextButton(
                  onPressed: () {
                    Get.offAllNamed('/home');
                  },
                  child: const Text(AppStrings.skip),
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
      child: Container(
        height: 200,
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey),
          borderRadius: BorderRadius.circular(12),
          color: Colors.grey.shade100,
        ),
        child: image == null
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, size: 48, color: Colors.grey),
                  const SizedBox(height: 8),
                  Text(
                    'Appuyez pour ajouter une photo',
                    style: TextStyle(color: Colors.grey.shade600),
                  ),
                ],
              )
            : ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.file(image, fit: BoxFit.cover),
              ),
      ),
    );
  }
}
