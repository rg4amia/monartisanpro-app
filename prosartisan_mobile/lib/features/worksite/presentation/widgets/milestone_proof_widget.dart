import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/domain/value_objects/gps_coordinates.dart';
import '../../../../shared/widgets/photo_capture_widget.dart';
import '../../../../shared/controllers/photo_controller.dart';

/// Widget for submitting milestone proof with GPS-tagged photos
class MilestoneProofWidget extends StatefulWidget {
  final String milestoneId;
  final String milestoneDescription;
  final Function(File photo, GPSCoordinates? coordinates, String description)
  onSubmit;
  final bool isSubmitting;

  const MilestoneProofWidget({
    super.key,
    required this.milestoneId,
    required this.milestoneDescription,
    required this.onSubmit,
    this.isSubmitting = false,
  });

  @override
  State<MilestoneProofWidget> createState() => _MilestoneProofWidgetState();
}

class _MilestoneProofWidgetState extends State<MilestoneProofWidget> {
  final PhotoController _photoController = Get.put(PhotoController());
  final TextEditingController _descriptionController = TextEditingController();

  File? _capturedPhoto;
  GPSCoordinates? _photoCoordinates;

  @override
  void initState() {
    super.initState();
    _descriptionController.text = widget.milestoneDescription;
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.camera_alt, color: Colors.orange),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Preuve de livraison',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 16),

            // Description field
            TextFormField(
              controller: _descriptionController,
              decoration: const InputDecoration(
                labelText: 'Description du jalon',
                hintText: 'Décrivez le travail accompli...',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
              enabled: !widget.isSubmitting,
            ),

            const SizedBox(height: 16),

            // Photo capture section
            const Text(
              'Photo avec localisation GPS',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),

            const SizedBox(height: 8),

            const Text(
              'Prenez une photo du travail réalisé. La localisation GPS sera automatiquement ajoutée pour vérifier que vous êtes sur le chantier.',
              style: TextStyle(fontSize: 14, color: Colors.grey),
            ),

            const SizedBox(height: 12),

            // Photo capture widget
            PhotoCaptureWidget(
              requireGPS: true,
              title: 'Prendre une photo du jalon',
              subtitle: 'GPS requis pour la validation',
              height: 200,
              onPhotoCapture: (file, coordinates) {
                setState(() {
                  _capturedPhoto = file;
                  _photoCoordinates = coordinates;
                });
              },
            ),

            const SizedBox(height: 16),

            // GPS status indicator
            if (_capturedPhoto != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: _photoCoordinates != null
                      ? Colors.green.shade50
                      : Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: _photoCoordinates != null
                        ? Colors.green.shade200
                        : Colors.orange.shade200,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      _photoCoordinates != null
                          ? Icons.location_on
                          : Icons.location_off,
                      color: _photoCoordinates != null
                          ? Colors.green
                          : Colors.orange,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _photoCoordinates != null
                                ? 'Localisation GPS enregistrée'
                                : 'Aucune donnée GPS',
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: _photoCoordinates != null
                                  ? Colors.green.shade700
                                  : Colors.orange.shade700,
                            ),
                          ),
                          if (_photoCoordinates != null)
                            Text(
                              'Lat: ${_photoCoordinates!.latitude.toStringAsFixed(6)}\n'
                              'Lon: ${_photoCoordinates!.longitude.toStringAsFixed(6)}',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.green.shade600,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),
            ],

            // Submit button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _canSubmit() && !widget.isSubmitting
                    ? _submitProof
                    : null,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: widget.isSubmitting
                    ? const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(
                                Colors.white,
                              ),
                            ),
                          ),
                          SizedBox(width: 12),
                          Text('Envoi en cours...'),
                        ],
                      )
                    : const Text(
                        'Soumettre la preuve',
                        style: TextStyle(fontSize: 16),
                      ),
              ),
            ),

            // Warning message if no GPS
            if (_capturedPhoto != null && _photoCoordinates == null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.warning, color: Colors.red.shade600),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Attention: Cette photo ne contient pas de données GPS. '
                        'La validation pourrait nécessiter une vérification supplémentaire.',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.red.shade700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  bool _canSubmit() {
    return _capturedPhoto != null &&
        _descriptionController.text.trim().isNotEmpty;
  }

  void _submitProof() {
    if (!_canSubmit()) return;

    widget.onSubmit(
      _capturedPhoto!,
      _photoCoordinates,
      _descriptionController.text.trim(),
    );
  }
}
