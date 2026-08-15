import 'dart:math';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
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

  void _updateMapElements() {
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

      // Tracé polyline
      try {
        final poly = routes.addPolyline(mk.Polyline([
          mk.Point(latitude: _driverLat, longitude: _driverLng),
          mk.Point(
            latitude: (_driverLat + _supplierLat) / 2 + 0.002,
            longitude: (_driverLng + _supplierLng) / 2 - 0.001,
          ),
          mk.Point(latitude: _supplierLat, longitude: _supplierLng),
        ]));
        poly.setStrokeColor(const Color(0xFFF59E0B)); // Orange
        poly.setStrokeWidth(4.5);
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

      // Tracé polyline
      try {
        final poly = routes.addPolyline(mk.Polyline([
          mk.Point(latitude: _supplierLat, longitude: _supplierLng),
          mk.Point(
            latitude: (_supplierLat + _clientLat) / 2 - 0.002,
            longitude: (_supplierLng + _clientLng) / 2 + 0.002,
          ),
          mk.Point(latitude: _clientLat, longitude: _clientLng),
        ]));
        poly.setStrokeColor(const Color(0xFF10B981)); // Vert
        poly.setStrokeWidth(4.5);
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
          'Course #${widget.mission.id} (${Formatters.fcfa(deliveryFee)})',
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 1,
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
          YandexMap(onMapCreated: _onMapCreated),
          if (!_mapReady)
            const Center(child: CircularProgressIndicator(color: AppColors.driver)),

          // ── Top Phase Indicator HUD ──
          Positioned(
            top: 16,
            left: 16,
            right: 16,
            child: _buildTopHud(),
          ),

          // ── Bottom Floating Action Panel ──
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: _buildBottomPanel(),
          ),
        ],
      ),
    );
  }

  Widget _buildTopHud() {
    final isPickup = _currentPhase == DeliveryPhase.pickup;
    final isCompleted = _currentPhase == DeliveryPhase.completed;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: isCompleted
                  ? AppColors.success.withValues(alpha: 0.15)
                  : isPickup
                      ? AppColors.warning.withValues(alpha: 0.15)
                      : AppColors.success.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isCompleted
                  ? Icons.check_circle_rounded
                  : isPickup
                      ? Icons.storefront_rounded
                      : Icons.delivery_dining_rounded,
              color: isCompleted
                  ? AppColors.success
                  : isPickup
                      ? AppColors.warning
                      : AppColors.success,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  isCompleted
                      ? 'COURSE TERMINÉE'
                      : isPickup
                          ? 'ÉTAPE 1/2 : RETRAIT MATÉRIEL'
                          : 'ÉTAPE 2/2 : LIVRAISON CLIENT',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.5,
                    color: isCompleted
                        ? AppColors.success
                        : isPickup
                            ? AppColors.warning
                            : AppColors.success,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  isCompleted
                      ? 'Livraison validée avec succès !'
                      : isPickup
                          ? 'Itinéraire vers ${widget.mission.artisanName ?? 'la Quincaillerie'}'
                          : 'Itinéraire vers ${widget.mission.clientName ?? 'le Client'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomPanel() {
    if (_currentPhase == DeliveryPhase.completed) {
      return Card(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        elevation: 10,
        color: Colors.white,
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 50),
              const SizedBox(height: 10),
              const Text(
                'Course Livrée avec Succès !',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 6),
              Text(
                'Votre compte a été crédité du montant de la course.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () => Get.back(),
                  child: const Text('Retour à mes courses', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final isPickup = _currentPhase == DeliveryPhase.pickup;

    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      elevation: 10,
      color: Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(18.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Details Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        isPickup ? 'POINT DE DÉPART (MAGASIN)' : 'POINT D\'ARRIVÉE (CLIENT)',
                        style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: AppColors.textSecondary),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isPickup
                            ? (widget.mission.artisanName ?? 'Quincaillerie Partenaire')
                            : (widget.mission.clientName ?? 'Client'),
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                      ),
                    ],
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: () {
                    if (isPickup) {
                      _launchExternalNavigation(_supplierLat, _supplierLng, 'Fournisseur');
                    } else {
                      _launchExternalNavigation(_clientLat, _clientLng, 'Client');
                    }
                  },
                  icon: const Icon(Icons.navigation_outlined, size: 16),
                  label: const Text('GPS', style: TextStyle(fontWeight: FontWeight.bold)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.primary,
                    side: const BorderSide(color: AppColors.primary),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            // Description articles
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.inventory_2_outlined, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      widget.mission.description ?? 'Articles divers commandés',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w600),
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
                  backgroundColor: isPickup ? AppColors.warning : AppColors.success,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
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
      ),
    );
  }
}
