import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';

import '../../../core/config/env_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../shared/widgets/map_offline_notice.dart';
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
  State<DeliveryRoutePlannerScreen> createState() =>
      _DeliveryRoutePlannerScreenState();
}

class _DeliveryRoutePlannerScreenState
    extends State<DeliveryRoutePlannerScreen> {
  final HomeController _homeController = Get.find<HomeController>();
  final OrderRepository _orderRepo = OrderRepository();

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

  /// `true` tant qu'au moins une position affichée est dérivée (fournisseur ou
  /// client absent de la mission) et non une vraie coordonnée GPS.
  bool _coordsApproximate = false;

  String? _routeDistanceText;
  String? _routeDurationText;

  /// `true` quand la distance affichée est une estimation à vol d'oiseau
  /// (aucun itinéraire routier OSRM disponible).
  bool _routeIsEstimate = false;

  // ── Télémétrie en direct (Option 3 / Lot 4) ──
  Timer? _telemetryTimer;
  bool _isTelemetryActive = false;
  double? _lastSpeedKmh;

  @override
  void initState() {
    super.initState();
    _initPhase();
    _initCoordinates();
    _resolveDriverPosition();
    _startTelemetryPublisher();
  }

  @override
  void dispose() {
    _stopTelemetryPublisher();
    super.dispose();
  }

  void _startTelemetryPublisher() {
    _stopTelemetryPublisher();
    if (_currentPhase == DeliveryPhase.completed) return;

    // Envoi initial immédiat
    _emitDriverTelemetry();

    // Envoi périodique toutes les 15 secondes
    _telemetryTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (_currentPhase == DeliveryPhase.completed) {
        _stopTelemetryPublisher();
        return;
      }
      _emitDriverTelemetry();
    });

    if (mounted) {
      setState(() => _isTelemetryActive = true);
    }
  }

  void _stopTelemetryPublisher() {
    _telemetryTimer?.cancel();
    _telemetryTimer = null;
    if (_isTelemetryActive && mounted) {
      setState(() => _isTelemetryActive = false);
    }
  }

  Future<void> _emitDriverTelemetry() async {
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );

      _driverLat = pos.latitude;
      _driverLng = pos.longitude;
      final speedKmh = pos.speed > 0 ? (pos.speed * 3.6) : 0.0;
      _lastSpeedKmh = speedKmh;

      await _orderRepo.sendDriverLocation(
        widget.mission.id,
        latitude: pos.latitude,
        longitude: pos.longitude,
        speedKmh: speedKmh,
        heading: pos.heading >= 0 ? pos.heading : null,
      );

      if (mounted) {
        _homeController.driverGpsCoords.value =
            '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
        if (_mapReady && _pinsCollection != null) {
          await _updateMapElements();
        }
      }
    } catch (_) {}
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
        _driverLat = double.tryParse(parts[0].trim()) ?? _driverLat;
        _driverLng = double.tryParse(parts[1].trim()) ?? _driverLng;
      }
    }

    // Positions dérivées (repli) si la mission ne porte pas les vraies
    // coordonnées du fournisseur / client. Utilisées uniquement pour cadrer la
    // carte : l'UI signale alors qu'elles sont approximatives.
    final rand = Random(widget.mission.id);
    final derivedSupplierLat = _driverLat + (rand.nextDouble() * 0.012 + 0.005);
    final derivedSupplierLng = _driverLng + (rand.nextDouble() * 0.012 + 0.005);
    final derivedClientLat =
        derivedSupplierLat + (rand.nextDouble() * 0.015 + 0.008);
    final derivedClientLng =
        derivedSupplierLng - (rand.nextDouble() * 0.015 + 0.008);

    final supplierLat = widget.mission.supplierLatitude;
    final supplierLng = widget.mission.supplierLongitude;
    final clientLat = widget.mission.clientLatitude;
    final clientLng = widget.mission.clientLongitude;

    final hasSupplier = supplierLat != null && supplierLng != null;
    final hasClient = clientLat != null && clientLng != null;

    _supplierLat = hasSupplier ? supplierLat : derivedSupplierLat;
    _supplierLng = hasSupplier ? supplierLng : derivedSupplierLng;
    _clientLat = hasClient ? clientLat : derivedClientLat;
    _clientLng = hasClient ? clientLng : derivedClientLng;

    _coordsApproximate = !hasSupplier || !hasClient;
  }

  /// Tente d'obtenir la position GPS réelle du livreur ; ne bloque pas l'écran.
  Future<void> _resolveDriverPosition() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (mounted) await _updateMapElements();
        return;
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 6),
        ),
      );
      if (!mounted) return;
      _driverLat = pos.latitude;
      _driverLng = pos.longitude;
      _homeController.driverGpsCoords.value =
          '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
      await _updateMapElements();
    } catch (_) {
      // On garde la position par défaut / stockée, mais on recentre quand même.
      if (mounted) await _updateMapElements();
    }
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _routesCollection = mapWindow.map.mapObjects.addCollection();
    _pinsCollection = mapWindow.map.mapObjects.addCollection();

    setState(() => _mapReady = true);

    _updateMapElements();
  }

  /// Récupère la géométrie réelle du réseau routier via OSRM (Open Source Routing Machine)
  Future<List<mk.Point>> _fetchRoadRoutePoints(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) async {
    try {
      final base = EnvConfig.osrmBaseUrl.replaceAll(RegExp(r'/+$'), '');
      final url = Uri.parse(
        '$base/route/v1/driving/$lng1,$lat1;$lng2,$lat2?overview=full&geometries=geojson',
      );
      // 10 s : le serveur OSRM public est lent depuis l'Afrique de l'Ouest et
      // 4 s suffisaient rarement → l'itinéraire routier ne s'affichait jamais et
      // on retombait systématiquement sur l'estimation à vol d'oiseau.
      final response = await http.get(url).timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        final routes = data['routes'];
        if (data['code'] == 'Ok' && routes is List && routes.isNotEmpty) {
          final route = routes[0] as Map<String, dynamic>;
          final geometry = route['geometry'] as Map<String, dynamic>;
          final coords = geometry['coordinates'] as List?;

          if (route['distance'] != null) {
            final distKm =
                ((route['distance'] as num) / 1000).toStringAsFixed(1);
            _routeDistanceText = '$distKm km';
          }
          if (route['duration'] != null) {
            final durMin = ((route['duration'] as num) / 60).round();
            _routeDurationText = '$durMin min';
          }

          if (coords != null && coords.isNotEmpty) {
            _routeIsEstimate = false;
            return coords.map((c) {
              final pair = c as List;
              final lng = (pair[0] as num).toDouble();
              final lat = (pair[1] as num).toDouble();
              return mk.Point(latitude: lat, longitude: lng);
            }).toList();
          }
        }
      }
    } catch (e) {
      debugPrint('[RoutePlanner] OSRM routing fallback: $e');
    }

    // Repli : pas d'itinéraire routier disponible → estimation à vol d'oiseau
    // (clairement signalée comme « ~ » dans l'UI, jamais présentée comme exacte).
    final straightKm = _haversineKm(lat1, lng1, lat2, lng2);
    _routeIsEstimate = true;
    _routeDistanceText = '~ ${straightKm.toStringAsFixed(1)} km';
    _routeDurationText = null;

    final points = <mk.Point>[];
    const steps = 12;
    for (int i = 0; i <= steps; i++) {
      final t = i / steps;
      final latInterp = lat1 + (lat2 - lat1) * t;
      final lngInterp = lng1 + (lng2 - lng1) * t;

      // Création d'une courbure simulant un parcours en grille urbaine
      final offsetLat = sin(t * pi) * 0.0025;
      final offsetLng = cos(t * pi * 2) * 0.0015;

      points.add(
        mk.Point(
          latitude: latInterp + (i % 2 == 0 ? offsetLat : -offsetLat * 0.5),
          longitude: lngInterp + offsetLng,
        ),
      );
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
      pSupplier.geometry =
          mk.Point(latitude: _supplierLat, longitude: _supplierLng);

      final points = await _fetchRoadRoutePoints(
        _driverLat,
        _driverLng,
        _supplierLat,
        _supplierLng,
      );
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
      pPickup.geometry =
          mk.Point(latitude: _supplierLat, longitude: _supplierLng);

      final pClient = pins.addPlacemark();
      pClient.geometry = mk.Point(latitude: _clientLat, longitude: _clientLng);

      final points = await _fetchRoadRoutePoints(
        _supplierLat,
        _supplierLng,
        _clientLat,
        _clientLng,
      );
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

    // Le HUD lit _routeDistanceText / _routeDurationText renseignés ci-dessus.
    if (mounted) setState(() {});
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

    // Zoom adapté à la distance entre les deux points pour qu'ils restent visibles.
    final spanKm = _haversineKm(lat1, lng1, lat2, lng2);
    final double zoom = spanKm > 20
        ? 10.5
        : spanKm > 10
            ? 11.5
            : spanKm > 5
                ? 12.5
                : spanKm > 2
                    ? 13.5
                    : 14.5;

    final center = mk.CameraPosition(
      mk.Point(latitude: centerLat, longitude: centerLng),
      zoom: zoom,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      center,
      animation:
          const mk.Animation(type: mk.AnimationType.Smooth, duration: 1.0),
    );
  }

  /// Distance à vol d'oiseau en kilomètres (formule de Haversine).
  double _haversineKm(double lat1, double lng1, double lat2, double lng2) {
    const earthRadiusKm = 6371.0;
    final dLat = _deg2rad(lat2 - lat1);
    final dLng = _deg2rad(lng2 - lng1);
    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_deg2rad(lat1)) *
            cos(_deg2rad(lat2)) *
            sin(dLng / 2) *
            sin(dLng / 2);
    return earthRadiusKm * 2 * atan2(sqrt(a), sqrt(1 - a));
  }

  double _deg2rad(double deg) => deg * pi / 180.0;

  Future<void> _launchExternalNavigation(
    double destLat,
    double destLng,
    String label,
  ) async {
    final googleUrl = Uri.parse(
      'https://www.google.com/maps/dir/?api=1&destination=$destLat,$destLng',
    );
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
    final textController = TextEditingController();
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.storefront_rounded, color: AppColors.warning),
            SizedBox(width: 8),
            Text(
              'Enlèvement Magasin',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Confirmez la récupération du matériel chez ${widget.mission.artisanName ?? 'le fournisseur'}.',
              style:
                  const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: textController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: 'Code de retrait fournisseur',
                hintText: 'Communiqué par la quincaillerie',
                border:
                    OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                prefixIcon: const Icon(Icons.qr_code_scanner_rounded),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text(
              'Annuler',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.warning,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            onPressed: () async {
              Get.back();
              final ok = await _homeController.handleDriverPickupFromStore(
                widget.mission,
                textController.text.trim(),
              );
              if (!ok || !mounted) return;
              setState(() {
                _currentPhase = DeliveryPhase.delivery;
              });
              unawaited(_updateMapElements());
              unawaited(_emitDriverTelemetry());
            },
            child: const Text(
              'Valider Enlèvement',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  void _promptDeliveryValidation() {
    final textController = TextEditingController();
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.check_circle_rounded, color: AppColors.success),
            SizedBox(width: 8),
            Text(
              'Livraison Client',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Remettez les articles à ${widget.mission.clientName ?? 'Client'} et demandez le code de réception OTP.',
              style:
                  const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: textController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: 'Code de réception client (OTP)',
                hintText: 'Code affiché sur l\'app du client',
                border:
                    OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                prefixIcon: const Icon(Icons.pin_outlined),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(),
            child: const Text(
              'Annuler',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.success,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            onPressed: () async {
              Get.back();
              final ok = await _homeController.handleDriverDropoffToClient(
                widget.mission,
                textController.text.trim(),
              );
              if (!ok || !mounted) return;
              setState(() {
                _currentPhase = DeliveryPhase.completed;
              });
              _stopTelemetryPublisher();
            },
            child: const Text(
              'Confirmer Livraison',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final deliveryFee = widget.mission.montantMo > 0
        ? widget.mission.montantMo
        : (widget.mission.montantTotal > 0
            ? (widget.mission.montantTotal * 0.15).toInt()
            : 1500);

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
            icon:
                const Icon(Icons.my_location_rounded, color: AppColors.primary),
            tooltip: 'Actualiser ma position et recentrer',
            onPressed: () => _resolveDriverPosition(),
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
            const Center(
              child: CircularProgressIndicator(color: AppColors.driver),
            ),

          // ── Top Phase Indicator HUD ──
          Positioned(
            top: 12,
            left: 16,
            right: 16,
            child: Column(
              children: [
                _buildTopHud(),
                const MapOfflineNotice(
                  margin: EdgeInsets.only(top: 8),
                ),
              ],
            ),
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
        ? (widget.mission.artisanName?.isNotEmpty == true
            ? widget.mission.artisanName!
            : 'Quincaillerie Partenaire')
        : (widget.mission.clientName?.isNotEmpty == true
            ? widget.mission.clientName!
            : 'Client');

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
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
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
                    isCompleted
                        ? 'Livraison effectuée avec succès'
                        : 'Vers : $targetName',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  if (!isCompleted) ...[
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: _isTelemetryActive
                                ? const Color(0xFF10B981)
                                : const Color(0xFF94A3B8),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          _isTelemetryActive
                              ? (_lastSpeedKmh != null && _lastSpeedKmh! > 1.0
                                  ? 'Télémétrie en direct • ${_lastSpeedKmh!.toStringAsFixed(0)} km/h'
                                  : 'Télémétrie en direct • Émission 15s')
                              : 'Télémétrie GPS en attente',
                          style: const TextStyle(
                            fontSize: 10.5,
                            color: AppColors.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ],
                  if ((_coordsApproximate || _routeIsEstimate) &&
                      !isCompleted) ...[
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        const Icon(
                          Icons.info_outline_rounded,
                          size: 12,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            _coordsApproximate
                                ? 'Positions approximatives — GPS partenaire indisponible. Utilisez le bouton GPS pour la navigation.'
                                : 'Distance estimée à vol d\'oiseau (itinéraire routier indisponible).',
                            style: const TextStyle(
                              fontSize: 10.5,
                              height: 1.25,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
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
            const Icon(
              Icons.check_circle_rounded,
              color: AppColors.success,
              size: 52,
            ),
            const SizedBox(height: 10),
            const Text(
              'Course Livrée avec Succès !',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF0F172A),
              ),
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
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                onPressed: () => Get.back(),
                child: const Text(
                  'Retour à mes courses',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
            ),
          ],
        ),
      );
    }

    final isPickup = _currentPhase == DeliveryPhase.pickup;
    final targetName = isPickup
        ? (widget.mission.artisanName?.isNotEmpty == true
            ? widget.mission.artisanName!
            : 'Quincaillerie Partenaire')
        : (widget.mission.clientName?.isNotEmpty == true
            ? widget.mission.clientName!
            : 'Client');

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
                      isPickup
                          ? 'POINT D\'ENLÈVEMENT (MAGASIN)'
                          : 'DESTINATION (CLIENT)',
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
                    _launchExternalNavigation(
                      _supplierLat,
                      _supplierLng,
                      'Fournisseur',
                    );
                  } else {
                    _launchExternalNavigation(_clientLat, _clientLng, 'Client');
                  }
                },
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.navigation_rounded,
                        size: 14,
                        color: Colors.white,
                      ),
                      SizedBox(width: 4),
                      Text(
                        'GPS',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
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
                const Icon(
                  Icons.inventory_2_outlined,
                  size: 16,
                  color: AppColors.textSecondary,
                ),
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
                backgroundColor: isPickup
                    ? const Color(0xFFF59E0B)
                    : const Color(0xFF10B981),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
              onPressed: isPickup
                  ? _promptPickupValidation
                  : _promptDeliveryValidation,
              icon: Icon(
                isPickup
                    ? Icons.qr_code_scanner_rounded
                    : Icons.check_circle_outline,
                size: 20,
              ),
              label: Text(
                isPickup
                    ? 'Valider l\'Enlèvement Magasin'
                    : 'Valider la Livraison Client',
                style:
                    const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
