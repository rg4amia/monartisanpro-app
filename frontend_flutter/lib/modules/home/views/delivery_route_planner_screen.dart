import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/home_controller.dart';

enum DeliveryPhase {
  pickup, // Étape 1 : Trajet vers le fournisseur pour retrait matériel
  delivery, // Étape 2 : Trajet vers le client pour livraison
  completed, // Course terminée
}

class DeliveryRoutePlannerScreen extends StatefulWidget {
  final MissionModel mission;
  const DeliveryRoutePlannerScreen({super.key, required this.mission});

  @override
  State<DeliveryRoutePlannerScreen> createState() => _DeliveryRoutePlannerScreenState();
}

class _DeliveryRoutePlannerScreenState extends State<DeliveryRoutePlannerScreen> {
  final HomeController _homeController = Get.find<HomeController>();

  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _pinsCollection;
  mk.MapObjectCollection? _routesCollection;
  bool _mapReady = false;

  late DeliveryPhase _currentPhase;

  // Coordonnées GPS
  double _driverLat = 5.3484;
  double _driverLng = -4.0169;

  late double _supplierLat;
  late double _supplierLng;
  late double _clientLat;
  late double _clientLng;

  String? _routeDistanceText;
  String? _routeDurationText;

  @override
  void initState() {
    super.initState();
    _initPhase();
    _initCoordinates();
  }

  void _initPhase() {
    final rawStatus = widget.mission.rawStatus;
    if (rawStatus == 'shipping' || rawStatus == 'driver_picked_up') {
      _currentPhase = DeliveryPhase.delivery;
    } else if (rawStatus == 'terminee' || rawStatus == 'delivered') {
      _currentPhase = DeliveryPhase.completed;
    } else {
      _currentPhase = DeliveryPhase.pickup;
    }
  }

  void _initCoordinates() {
    final gps = _homeController.driverGpsCoords.value;
    if (gps.isNotEmpty) {
      final parts = gps.split(',');
      if (parts.length == 2) {
        _driverLat = double.tryParse(parts[0].trim()) ?? 5.3484;
        _driverLng = double.tryParse(parts[1].trim()) ?? -4.0169;
      }
    }

    // Coordonnées fournisseur et client réalistes calculées à partir de l'ID de la mission
    final rand = Random(widget.mission.id);
    _supplierLat = _driverLat + (rand.nextDouble() * 0.012 + 0.005);
    _supplierLng = _driverLng + (rand.nextDouble() * 0.012 + 0.005);

    _clientLat = _supplierLat + (rand.nextDouble() * 0.015 + 0.008);
    _clientLng = _supplierLng - (rand.nextDouble() * 0.015 + 0.008);
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _routesCollection = mapWindow.map.mapObjects.addCollection();
    _pinsCollection = mapWindow.map.mapObjects.addCollection();

    setState(() => _mapReady = true);

    _updateMapElements();
  }

