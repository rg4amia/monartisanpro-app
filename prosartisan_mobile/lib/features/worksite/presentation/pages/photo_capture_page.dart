import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../core/theme/app_radius.dart';
import '../../../../shared/widgets/buttons/primary_button.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../controllers/worksite_controller.dart';

/// Photo capture screen with GPS embedding
///
/// Allows artisans to capture photos with GPS coordinates for milestone proofs
/// Requirements: 6.2, 14.7
class PhotoCapturePage extends StatefulWidget {
  final String jalonId;
  final String jalonDescription;

  const PhotoCapturePage({
    super.key,
    required this.jalonId,
    required this.jalonDescription,
  });

  @override
  State<PhotoCapturePage> createState() => _PhotoCapturePageState();
}

class _PhotoCapturePageState extends State<PhotoCapturePage> {
  final ImagePicker _picker = ImagePicker();
  final WorksiteController _controller = Get.find<WorksiteController>();

  File? _capturedImage;
  Position? _currentPosition;
  bool _isCapturing = false;
  bool _isSubmitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.primaryBg,
      appBar: AppBar(
        title: Text(
          'Preuve de Livraison',
          style: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        ),
        backgroundColor: AppColors.accentPrimary,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Padding(
        padding: EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildJalonInfo(context),
            SizedBox(height: AppSpacing.lg),
            _buildLocationInfo(context),
            SizedBox(height: AppSpacing.lg),
            _buildCameraSection(context),
            const Spacer(),
            _buildActionButtons(context),
          ],
        ),
      ),
    );
  }

  Widget _buildJalonInfo(BuildContext context) {
    return InfoCard(
      title: 'Jalon à valider',
      subtitle: widget.jalonDescription,
      icon: Icons.assignment,
      backgroundColor: AppColors.accentPrimary.withValues(alpha: 0.1),
      iconColor: AppColors.accentPrimary,
    );
  }

  Widget _buildLocationInfo(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.overlayMedium),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                _currentPosition != null
                    ? Icons.location_on
                    : Icons.location_off,
                color: _currentPosition != null
                    ? AppColors.accentSuccess
                    : AppColors.accentDanger,
              ),
              SizedBox(width: AppSpacing.sm),
              Text(
                'Localisation GPS',
                style: AppTypography.sectionTitle.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          SizedBox(height: AppSpacing.sm),
          if (_currentPosition != null) ...[
            Text(
              'Latitude: ${_currentPosition!.latitude.toStringAsFixed(6)}',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
                fontFamily: 'monospace',
              ),
            ),
            Text(
              'Longitude: ${_currentPosition!.longitude.toStringAsFixed(6)}',
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textSecondary,
                fontFamily: 'monospace',
              ),
            ),
            Text(
              'Précision: ${_currentPosition!.accuracy.toStringAsFixed(1)}m',
              style: AppTypography.bodySmall.copyWith(
                color: _currentPosition!.accuracy <= 10
                    ? AppColors.accentSuccess
                    : AppColors.accentWarning,
              ),
            ),
            if (_currentPosition!.accuracy > 10)
              Text(
                'Attention: Précision GPS faible (>10m)',
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.accentWarning,
                ),
              ),
          ] else ...[
            Text(
              'Localisation non disponible',
              style: AppTypography.body.copyWith(color: AppColors.accentDanger),
            ),
            SizedBox(height: AppSpacing.sm),
            Container(
              width: double.infinity,
              height: 40,
              child: OutlinedButton.icon(
                onPressed: _getCurrentLocation,
                icon: Icon(Icons.refresh),
                label: Text('Réessayer'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.accentPrimary,
                  side: BorderSide(color: AppColors.accentPrimary),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCameraSection(BuildContext context) {
    return Expanded(
      child: Container(
        padding: EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(color: AppColors.overlayMedium),
        ),
        child: Column(
          children: [
            Text(
              'Photo de preuve',
              style: AppTypography.sectionTitle.copyWith(
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: AppSpacing.md),
            Expanded(
              child: _capturedImage != null
                  ? _buildImagePreview()
                  : _buildCameraPlaceholder(context),
            ),
            SizedBox(height: AppSpacing.md),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                Expanded(
                  child: Container(
                    height: 48,
                    margin: EdgeInsets.only(right: AppSpacing.sm),
                    child: OutlinedButton.icon(
                      onPressed: _isCapturing
                          ? null
                          : () => _capturePhoto(ImageSource.camera),
                      icon: _isCapturing
                          ? SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.accentPrimary,
                              ),
                            )
                          : Icon(Icons.camera_alt),
                      label: Text('Appareil photo'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.accentPrimary,
                        side: BorderSide(color: AppColors.accentPrimary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                      ),
                    ),
                  ),
                ),
                Expanded(
                  child: Container(
                    height: 48,
                    margin: EdgeInsets.only(left: AppSpacing.sm),
                    child: OutlinedButton.icon(
                      onPressed: _isCapturing
                          ? null
                          : () => _capturePhoto(ImageSource.gallery),
                      icon: Icon(Icons.photo_library),
                      label: Text('Galerie'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.accentPrimary,
                        side: BorderSide(color: AppColors.accentPrimary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildImagePreview() {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.overlayMedium),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Image.file(_capturedImage!, fit: BoxFit.contain),
      ),
    );
  }

  Widget _buildCameraPlaceholder(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border, style: BorderStyle.solid),
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        color: AppColors.background,
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.camera_alt, size: 64, color: AppColors.textSecondary),
          SizedBox(height: AppSpacing.md),
          Text(
            'Aucune photo capturée',
            style: AppTypography.bodyLarge.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          SizedBox(height: AppSpacing.sm),
          Text(
            'Prenez une photo pour prouver l\'avancement du travail',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondary,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildActionButtons(BuildContext context) {
    return Column(
      children: [
        if (_error != null) ...[
          InfoCard(
            title: 'Erreur',
            subtitle: _error!,
            icon: Icons.error,
            backgroundColor: AppColors.error.withValues(alpha: 0.1),
            borderColor: AppColors.error,
          ),
          SizedBox(height: AppSpacing.md),
        ],
        PrimaryButton(
          onPressed: _canSubmit() ? _submitProof : null,
          text: _isSubmitting ? 'Envoi en cours...' : 'Soumettre la preuve',
          icon: Icons.send,
          isLoading: _isSubmitting,
          isFullWidth: true,
        ),
      ],
    );
  }

  Future<void> _getCurrentLocation() async {
    try {
      setState(() {
        _error = null;
      });

      // Check if location services are enabled
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        setState(() {
          _error = 'Les services de localisation sont désactivés';
        });
        return;
      }

      // Check location permissions
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          setState(() {
            _error = 'Permission de localisation refusée';
          });
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        setState(() {
          _error = 'Permission de localisation refusée définitivement';
        });
        return;
      }

      // Get current position
      Position position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );

      setState(() {
        _currentPosition = position;
      });
    } catch (e) {
      setState(() {
        _error = 'Erreur lors de la récupération de la localisation: $e';
      });
    }
  }

  Future<void> _capturePhoto(ImageSource source) async {
    try {
      setState(() {
        _isCapturing = true;
        _error = null;
      });

      final XFile? image = await _picker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1920,
        maxHeight: 1080,
      );

      if (image != null) {
        setState(() {
          _capturedImage = File(image.path);
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Erreur lors de la capture de la photo: $e';
      });
    } finally {
      setState(() {
        _isCapturing = false;
      });
    }
  }

  Future<void> _submitProof() async {
    if (!_canSubmit()) return;

    setState(() {
      _isSubmitting = true;
      _error = null;
    });

    try {
      final success = await _controller.submitProof(
        jalonId: widget.jalonId,
        photo: _capturedImage!,
        latitude: _currentPosition!.latitude,
        longitude: _currentPosition!.longitude,
        accuracy: _currentPosition!.accuracy,
        capturedAt: DateTime.now(),
        exifData: {
          'timestamp': DateTime.now().toIso8601String(),
          'accuracy': _currentPosition!.accuracy,
          'altitude': _currentPosition!.altitude,
          'heading': _currentPosition!.heading,
          'speed': _currentPosition!.speed,
        },
      );

      if (success) {
        Get.back(result: true);
      }
    } catch (e) {
      setState(() {
        _error = 'Erreur lors de l\'envoi: $e';
      });
    } finally {
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  bool _canSubmit() {
    return _capturedImage != null &&
        _currentPosition != null &&
        !_isSubmitting &&
        _currentPosition!.accuracy <=
            10; // Requirement 10.4: GPS accuracy < 10m
  }
}
