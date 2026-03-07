import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';

class LocationPickerScreen extends StatefulWidget {
  const LocationPickerScreen({super.key});

  @override
  State<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends State<LocationPickerScreen> {
  mk.MapWindow? _mapWindow;
  mk.Point? _selectedLocation;
  String _address = 'Sélectionnez un emplacement';
  bool _isLoading = false;

  // Default to Abidjan
  static const double _kAbidjanLat = 5.3484;
  static const double _kAbidjanLng = -4.0169;
  static const double _kDefaultZoom = 14.0;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  Future<void> _getCurrentLocation() async {
    try {
      setState(() => _isLoading = true);
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );
      _selectedLocation = mk.Point(
        latitude: position.latitude,
        longitude: position.longitude,
      );
      _updateAddress(position.latitude, position.longitude);
    } catch (e) {
      _selectedLocation = const mk.Point(
        latitude: _kAbidjanLat,
        longitude: _kAbidjanLng,
      );
      _address = 'Abidjan, Côte d\'Ivoire';
    } finally {
      setState(() => _isLoading = false);
    }
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;

    // Move to initial location
    if (_selectedLocation != null) {
      _moveCamera(_selectedLocation!.latitude, _selectedLocation!.longitude);
    }

    // Add tap listener
    mapWindow.map.addInputListener(_MapTapListener((point) {
      setState(() {
        _selectedLocation = point;
        _updateAddress(point.latitude, point.longitude);
      });
    }));
  }

  void _moveCamera(double lat, double lng, {bool animated = true}) {
    final mw = _mapWindow;
    if (mw == null) return;

    final position = mk.CameraPosition(
      mk.Point(latitude: lat, longitude: lng),
      zoom: _kDefaultZoom,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      position,
      animation: animated
          ? const mk.Animation(
              type: mk.AnimationType.Smooth,
              duration: 0.8,
            )
          : null,
    );
  }

  void _updateAddress(double lat, double lng) {
    // Simple address formatting - in production, use reverse geocoding
    setState(() {
      _address = 'Lat: ${lat.toStringAsFixed(4)}, Lng: ${lng.toStringAsFixed(4)}';
    });
  }

  void _confirmLocation() {
    if (_selectedLocation != null) {
      Get.back(result: {
        'latitude': _selectedLocation!.latitude,
        'longitude': _selectedLocation!.longitude,
        'address': _address,
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // Yandex Map
          YandexMap(onMapCreated: _onMapCreated),

          // Center marker
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.location_on,
                  size: 48,
                  color: Color(0xFF4F46E5),
                ),
                Container(
                  width: 4,
                  height: 4,
                  decoration: const BoxDecoration(
                    color: Color(0xFF4F46E5),
                    shape: BoxShape.circle,
                  ),
                ),
              ],
            ),
          ),

          // Top bar
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: EdgeInsets.fromLTRB(
                16,
                MediaQuery.of(context).padding.top + 16,
                16,
                16,
              ),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withValues(alpha: 0.6),
                    Colors.transparent,
                  ],
                ),
              ),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => Get.back(),
                    child: Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.1),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.arrow_back,
                        color: Color(0xFF4F46E5),
                        size: 22,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'Sélectionner l\'emplacement',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                        shadows: [
                          Shadow(blurRadius: 4, color: Colors.black54),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Bottom panel
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(24),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.2),
                    blurRadius: 24,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              padding: EdgeInsets.fromLTRB(
                20,
                20,
                20,
                MediaQuery.of(context).padding.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          