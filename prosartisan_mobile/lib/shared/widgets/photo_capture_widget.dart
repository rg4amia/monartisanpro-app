import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../core/services/photo/photo_capture_service.dart';
import '../../core/domain/value_objects/gps_coordinates.dart';

/// Widget for capturing photos with GPS data
class PhotoCaptureWidget extends StatefulWidget {
  final Function(File file, GPSCoordinates? coordinates) onPhotoCapture;
  final bool requireGPS;
  final String? title;
  final String? subtitle;
  final Widget? placeholder;
  final double? width;
  final double? height;

  const PhotoCaptureWidget({
    super.key,
    required this.onPhotoCapture,
    this.requireGPS = false,
    this.title,
    this.subtitle,
    this.placeholder,
    this.width,
    this.height,
  });

  @override
  State<PhotoCaptureWidget> createState() => _PhotoCaptureWidgetState();
}

class _PhotoCaptureWidgetState extends State<PhotoCaptureWidget> {
  final PhotoCaptureService _photoCaptureService = PhotoCaptureService();
  File? _capturedPhoto;
  GPSCoordinates? _coordinates;
  bool _isCapturing = false;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: widget.width ?? double.infinity,
      height: widget.height ?? 200,
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(12),
        color: Colors.grey.shade50,
      ),
      child: _capturedPhoto != null
          ? _buildPhotoPreview()
          : _buildCaptureButton(),
    );
  }

  Widget _buildCaptureButton() {
    return InkWell(
      onTap: _isCapturing ? null : _showCaptureOptions,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (widget.placeholder != null)
              widget.placeholder!
            else
              Icon(
                Icons.camera_alt,
                size: 48,
                color: _isCapturing ? Colors.grey : Colors.orange,
              ),

            const SizedBox(height: 12),

            if (widget.title != null)
              Text(
                widget.title!,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
                textAlign: TextAlign.center,
              ),

            if (widget.subtitle != null) ...[
              const SizedBox(height: 4),
              Text(
                widget.subtitle!,
                style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                textAlign: TextAlign.center,
              ),
            ],

            if (_isCapturing) ...[
              const SizedBox(height: 12),
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildPhotoPreview() {
    return Stack(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.file(
            _capturedPhoto!,
            width: double.infinity,
            height: double.infinity,
            fit: BoxFit.cover,
          ),
        ),

        // GPS indicator
        if (_coordinates != null)
          Positioned(
            top: 8,
            left: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.green,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.location_on, color: Colors.white, size: 16),
                  SizedBox(width: 4),
                  Text(
                    'GPS',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ),

        // Action buttons
        Positioned(
          top: 8,
          right: 8,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Retake button
              Container(
                decoration: const BoxDecoration(
                  color: Colors.orange,
                  shape: BoxShape.circle,
                ),
                child: IconButton(
                  icon: const Icon(
                    Icons.camera_alt,
                    color: Colors.white,
                    size: 20,
                  ),
                  onPressed: _showCaptureOptions,
                  padding: const EdgeInsets.all(8),
                  constraints: const BoxConstraints(),
                ),
              ),

              const SizedBox(width: 8),

              // Remove button
              Container(
                decoration: const BoxDecoration(
                  color: Colors.red,
                  shape: BoxShape.circle,
                ),
                child: IconButton(
                  icon: const Icon(Icons.close, color: Colors.white, size: 20),
                  onPressed: _removePhoto,
                  padding: const EdgeInsets.all(8),
                  constraints: const BoxConstraints(),
                ),
              ),
            ],
          ),
        ),

        // Photo info
        Positioned(
          bottom: 8,
          left: 8,
          right: 8,
          child: Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.7),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Photo capturée',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (_coordinates != null)
                  Text(
                    'GPS: ${_coordinates!.latitude.toStringAsFixed(6)}, ${_coordinates!.longitude.toStringAsFixed(6)}',
                    style: const TextStyle(color: Colors.white70, fontSize: 10),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  void _showCaptureOptions() {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),

            const SizedBox(height: 20),

            const Text(
              'Choisir une photo',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
            ),

            const SizedBox(height: 20),

            // Camera option
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Colors.orange),
              title: const Text('Prendre une photo'),
              subtitle: widget.requireGPS
                  ? const Text('Avec localisation GPS')
                  : null,
              onTap: () {
                Get.back();
                _captureFromCamera();
              },
            ),

            // Gallery option
            ListTile(
              leading: const Icon(Icons.photo_library, color: Colors.blue),
              title: const Text('Choisir depuis la galerie'),
              subtitle: const Text('GPS extrait si disponible'),
              onTap: () {
                Get.back();
                _pickFromGallery();
              },
            ),

            const SizedBox(height: 10),
          ],
        ),
      ),
    );
  }

  Future<void> _captureFromCamera() async {
    setState(() {
      _isCapturing = true;
    });

    try {
      final result = await _photoCaptureService.capturePhotoWithGPS(
        requireGPS: widget.requireGPS,
      );

      setState(() {
        _capturedPhoto = result.file;
        _coordinates = result.coordinates;
        _isCapturing = false;
      });

      widget.onPhotoCapture(result.file, result.coordinates);

      if (widget.requireGPS && !result.hasGPSData) {
        Get.snackbar(
          'Attention',
          'Aucune donnée GPS n\'a pu être ajoutée à la photo',
          backgroundColor: Colors.orange,
          colorText: Colors.white,
        );
      }
    } catch (e) {
      setState(() {
        _isCapturing = false;
      });

      Get.snackbar(
        'Erreur',
        e.toString(),
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  Future<void> _pickFromGallery() async {
    setState(() {
      _isCapturing = true;
    });

    try {
      final result = await _photoCaptureService.pickPhotoFromGallery();

      setState(() {
        _capturedPhoto = result.file;
        _coordinates = result.coordinates;
        _isCapturing = false;
      });

      widget.onPhotoCapture(result.file, result.coordinates);

      if (widget.requireGPS && !result.hasGPSData) {
        Get.snackbar(
          'Attention',
          'Cette photo ne contient pas de données GPS',
          backgroundColor: Colors.orange,
          colorText: Colors.white,
        );
      }
    } catch (e) {
      setState(() {
        _isCapturing = false;
      });

      Get.snackbar(
        'Erreur',
        e.toString(),
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
    }
  }

  void _removePhoto() {
    setState(() {
      _capturedPhoto = null;
      _coordinates = null;
    });
  }
}
