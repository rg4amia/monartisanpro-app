import 'dart:io';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/jalon_model.dart';
import '../controllers/missions_controller.dart';

/// Écran de soumission de preuves de jalon par l'artisan
/// Permet de capturer des photos/vidéos géolocalisées et de soumettre le jalon
class JalonSubmitScreen extends StatefulWidget {
  const JalonSubmitScreen({super.key});

  @override
  State<JalonSubmitScreen> createState() => _JalonSubmitScreenState();
}

class _JalonSubmitScreenState extends State<JalonSubmitScreen> {
  final ImagePicker _picker = ImagePicker();
  final List<Map<String, dynamic>> _photos = [];
  bool _isCapturing = false;
  bool _isSubmitting = false;

  JalonModel? _jalon;

  @override
  void initState() {
    super.initState();
    _jalon = Get.arguments as JalonModel?;
  }

  /// Affiche le menu pour choisir le type de preuve à ajouter
  void _showPickOptions() {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(20),
            topRight: Radius.circular(20),
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ajouter une preuve de travail',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 20),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: AppColors.primary),
              title: const Text('Prendre une photo'),
              onTap: () {
                Get.back();
                _addProof(isVideo: false, fromCamera: true);
              },
            ),
            ListTile(
              leading:
                  const Icon(Icons.photo_library, color: AppColors.primary),
              title: const Text('Choisir une photo depuis la galerie'),
              onTap: () {
                Get.back();
                _addProof(isVideo: false, fromCamera: false);
              },
            ),
            ListTile(
              leading: const Icon(Icons.videocam, color: AppColors.primary),
              title: const Text('Enregistrer une vidéo'),
              onTap: () {
                Get.back();
                _addProof(isVideo: true, fromCamera: true);
              },
            ),
            ListTile(
              leading:
                  const Icon(Icons.video_library, color: AppColors.primary),
              title: const Text('Choisir une vidéo depuis la galerie'),
              onTap: () {
                Get.back();
                _addProof(isVideo: true, fromCamera: false);
              },
            ),
          ],
        ),
      ),
    );
  }

  /// Capture ou choisit une preuve (photo ou vidéo) avec géolocalisation
  Future<void> _addProof({
    required bool isVideo,
    required bool fromCamera,
  }) async {
    if (_jalon == null) return;

    setState(() => _isCapturing = true);

    try {
      // 1. Vérifier les permissions de localisation
      final locationStatus = await Permission.location.status;
      if (!locationStatus.isGranted) {
        final result = await Permission.location.request();
        if (!result.isGranted) {
          _showError('Permission de localisation refusée');
          setState(() => _isCapturing = false);
          return;
        }
      }

      // 2. Vérifier si le service de localisation est activé
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        _showError(
          'Le service de localisation est désactivé. Veuillez l\'activer dans les paramètres.',
        );
        setState(() => _isCapturing = false);
        return;
      }

      // 3. Obtenir la position actuelle
      final Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );

      // 4. Capturer ou sélectionner le fichier
      XFile? file;
      if (isVideo) {
        file = await _picker.pickVideo(
          source: fromCamera ? ImageSource.camera : ImageSource.gallery,
          maxDuration: const Duration(seconds: 60),
        );
      } else {
        file = await _picker.pickImage(
          source: fromCamera ? ImageSource.camera : ImageSource.gallery,
          imageQuality: 80,
          maxWidth: 1920,
        );
      }

      if (file == null) {
        setState(() => _isCapturing = false);
        return;
      }

      final filePath = file.path;

      // 5. Ajouter la preuve à la liste
      setState(() {
        _photos.add({
          'url': filePath,
          'lat': position.latitude,
          'lng': position.longitude,
          'taken_at': DateTime.now().toIso8601String(),
          'is_video': isVideo,
        });
      });

      Get.snackbar(
        isVideo ? 'Vidéo ajoutée' : 'Photo ajoutée',
        'Preuve géolocalisée enregistrée avec succès',
        snackPosition: SnackPosition.BOTTOM,
        duration: const Duration(seconds: 2),
      );
    } catch (e) {
      _showError('Erreur lors de la capture: $e');
    } finally {
      setState(() => _isCapturing = false);
    }
  }

  /// Soumet le jalon avec les photos (nouvelle soumission)
  Future<void> _submitJalon() async {
    if (_jalon == null) return;

    if (_photos.isEmpty) {
      _showError('Veuillez ajouter au moins une preuve du travail réalisé');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final controller = Get.find<MissionsController>();
      final success = await controller.submitJalon(
        _jalon!.id,
        photos: _photos,
      );

      if (success) {
        Get.back(result: true);
      }
    } catch (e) {
      _showError('Erreur lors de la soumission: $e');
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  /// Envoie des preuves supplémentaires (jalon déjà soumis)
  Future<void> _uploadAdditionalProofs() async {
    if (_jalon == null) return;

    if (_photos.isEmpty) {
      _showError('Veuillez ajouter au moins une nouvelle preuve à envoyer');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final controller = Get.find<MissionsController>();
      final success = await controller.uploadJalonPhotos(
        _jalon!.id,
        _photos,
      );

      if (success) {
        Get.back(result: true);
      }
    } catch (e) {
      _showError('Erreur lors de l\'envoi des preuves: $e');
    } finally {
      setState(() => _isSubmitting = false);
    }
  }

  void _showError(String message) {
    Get.snackbar(
      'Erreur',
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: AppColors.danger,
      colorText: Colors.white,
      duration: const Duration(seconds: 3),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_jalon == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Erreur')),
        body: const Center(
          child: Text('Jalon introuvable'),
        ),
      );
    }

    final isAlreadySubmitted = _jalon!.isSubmitted;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          isAlreadySubmitted ? 'Ajouter des preuves' : 'Soumettre le jalon',
        ),
        elevation: 0,
        backgroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Jalon Info Card
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Center(
                          child: Text(
                            '${_jalon!.ordre}',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Jalon ${_jalon!.ordre}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            Text(
                              Formatters.fcfa(_jalon!.montant),
                              style: const TextStyle(
                                fontSize: 14,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    _jalon!.description,
                    style: const TextStyle(
                      fontSize: 14,
                      color: AppColors.textSecondary,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Instructions
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.info_outline,
                    color: AppColors.primary,
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      isAlreadySubmitted
                          ? 'Vous pouvez envoyer autant de photos ou vidéos de preuve supplémentaires que nécessaire pour justifier les travaux.'
                          : 'Prenez des photos ou vidéos du travail réalisé. La géolocalisation sera automatiquement enregistrée.',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.primary.withValues(alpha: 0.9),
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Preuves Section
            const Text(
              'Preuves du travail',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 12),

            // Photo/Video Grid
            if (_photos.isNotEmpty)
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 3,
                  crossAxisSpacing: 8,
                  mainAxisSpacing: 8,
                ),
                itemCount: _photos.length,
                itemBuilder: (context, index) {
                  final photo = _photos[index];
                  final isVideo = photo['is_video'] == true;
                  final path = photo['url'] as String;

                  return Stack(
                    children: [
                      Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: AppColors.primary.withValues(alpha: 0.2),
                          ),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              isVideo
                                  ? Container(
                                      color: Colors.grey[200],
                                      child: const Center(
                                        child: Icon(
                                          Icons.play_circle_fill,
                                          color: AppColors.primary,
                                          size: 40,
                                        ),
                                      ),
                                    )
                                  : Image.file(
                                      File(path),
                                      fit: BoxFit.cover,
                                      errorBuilder:
                                          (context, error, stackTrace) {
                                        return const Center(
                                          child: Icon(
                                            Icons.broken_image,
                                            color: Colors.grey,
                                            size: 40,
                                          ),
                                        );
                                      },
                                    ),
                            ],
                          ),
                        ),
                      ),
                      Positioned(
                        top: 4,
                        right: 4,
                        child: GestureDetector(
                          onTap: () {
                            setState(() => _photos.removeAt(index));
                          },
                          child: Container(
                            padding: const EdgeInsets.all(4),
                            decoration: const BoxDecoration(
                              color: AppColors.danger,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.close,
                              color: Colors.white,
                              size: 16,
                            ),
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            const SizedBox(height: 16),

            // Add File Button
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _isCapturing ? null : _showPickOptions,
                icon: _isCapturing
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.add_a_photo),
                label: Text(
                  _isCapturing
                      ? 'Capture en cours...'
                      : 'Ajouter une photo ou vidéo',
                ),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  side: BorderSide(
                    color: AppColors.primary.withValues(alpha: 0.3),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 80),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: ElevatedButton(
          onPressed: (_isSubmitting || _photos.isEmpty)
              ? null
              : (isAlreadySubmitted ? _uploadAdditionalProofs : _submitJalon),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            elevation: 0,
            disabledBackgroundColor: AppColors.primary.withValues(alpha: 0.5),
          ),
          child: _isSubmitting
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : Text(
                  isAlreadySubmitted
                      ? 'Envoyer les preuves'
                      : 'Soumettre le jalon',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
        ),
      ),
    );
  }
}
