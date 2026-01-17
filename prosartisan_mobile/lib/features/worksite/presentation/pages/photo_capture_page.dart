import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:prosartisan_mobile/features/worksite/presentation/controllers/worksite_controller.dart';

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
      appBar: AppBar(
        title: const Text('Preuve de Livraison'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildJalonInfo(context),
            const SizedBox(height: 24),
            _buildLocationInfo(context),
            const SizedBox(height: 24),
            _buildCameraSection(context),
            const Spacer(),
            _buildActionButtons(context),
          ],
        ),
      ),
    );
  }

  Widget _buildJalonInfo(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Jalon à valider',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              widget.jalonDescription,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationInfo(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  _currentPosition != null
                      ? Icons.location_on
                      : Icons.location_off,
                  color: _currentPosition != null ? Colors.green : Colors.red,
                ),
                const SizedBox(width: 8),
                Text(
                  'Localisation GPS',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (_currentPosition != null) ...[
              Text(
                'Latitude: ${_currentPosition!.latitude.toStringAsFixed(6)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Text(
                'Longitude: ${_currentPosition!.longitude.toStringAsFixed(6)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Text(
                'Précision: ${_currentPosition!.accuracy.toStringAsFixed(1)}m',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: _currentPosition!.accuracy <= 10
                      ? Colors.green
                      : Colors.orange,
                ),
              ),
              if (_currentPosition!.accuracy > 10)
                const Text(
                  'Attention: Précision GPS faible (>10m)',
                  style: TextStyle(color: Colors.orange, fontSize: 12),
                ),
            ] else ...[
              const Text(
                'Localisation non disponible',
                style: TextStyle(color: Colors.red),
              ),
              TextButton.icon(
                onPressed: _getCurrentLocation,
                icon: const Icon(Icons.refresh),
                label: const Text('Réessayer'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCameraSection(BuildContext context) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              Text(
                'Photo de preuve',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: _capturedImage != null
                    ? _buildImagePreview()
                    : _buildCameraPlaceholder(context),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  ElevatedButton.icon(
                    onPressed: _isCapturing
                        ? null
                        : () => _capturePhoto(ImageSource.camera),
                    icon: _isCapturing
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.camera_alt),
                    label: const Text('Appareil photo'),
                  ),
                  ElevatedButton.icon(
                    onPressed: _isCapturing
                        ? null
                        : () => _capturePhoto(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library),
                    label: const Text('Galerie'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildImagePreview() {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(8),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: Image.file(_capturedImage!, fit: BoxFit.contain),
      ),
    );
  }

  Widget _buildCameraPlaceholder(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!, style: BorderStyle.solid),
        borderRadius: BorderRadius.circular(8),
        color: Colors.grey[50],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.camera_alt, size: 64, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            'Aucune photo capturée',
            style: Theme.of(
              context,
            ).textTheme.bodyLarge?.copyWith(color: Colors.grey[600]),
          ),
          const SizedBox(height: 8),
          Text(
            'Prenez une photo pour prouver l\'avancement du travail',
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: Colors.grey[500]),
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
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.red[50],
              border: Border.all(color: Colors.red[200]!),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              _error!,
              style: const TextStyle(color: Colors.red),
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(height: 16),
        ],
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _canSubmit() ? _submitProof : null,
            icon: _isSubmitting
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : const Icon(Icons.send),
            label: Text(
              _isSubmitting ? 'Envoi en cours...' : 'Soumettre la preuve',
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).primaryColor,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
          ),
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
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 10),
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
