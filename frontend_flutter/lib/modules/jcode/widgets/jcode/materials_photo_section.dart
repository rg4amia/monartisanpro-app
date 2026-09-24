import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/jcode_model.dart';
import '../../controllers/jcode_controller.dart';

/// Permet à l'artisan de prendre une photo géolocalisée des matériaux
/// reçus sur chantier une fois le J-Code livré par le fournisseur, et de
/// l'envoyer au backend pour notifier le client (Phase 3 du flux métier).
class MaterialsPhotoSection extends StatefulWidget {
  final JcodeModel jcode;

  const MaterialsPhotoSection({super.key, required this.jcode});

  @override
  State<MaterialsPhotoSection> createState() => _MaterialsPhotoSectionState();
}

class _MaterialsPhotoSectionState extends State<MaterialsPhotoSection> {
  final JcodeController controller = Get.find<JcodeController>();
  final ImagePicker _picker = ImagePicker();
  bool _isCapturing = false;

  Future<void> _captureAndUpload() async {
    setState(() => _isCapturing = true);

    String? photoPath;
    double? latitude;
    double? longitude;

    try {
      final photo = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 80,
        maxWidth: 1920,
      );
      if (photo == null) {
        return;
      }
      photoPath = photo.path;

      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final requested = await Geolocator.requestPermission();
        if (requested == LocationPermission.denied ||
            requested == LocationPermission.deniedForever) {
          throw Exception('Autorisation GPS refusée.');
        }
      }

      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        throw Exception(
          'Le service de localisation est désactivé. Veuillez l\'activer dans les paramètres.',
        );
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          // Borne obligatoire : sans elle, un terminal qui ne fixe pas de
          // point GPS bloquerait cet écran indéfiniment sur « Localisation... ».
          timeLimit: Duration(seconds: 10),
        ),
      );
      latitude = position.latitude;
      longitude = position.longitude;
    } catch (e) {
      Get.snackbar(
        'Erreur GPS',
        e.toString().replaceAll('Exception:', '').trim(),
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return;
    } finally {
      if (mounted) setState(() => _isCapturing = false);
    }

    await controller.uploadPhotoMateriaux(
      jcode: widget.jcode,
      photoPath: photoPath,
      latitude: latitude,
      longitude: longitude,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final alreadySent =
          controller.photoMateriauxUploadedId.value == widget.jcode.id;
      final isUploading = controller.isUploadingPhotoMateriaux.value;
      final isBusy = _isCapturing || isUploading;

      if (alreadySent) {
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.success.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Row(
            children: [
              Icon(Icons.check_circle, color: AppColors.success),
              SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Photo des matériaux envoyée. Le client a été notifié.',
                  style: TextStyle(
                    color: AppColors.success,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        );
      }

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.06),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline, color: AppColors.primary, size: 20),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Prenez une photo géolocalisée des matériaux reçus sur le chantier pour notifier le client.',
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                      height: 1.4,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          ElevatedButton.icon(
            onPressed: isBusy ? null : _captureAndUpload,
            icon: isBusy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.add_a_photo),
            label: Text(
              _isCapturing
                  ? 'Localisation...'
                  : isUploading
                      ? 'Envoi en cours...'
                      : 'Ajouter la photo des matériaux sur chantier',
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      );
    });
  }
}
