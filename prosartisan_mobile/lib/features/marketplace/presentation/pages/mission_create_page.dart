import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:geolocator/geolocator.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/presentation/controllers/mission_controller.dart';
import 'package:prosartisan_mobile/features/marketplace/presentation/widgets/category_selector_widget.dart';

class MissionCreatePage extends GetView<MissionController> {
  const MissionCreatePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Créer une mission'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
      ),
      body: Form(
        key: controller.formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Description section
              _buildSectionTitle('Description de la mission'),
              const SizedBox(height: 8),
              TextFormField(
                controller: controller.descriptionController,
                maxLines: 4,
                decoration: const InputDecoration(
                  hintText: 'Décrivez en détail les travaux à réaliser...',
                  border: OutlineInputBorder(),
                ),
                validator: controller.validateDescription,
              ),

              const SizedBox(height: 24),

              // Category section
              _buildSectionTitle('Catégorie d\'artisan'),
              const SizedBox(height: 8),
              Obx(
                () => CategorySelectorWidget(
                  selectedCategory: controller.selectedCategory,
                  onCategorySelected: controller.setCategory,
                ),
              ),

              const SizedBox(height: 24),

              // Budget section
              _buildSectionTitle('Budget estimé (FCFA)'),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: controller.budgetMinController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Budget minimum',
                        border: OutlineInputBorder(),
                        suffixText: 'FCFA',
                      ),
                      validator: (value) =>
                          controller.validateBudget(value, isMin: true),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: TextFormField(
                      controller: controller.budgetMaxController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Budget maximum',
                        border: OutlineInputBorder(),
                        suffixText: 'FCFA',
                      ),
                      validator: (value) =>
                          controller.validateBudget(value, isMin: false),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Location section
              _buildSectionTitle('Localisation'),
              const SizedBox(height: 8),
              _buildLocationSelector(),

              const SizedBox(height: 32),

              // Submit button
              Obx(
                () => SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: controller.isLoading || !controller.canSubmit
                        ? null
                        : controller.createMission,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue[600],
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    child: controller.isLoading
                        ? const CircularProgressIndicator(color: Colors.white)
                        : const Text(
                            'Créer la mission',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: Get.textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.bold,
        color: Colors.blue[700],
      ),
    );
  }

  Widget _buildLocationSelector() {
    return Obx(() {
      final selectedLocation = controller.selectedLocation;

      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.location_on, color: Colors.blue),
                  const SizedBox(width: 8),
                  Text(
                    selectedLocation != null
                        ? 'Localisation sélectionnée'
                        : 'Sélectionner la localisation',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  const Spacer(),
                  TextButton(
                    onPressed: _showLocationPicker,
                    child: Text(
                      selectedLocation != null ? 'Modifier' : 'Choisir',
                    ),
                  ),
                ],
              ),

              if (selectedLocation != null) ...[
                const SizedBox(height: 8),
                Text(
                  'Lat: ${selectedLocation.latitude.toStringAsFixed(6)}, '
                  'Lng: ${selectedLocation.longitude.toStringAsFixed(6)}',
                  style: const TextStyle(color: Colors.grey),
                ),
                const SizedBox(height: 8),
                Container(
                  height: 150,
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey[300]!),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: GoogleMap(
                    initialCameraPosition: CameraPosition(
                      target: LatLng(
                        selectedLocation.latitude,
                        selectedLocation.longitude,
                      ),
                      zoom: 16,
                    ),
                    markers: {
                      Marker(
                        markerId: const MarkerId('selected_location'),
                        position: LatLng(
                          selectedLocation.latitude,
                          selectedLocation.longitude,
                        ),
                      ),
                    },
                    zoomControlsEnabled: false,
                    scrollGesturesEnabled: false,
                    zoomGesturesEnabled: false,
                    tiltGesturesEnabled: false,
                    rotateGesturesEnabled: false,
                  ),
                ),
              ] else ...[
                const SizedBox(height: 8),
                const Text(
                  'Appuyez sur "Choisir" pour sélectionner la localisation de votre mission',
                  style: TextStyle(color: Colors.grey),
                ),
              ],
            ],
          ),
        ),
      );
    });
  }

  void _showLocationPicker() {
    Get.to(() => const LocationPickerPage())?.then((location) {
      if (location != null && location is GPSCoordinates) {
        controller.setLocation(location);
      }
    });
  }
}

class LocationPickerPage extends StatefulWidget {
  const LocationPickerPage({super.key});

  @override
  State<LocationPickerPage> createState() => _LocationPickerPageState();
}

class _LocationPickerPageState extends State<LocationPickerPage> {
  GPSCoordinates? _selectedLocation;
  GPSCoordinates? _currentLocation;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  Future<void> _getCurrentLocation() async {
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        setState(() => _isLoading = false);
        return;
      }

      final position = await Geolocator.getCurrentPosition();
      final location = GPSCoordinates(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
      );

      setState(() {
        _currentLocation = location;
        _selectedLocation = location;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Choisir la localisation'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
        actions: [
          TextButton(
            onPressed: _selectedLocation != null
                ? () => Get.back(result: _selectedLocation)
                : null,
            child: const Text(
              'Confirmer',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Stack(
              children: [
                GoogleMap(
                  onMapCreated: (controller) {
                    // Map controller can be used if needed
                  },
                  initialCameraPosition: CameraPosition(
                    target: _currentLocation != null
                        ? LatLng(
                            _currentLocation!.latitude,
                            _currentLocation!.longitude,
                          )
                        : const LatLng(5.3600, -4.0083), // Abidjan default
                    zoom: 14,
                  ),
                  onTap: _onMapTapped,
                  markers: _selectedLocation != null
                      ? {
                          Marker(
                            markerId: const MarkerId('selected'),
                            position: LatLng(
                              _selectedLocation!.latitude,
                              _selectedLocation!.longitude,
                            ),
                          ),
                        }
                      : {},
                  myLocationEnabled: true,
                  myLocationButtonEnabled: true,
                ),

                Positioned(
                  bottom: 16,
                  left: 16,
                  right: 16,
                  child: Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text(
                            'Appuyez sur la carte pour sélectionner la localisation de votre mission',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontWeight: FontWeight.bold),
                          ),
                          if (_selectedLocation != null) ...[
                            const SizedBox(height: 8),
                            Text(
                              'Coordonnées: ${_selectedLocation!.latitude.toStringAsFixed(6)}, '
                              '${_selectedLocation!.longitude.toStringAsFixed(6)}',
                              style: const TextStyle(color: Colors.grey),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  void _onMapTapped(LatLng position) {
    setState(() {
      _selectedLocation = GPSCoordinates(
        latitude: position.latitude,
        longitude: position.longitude,
      );
    });
  }
}
