import 'dart:math';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/home_controller.dart';

class DeliveryRoutePlannerScreen extends StatefulWidget {
  final MissionModel mission;
  const DeliveryRoutePlannerScreen({super.key, required this.mission});

  @override
  State<DeliveryRoutePlannerScreen> createState() => _DeliveryRoutePlannerScreenState();
}

class _DeliveryRoutePlannerScreenState extends State<DeliveryRoutePlannerScreen> {
  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _pinsCollection;
  bool _mapReady = false;

  // Default fallback coords (Abidjan)
  double _driverLat = 5.3484;
  double _driverLng = -4.0169;

  // Predefined store and client positions relative to Abidjan center for routing demonstration
  late double _supplierLat;
  late double _supplierLng;
  late double _clientLat;
  late double _clientLng;

  @override
  void initState() {
    super.initState();
    _initCoordinates();
  }

  void _initCoordinates() {
    final c = Get.find<HomeController>();
    final gps = c.driverGpsCoords.value;
    if (gps.isNotEmpty) {
      final parts = gps.split(',');
      if (parts.length == 2) {
        _driverLat = double.tryParse(parts[0].trim()) ?? 5.3484;
        _driverLng = double.tryParse(parts[1].trim()) ?? -4.0169;
      }
    }

    // Set supplier and client positions with a small offsets for route visualization
    final rand = Random(widget.mission.id);
    _supplierLat = _driverLat + (rand.nextDouble() - 0.5) * 0.015;
    _supplierLng = _driverLng + (rand.nextDouble() - 0.5) * 0.015;
    _clientLat = _supplierLat + (rand.nextDouble() - 0.5) * 0.015;
    _clientLng = _supplierLng + (rand.nextDouble() - 0.5) * 0.015;
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _pinsCollection = mapWindow.map.mapObjects.addCollection();

    setState(() => _mapReady = true);

    _plotRouteMarkers();
    _centerMap();
  }

  void _plotRouteMarkers() {
    final col = _pinsCollection;
    if (col == null) return;
    col.clear();

    // 1. Driver Position (Yellow Marker)
    final p1 = col.addPlacemark();
    p1.geometry = mk.Point(latitude: _driverLat, longitude: _driverLng);

    // 2. Store Position (Supplier)
    final p2 = col.addPlacemark();
    p2.geometry = mk.Point(latitude: _supplierLat, longitude: _supplierLng);

    // 3. Destination Position (Client)
    final p3 = col.addPlacemark();
    p3.geometry = mk.Point(latitude: _clientLat, longitude: _clientLng);
  }

  void _centerMap() {
    final mw = _mapWindow;
    if (mw == null) return;

    // Center view on supplier (middle point of the itinerary)
    final center = mk.CameraPosition(
      mk.Point(latitude: _supplierLat, longitude: _supplierLng),
      zoom: 13.2,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      center,
      animation: const mk.Animation(type: mk.AnimationType.Smooth, duration: 1.0),
    );
  }

  Future<void> _launchExternalNavigation(double destLat, double destLng, String label) async {
    final googleUrl = Uri.parse('https://www.google.com/maps/search/?api=1&query=$destLat,$destLng');
    final appleUrl = Uri.parse('maps://?q=$destLat,$destLng');

    try {
      if (await canLaunchUrl(googleUrl)) {
        await launchUrl(googleUrl, mode: LaunchMode.externalApplication);
      } else if (await canLaunchUrl(appleUrl)) {
        await launchUrl(appleUrl, mode: LaunchMode.externalApplication);
      } else {
        Get.snackbar(
          'Erreur de Navigation',
          'Aucune application de navigation trouvée.',
          backgroundColor: AppColors.danger,
          colorText: Colors.white,
        );
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de lancer le guidage GPS.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text('Itinéraire CMD-#${widget.mission.id}'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Stack(
        children: [
          YandexMap(onMapCreated: _onMapCreated),
          if (!_mapReady)
            const Center(child: CircularProgressIndicator(color: AppColors.driver)),
          Positioned(
            left: 20,
            right: 20,
            bottom: 20,
            child: Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              elevation: 8,
              color: Colors.white,
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Planificateur d\'Itinéraire',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                    ),
                    const SizedBox(height: 16),
                    _buildRouteStep(
                      icon: Icons.store,
                      color: AppColors.warning,
                      title: 'Étape 1 : Retrait Matériaux',
                      address: widget.mission.artisanName ?? 'Quincaillerie Agréée',
                      onNav: () => _launchExternalNavigation(_supplierLat, _supplierLng, 'Fournisseur'),
                    ),
                    const Padding(
                      padding: EdgeInsets.only(left: 12.0),
                      child: SizedBox(height: 10, child: VerticalDivider(thickness: 2, color: AppColors.border)),
                    ),
                    _buildRouteStep(
                      icon: Icons.person_pin_circle,
                      color: AppColors.success,
                      title: 'Étape 2 : Livraison Client',
                      address: widget.mission.clientName ?? 'Chantier Client',
                      onNav: () => _launchExternalNavigation(_clientLat, _clientLng, 'Client'),
                    ),
                  ],
                ),
              ),
            ),
          )
        ],
      ),
    );
  }

  Widget _buildRouteStep({
    required IconData icon,
    required Color color,
    required String title,
    required String address,
    required VoidCallback onNav,
  }) {
    return Row(
      children: [
        CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.15),
          radius: 18,
          child: Icon(icon, color: color, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.textSecondary)),
              const SizedBox(height: 2),
              Text(address, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
            ],
          ),
        ),
        const SizedBox(width: 8),
        IconButton(
          onPressed: onNav,
          icon: Icon(Icons.navigation_outlined, color: AppColors.driver),
          tooltip: 'Naviguer',
        ),
      ],
    );
  }
}