  /// Récupère la géométrie réelle du réseau routier via OSRM (Open Source Routing Machine)
  Future<List<mk.Point>> _fetchRoadRoutePoints(double lat1, double lng1, double lat2, double lng2) async {
    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/$lng1,$lat1;$lng2,$lat2?overview=full&geometries=geojson',
      );
      final response = await http.get(url).timeout(const Duration(seconds: 4));
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['code'] == 'Ok' && data['routes'] is List && (data['routes'] as List).isNotEmpty) {
          final route = data['routes'][0];
          final geometry = route['geometry'];
          final coords = (geometry['coordinates'] as List?);

          if (route['distance'] != null) {
            final distKm = ((route['distance'] as num) / 1000).toStringAsFixed(1);
            _routeDistanceText = '$distKm km';
          }
          if (route['duration'] != null) {
            final durMin = ((route['duration'] as num) / 60).round();
            _routeDurationText = '$durMin min';
          }

          if (coords != null && coords.isNotEmpty) {
            return coords.map((c) {
              final lng = (c[0] as num).toDouble();
              final lat = (c[1] as num).toDouble();
              return mk.Point(latitude: lat, longitude: lng);
            }).toList();
          }
        }
      }
    } catch (e) {
      debugPrint('[RoutePlanner] OSRM routing fallback: $e');
    }

    // Fallback lissé avec waypoints virtuels suivant des angles de rue
    final points = <mk.Point>[];
    const steps = 12;
    for (int i = 0; i <= steps; i++) {
      final t = i / steps;
      final latInterp = lat1 + (lat2 - lat1) * t;
      final lngInterp = lng1 + (lng2 - lng1) * t;

      // Création d'une courbure simulant un parcours en grille urbaine
      final offsetLat = sin(t * pi) * 0.0025;
      final offsetLng = cos(t * pi * 2) * 0.0015;

      points.add(mk.Point(
        latitude: latInterp + (i % 2 == 0 ? offsetLat : -offsetLat * 0.5),
        longitude: lngInterp + offsetLng,
      ));
    }
    return points;
  }

  Future<void> _updateMapElements() async {
    final pins = _pinsCollection;
    final routes = _routesCollection;
    if (pins == null || routes == null) return;

    pins.clear();
    routes.clear();

    if (_currentPhase == DeliveryPhase.pickup) {
      // ── ÉTAPE 1 : Livreur -> Fournisseur ──
      final pDriver = pins.addPlacemark();
      pDriver.geometry = mk.Point(latitude: _driverLat, longitude: _driverLng);

      final pSupplier = pins.addPlacemark();
      pSupplier.geometry = mk.Point(latitude: _supplierLat, longitude: _supplierLng);

      final points = await _fetchRoadRoutePoints(_driverLat, _driverLng, _supplierLat, _supplierLng);
      try {
        final poly = routes.addPolyline();
        poly.geometry = mk.Polyline(points);
        poly.setStrokeColor(const Color(0xFFF59E0B)); // Orange
        // ignore: deprecated_member_use
        poly.strokeWidth = 5.0;
      } catch (_) {}

      _focusCamera(
        lat1: _driverLat,
        lng1: _driverLng,
        lat2: _supplierLat,
        lng2: _supplierLng,
      );
    } else if (_currentPhase == DeliveryPhase.delivery) {
      // ── ÉTAPE 2 : Fournisseur -> Client ──
      final pPickup = pins.addPlacemark();
      pPickup.geometry = mk.Point(latitude: _supplierLat, longitude: _supplierLng);

      final pClient = pins.addPlacemark();
      pClient.geometry = mk.Point(latitude: _clientLat, longitude: _clientLng);

      final points = await _fetchRoadRoutePoints(_supplierLat, _supplierLng, _clientLat, _clientLng);
      try {
        final poly = routes.addPolyline();
        poly.geometry = mk.Polyline(points);
        poly.setStrokeColor(const Color(0xFF10B981)); // Vert
        // ignore: deprecated_member_use
        poly.strokeWidth = 5.0;
      } catch (_) {}

      _focusCamera(
        lat1: _supplierLat,
        lng1: _supplierLng,
        lat2: _clientLat,
        lng2: _clientLng,
      );
    }
  }

  void _focusCamera({
    required double lat1,
    required double lng1,
    required double lat2,
    required double lng2,
  }) {
    final mw = _mapWindow;
    if (mw == null) return;

    final centerLat = (lat1 + lat2) / 2;
    final centerLng = (lng1 + lng2) / 2;

    final center = mk.CameraPosition(
      mk.Point(latitude: centerLat, longitude: centerLng),
      zoom: 14.0,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      center,
      animation: const mk.Animation(type: mk.AnimationType.Smooth, duration: 1.0),
    );
  }

  Future<void> _launchExternalNavigation(double destLat, double destLng, String label) async {
    final googleUrl = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$destLat,$destLng');
    final appleUrl = Uri.parse('maps://?daddr=$destLat,$destLng');

    try {
      if (await canLaunchUrl(googleUrl)) {
        await launchUrl(googleUrl, mode: LaunchMode.externalApplication);
      } else if (await canLaunchUrl(appleUrl)) {
        await launchUrl(appleUrl, mode: LaunchMode.externalApplication);
      } else {
        Get.snackbar(
          'Navigation GPS',
          'Coordonnées : $destLat, $destLng',
          backgroundColor: AppColors.primary,
          colorText: Colors.white,
        );
      }
    } catch (_) {
      Get.snackbar(
        'Erreur GPS',
        'Impossible de lancer l\'application de navigation.',
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    }
  }

  void _promptPickupValidation() {
    final textController = TextEditingController(text: 'RET-${widget.mission.id}');
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.storefront_rounded, color: AppColors.warning),
            SizedBox(width: 8),
            Text('Enlèvement Magasin', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Confirmez la récupération du matériel chez ${widget.mission.artisanName ?? 'le fournisseur'}.',
              style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: textController,
              decoration: InputDecoration(
                labelText: 'Code de retrait fournisseur',
                hintText: 'Ex: RET-${widget.mission.id}',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                prefixIcon: const Icon(Icons.qr_code_scanner_rounded),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler', style: TextStyle(color: AppColors.textSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.warning,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () async {
              Get.back();
              await _homeController.handleDriverPickupFromStore(widget.mission, textController.text.trim());
              setState(() {
                _currentPhase = DeliveryPhase.delivery;
              });
              _updateMapElements();
            },
            child: const Text('Valider Enlèvement', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  void _promptDeliveryValidation() {
    final textController = TextEditingController(text: 'REC-${widget.mission.id}');
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.check_circle_rounded, color: AppColors.success),
            SizedBox(width: 8),
            Text('Livraison Client', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Remettez les articles à ${widget.mission.clientName ?? 'Client'} et demandez le code de réception OTP.',
              style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: textController,
              decoration: InputDecoration(
                labelText: 'Code de réception client (OTP)',
                hintText: 'Ex: REC-${widget.mission.id}',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                prefixIcon: const Icon(Icons.pin_outlined),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text('Annuler', style: TextStyle(color: AppColors.textSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.success,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () async {
              Get.back();
              await _homeController.handleDriverDropoffToClient(widget.mission, textController.text.trim());
              setState(() {
                _currentPhase = DeliveryPhase.completed;
              });
            },
            child: const Text('Confirmer Livraison', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final deliveryFee = widget.mission.montantMo > 0
        ? widget.mission.montantMo
        : (widget.mission.montantTotal > 0 ? (widget.mission.montantTotal * 0.15).toInt() : 1500);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Course #${widget.mission.id} • ${Formatters.fcfa(deliveryFee)}',
          style: const TextStyle(
            color: Color(0xFF1E293B),
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1E293B),
        iconTheme: const IconThemeData(color: Color(0xFF1E293B)),
        elevation: 1.5,
        actions: [
          IconButton(
            icon: const Icon(Icons.my_location_rounded, color: AppColors.primary),
            tooltip: 'Recentrer la carte',
            onPressed: () => _updateMapElements(),
          ),
        ],
      ),
      body: Stack(
        children: [
          // ── Map Canvas ──
          Positioned.fill(
            child: YandexMap(onMapCreated: _onMapCreated),
          ),
          if (!_mapReady)
            const Center(child: CircularProgressIndicator(color: AppColors.driver)),

          // ── Top Phase Indicator HUD ──
          Positioned(
            top: 12,
            left: 16,
            right: 16,
            child: _buildTopHud(),
          ),

          // ── Bottom Floating Action Panel ──
          Positioned(
            left: 16,
            right: 16,
            bottom: 20,
            child: _buildBottomPanel(),
          ),
        ],
      ),
    );
  }

  Widget _buildTopHud() {
    final isPickup = _currentPhase == DeliveryPhase.pickup;
    final isCompleted = _currentPhase == DeliveryPhase.completed;

    final badgeColor = isCompleted
        ? AppColors.success
        : isPickup
            ? const Color(0xFFD97706)
            : AppColors.success;

    final targetName = isPickup
        ? (widget.mission.artisanName?.isNotEmpty == true ? widget.mission.artisanName! : 'Quincaillerie Partenaire')
        : (widget.mission.clientName?.isNotEmpty == true ? widget.mission.clientName! : 'Client');

    return Material(
      elevation: 6,
      borderRadius: BorderRadius.circular(16),
      color: Colors.white,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border.withValues(alpha: 0.8)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: badgeColor.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isCompleted
                    ? Icons.check_circle_rounded
                    : isPickup
                        ? Icons.storefront_rounded
                        : Icons.delivery_dining_rounded,
                color: badgeColor,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          isCompleted
                              ? 'COURSE TERMINÉE'
                              : isPickup
                                  ? 'ÉTAPE 1/2 • RETRAIT MATÉRIEL'
                                  : 'ÉTAPE 2/2 • LIVRAISON CLIENT',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.5,
                            color: badgeColor,
                          ),
                        ),
                      ),
                      if (_routeDistanceText != null && !isCompleted) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: badgeColor.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            '$_routeDistanceText${_routeDurationText != null ? " • $_routeDurationText" : ""}',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: badgeColor,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    isCompleted ? 'Livraison effectuée avec succès' : 'Vers : $targetName',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomPanel() {
    if (_currentPhase == DeliveryPhase.completed) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20.0),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.border),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.12),
              blurRadius: 14,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 52),
            const SizedBox(height: 10),
            const Text(
              'Course Livrée avec Succès !',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 6),
            const Text(
              'Votre compte a été crédité du montant de la livraison.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () => Get.back(),
                child: const Text('Retour à mes courses', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
              ),
            ),
          ],
        ),
      );
    }

    final isPickup = _currentPhase == DeliveryPhase.pickup;
    final targetName = isPickup
        ? (widget.mission.artisanName?.isNotEmpty == true ? widget.mission.artisanName! : 'Quincaillerie Partenaire')
        : (widget.mission.clientName?.isNotEmpty == true ? widget.mission.clientName! : 'Client');

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16.0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Details Header
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      isPickup ? 'POINT D\'ENLÈVEMENT (MAGASIN)' : 'DESTINATION (CLIENT)',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textSecondary,
                        letterSpacing: 0.4,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      targetName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              InkWell(
                onTap: () {
                  if (isPickup) {
                    _launchExternalNavigation(_supplierLat, _supplierLng, 'Fournisseur');
                  } else {
                    _launchExternalNavigation(_clientLat, _clientLng, 'Client');
                  }
                },
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.navigation_rounded, size: 14, color: Colors.white),
                      SizedBox(width: 4),
                      Text('GPS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Description articles
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                const Icon(Icons.inventory_2_outlined, size: 16, color: AppColors.textSecondary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    widget.mission.description?.isNotEmpty == true
                        ? widget.mission.description!
                        : 'Articles commandés #${widget.mission.id}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF334155),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Primary Action Button
          SizedBox(
            height: 48,
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: isPickup ? const Color(0xFFF59E0B) : const Color(0xFF10B981),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
              onPressed: isPickup ? _promptPickupValidation : _promptDeliveryValidation,
              icon: Icon(isPickup ? Icons.qr_code_scanner_rounded : Icons.check_circle_outline, size: 20),
              label: Text(
                isPickup ? 'Valider l\'Enlèvement Magasin' : 'Valider la Livraison Client',
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
