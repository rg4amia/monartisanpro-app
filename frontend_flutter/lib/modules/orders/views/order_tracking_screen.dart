import 'dart:async';

import 'package:flutter/material.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../shared/widgets/map_offline_notice.dart';

/// Suivi temps réel, en lecture seule, d'une livraison en cours pour le client.
///
/// Le repository `getOrderTracking` existait déjà (`GET /orders/{id}/tracking`)
/// mais n'était appelé par aucun écran : le client n'avait aucun moyen de
/// suivre son livreur après l'avoir vu assigné à sa commande. Cet écran
/// n'accède jamais au GPS de l'appareil : il affiche uniquement les positions
/// (fournisseur, client, livreur) que le backend restitue déjà à partir de la
/// télémétrie envoyée par le livreur, rafraîchies par sondage périodique au
/// même rythme que cette télémétrie (15 s).
class OrderTrackingScreen extends StatefulWidget {
  const OrderTrackingScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  final OrderRepository _repo = OrderRepository();

  static const double _kAbidjanLat = 5.3484;
  static const double _kAbidjanLng = -4.0169;

  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _pinsCollection;
  mk.MapObjectCollection? _routesCollection;
  bool _mapReady = false;
  bool _isLoading = true;
  bool _hasError = false;

  /// La caméra n'est recadrée en zoom qu'une fois : ensuite elle suit le
  /// livreur en douceur sans re-zoomer à chaque rafraîchissement.
  bool _cameraFramed = false;

  Timer? _pollTimer;

  Map<String, dynamic>? _supplier;
  Map<String, dynamic>? _client;
  Map<String, dynamic>? _driver;
  Map<String, dynamic>? _route;
  String? _status;

  @override
  void initState() {
    super.initState();
    _fetchTracking(initial: true);
    // Même cadence que l'émission de télémétrie du livreur (voir
    // DeliveryRoutePlannerScreen) : inutile de sonder plus souvent.
    _pollTimer = Timer.periodic(
      const Duration(seconds: 15),
      (_) => _fetchTracking(),
    );
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _fetchTracking({bool initial = false}) async {
    if (initial && mounted) setState(() => _isLoading = true);

    try {
      final res = await _repo.getOrderTracking(widget.orderId);
      final raw = res['data'] ?? res['tracking'];
      if (raw is! Map) {
        if (!mounted) return;
        setState(() {
          _isLoading = false;
          if (initial) _hasError = true;
        });
        return;
      }

      final map = Map<String, dynamic>.from(raw);
      if (!mounted) return;
      setState(() {
        _supplier =
            map['supplier'] is Map ? Map<String, dynamic>.from(map['supplier'] as Map) : null;
        _client =
            map['client'] is Map ? Map<String, dynamic>.from(map['client'] as Map) : null;
        _driver =
            map['driver'] is Map ? Map<String, dynamic>.from(map['driver'] as Map) : null;
        _route = map['route'] is Map
            ? Map<String, dynamic>.from(map['route'] as Map)
            : (map['routing'] is Map
                ? Map<String, dynamic>.from(map['routing'] as Map)
                : null);
        _status = map['status'] as String?;
        _isLoading = false;
        _hasError = false;
      });
      _updateMapElements();

      // La livraison est arrivée à un état final : plus rien à suivre, on
      // arrête le sondage périodique.
      if (const ['delivered', 'cancelled', 'disputed'].contains(_status)) {
        _pollTimer?.cancel();
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        if (initial) _hasError = true;
      });
    }
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _pinsCollection = mapWindow.map.mapObjects.addCollection();
    _routesCollection = mapWindow.map.mapObjects.addCollection();

    if (mounted) setState(() => _mapReady = true);

    // Cadrer Abidjan tout de suite, avant même la première réponse réseau :
    // sans ce positionnement initial la caméra reste sur celle par défaut du
    // SDK, au large du golfe de Guinée.
    _moveCamera(_kAbidjanLat, _kAbidjanLng, 12.0, animated: false);
    _updateMapElements();
  }

  double? _readCoord(Map<String, dynamic>? position, List<String> keys) {
    if (position == null) return null;
    for (final key in keys) {
      final v = position[key];
      if (v is num) return v.toDouble();
    }
    return null;
  }

  Map<String, dynamic>? _positionOf(Map<String, dynamic>? actor) {
    final pos = actor?['position'];
    return pos is Map ? Map<String, dynamic>.from(pos) : null;
  }

  void _updateMapElements() {
    final pins = _pinsCollection;
    final routes = _routesCollection;
    if (pins == null || routes == null) return;

    pins.clear();
    routes.clear();

    final supplierPos = _positionOf(_supplier);
    final clientPos = _positionOf(_client);
    final driverPos = _positionOf(_driver);

    final sLat = _readCoord(supplierPos, const ['lat', 'latitude']);
    final sLng = _readCoord(supplierPos, const ['lng', 'longitude']);
    final cLat = _readCoord(clientPos, const ['lat', 'latitude']);
    final cLng = _readCoord(clientPos, const ['lng', 'longitude']);
    final dLat = _readCoord(driverPos, const ['lat', 'latitude']);
    final dLng = _readCoord(driverPos, const ['lng', 'longitude']);

    if (sLat != null && sLng != null) {
      final p = pins.addPlacemark();
      p.geometry = mk.Point(latitude: sLat, longitude: sLng);
    }
    if (cLat != null && cLng != null) {
      final p = pins.addPlacemark();
      p.geometry = mk.Point(latitude: cLat, longitude: cLng);
    }
    if (dLat != null && dLng != null) {
      final p = pins.addPlacemark();
      p.geometry = mk.Point(latitude: dLat, longitude: dLng);
    }

    final geometry = _route?['geometry'] ?? _route?['coordinates'];
    if (geometry is List && geometry.length > 1) {
      final points = <mk.Point>[];
      for (final coord in geometry) {
        if (coord is List && coord.length >= 2) {
          final lng = (coord[0] as num).toDouble();
          final lat = (coord[1] as num).toDouble();
          points.add(mk.Point(latitude: lat, longitude: lng));
        }
      }
      if (points.length > 1) {
        try {
          final poly = routes.addPolyline();
          poly.geometry = mk.Polyline(points);
          poly.setStrokeColor(AppColors.client);
          // ignore: deprecated_member_use
          poly.strokeWidth = 5.0;
        } catch (_) {}
      }
    }

    if (!_cameraFramed) {
      if (dLat != null && dLng != null) {
        _moveCamera(dLat, dLng, 14.5);
        _cameraFramed = true;
      } else if (sLat != null && sLng != null) {
        _moveCamera(sLat, sLng, 13.0);
        _cameraFramed = true;
      } else if (cLat != null && cLng != null) {
        _moveCamera(cLat, cLng, 13.0);
        _cameraFramed = true;
      }
    } else if (dLat != null && dLng != null) {
      // Suit le livreur sans re-zoomer à chaque rafraîchissement.
      _moveCamera(dLat, dLng, null);
    }
  }

  void _moveCamera(
    double lat,
    double lng,
    double? zoom, {
    bool animated = true,
  }) {
    final mw = _mapWindow;
    if (mw == null) return;

    final resolvedZoom = zoom ?? mw.map.cameraPosition.zoom;
    final position = mk.CameraPosition(
      mk.Point(latitude: lat, longitude: lng),
      zoom: resolvedZoom,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      position,
      animation: animated
          ? const mk.Animation(type: mk.AnimationType.Smooth, duration: 1.0)
          : null,
    );
  }

  String get _driverName {
    final name = _driver?['name'];
    return name is String && name.isNotEmpty ? name : 'Livreur';
  }

  bool get _hasDriverPosition => _positionOf(_driver) != null;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text('Commande #${widget.orderId}'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Stack(
        children: [
          Positioned.fill(child: YandexMap(onMapCreated: _onMapCreated)),
          if (!_mapReady || (_isLoading && _driver == null))
            const Center(child: CircularProgressIndicator(color: AppColors.client)),

          Positioned(
            top: 12,
            left: 16,
            right: 16,
            child: Column(
              children: [
                _buildStatusCard(),
                const MapOfflineNotice(margin: EdgeInsets.only(top: 8)),
                if (_hasError) ...[
                  const SizedBox(height: 8),
                  _buildErrorBanner(),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusCard() {
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
                color: AppColors.client.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.local_shipping_outlined,
                color: AppColors.client,
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
                    _status == 'delivered'
                        ? 'Livraison terminée'
                        : _hasDriverPosition
                            ? 'Livreur en approche'
                            : 'En attente de position du livreur',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _status == 'delivered'
                        ? 'Le colis a été remis, ce suivi ne se met plus à jour.'
                        : _hasDriverPosition
                            ? _driverName
                            : 'La carte se mettra à jour dès que le livreur '
                                'transmettra sa position.',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  if (_route != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      _routeSummary(),
                      style: const TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.client,
                      ),
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

  String _routeSummary() {
    final route = _route;
    if (route == null) return '';
    final distanceKm = route['distance_km'];
    final durationMin = route['duration_min'] ?? route['duration_minutes'];
    final parts = <String>[];
    if (distanceKm is num) parts.add('${distanceKm.toStringAsFixed(1)} km');
    if (durationMin is num) parts.add('~ ${durationMin.round()} min');
    return parts.join(' • ');
  }

  Widget _buildErrorBanner() {
    return Material(
      color: const Color(0xFFB3261E),
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
        child: Row(
          children: [
            const Icon(Icons.error_outline_rounded, size: 18, color: Colors.white),
            const SizedBox(width: 10),
            const Expanded(
              child: Text(
                'Impossible de récupérer le suivi de cette livraison.',
                style: TextStyle(color: Colors.white, fontSize: 12, height: 1.3),
              ),
            ),
            TextButton(
              onPressed: () => _fetchTracking(initial: true),
              style: TextButton.styleFrom(
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 10),
              ),
              child: const Text('Réessayer'),
            ),
          ],
        ),
      ),
    );
  }
}
