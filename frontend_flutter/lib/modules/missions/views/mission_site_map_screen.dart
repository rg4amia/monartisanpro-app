import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/image.dart' as mk_image;
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_site_map.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../../shared/widgets/map_offline_notice.dart';

const double _kAbidjanLat = 5.3484;
const double _kAbidjanLng = -4.0169;

/// Carte du chantier d'une mission, pour l'artisan (et le client) :
/// - le repère du client ayant financé la mission (séquestre constitué) ;
/// - les fournisseurs chez qui des matériaux ont été retirés (via J-Codes).
///
/// Argument de route : l'`id` de la mission (`int`).
class MissionSiteMapScreen extends StatefulWidget {
  const MissionSiteMapScreen({super.key});

  @override
  State<MissionSiteMapScreen> createState() => _MissionSiteMapScreenState();
}

class _MissionSiteMapScreenState extends State<MissionSiteMapScreen> {
  final MissionRepository _repository = MissionRepository();

  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _collection;
  final List<_TapListener> _tapListeners = [];

  int? _missionId;
  MissionSiteMap? _data;
  bool _loading = true;
  String? _error;
  bool _mapReady = false;
  bool _plotted = false;

  @override
  void initState() {
    super.initState();
    final arg = Get.arguments;
    if (arg is int) {
      _missionId = arg;
    } else if (arg is Map && arg['missionId'] is int) {
      _missionId = arg['missionId'] as int;
    }
    _load();
  }

  @override
  void dispose() {
    _tapListeners.clear();
    super.dispose();
  }

  Future<void> _load() async {
    final id = _missionId;
    if (id == null) {
      setState(() {
        _loading = false;
        _error = 'Mission introuvable.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _repository.getSiteMap(id);
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
      _plotIfReady();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Impossible de charger la carte du chantier.';
      });
    }
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _collection = mapWindow.map.mapObjects.addCollection();
    if (mounted) setState(() => _mapReady = true);
    _moveCamera(_kAbidjanLat, _kAbidjanLng, 11.5, animated: false);
    _plotIfReady();
  }

  void _plotIfReady() {
    if (!_mapReady || _data == null || _plotted) return;
    _plotted = true;
    _plotMarkers(_data!);
  }

  void _moveCamera(double lat, double lng, double zoom, {bool animated = true}) {
    _mapWindow?.map.move(
      mk.CameraPosition(
        mk.Point(latitude: lat, longitude: lng),
        zoom: zoom,
        azimuth: 0.0,
        tilt: 0.0,
      ),
      animation: animated
          ? const mk.Animation(type: mk.AnimationType.Smooth, duration: 0.8)
          : null,
    );
  }

  Future<void> _plotMarkers(MissionSiteMap data) async {
    final col = _collection;
    if (col == null) return;
    col.clear();
    _tapListeners.clear();

    final points = <mk.Point>[];

    if (data.client != null) {
      final c = data.client!;
      final bytes = await _renderMarker(
        Icons.home_rounded,
        AppColors.client,
        label: 'Chantier',
      );
      if (!mounted || _collection == null) return;
      col.addPlacemarkWithImageStyle(
        mk.Point(latitude: c.lat, longitude: c.lng),
        mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
        mk.IconStyle(scale: 1.0, anchor: Point(0.5, 0.88)),
      );
      points.add(mk.Point(latitude: c.lat, longitude: c.lng));
    }

    for (final s in data.suppliers) {
      final bytes = await _renderMarker(
        Icons.storefront_rounded,
        AppColors.success,
        label: s.name,
      );
      if (!mounted || _collection == null) return;
      final pm = col.addPlacemarkWithImageStyle(
        mk.Point(latitude: s.lat, longitude: s.lng),
        mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
        mk.IconStyle(scale: 1.0, anchor: Point(0.5, 0.88)),
      );
      final listener = _TapListener((_, __) {
        _showSupplierSheet(s);
        return true;
      });
      _tapListeners.add(listener);
      pm.addTapListener(listener);
      points.add(mk.Point(latitude: s.lat, longitude: s.lng));
    }

    _fitCamera(points);
  }

  void _fitCamera(List<mk.Point> points) {
    if (points.isEmpty) return;
    if (points.length == 1) {
      _moveCamera(points.first.latitude, points.first.longitude, 15.0);
      return;
    }
    var minLat = points.first.latitude, maxLat = points.first.latitude;
    var minLng = points.first.longitude, maxLng = points.first.longitude;
    for (final p in points) {
      minLat = min(minLat, p.latitude);
      maxLat = max(maxLat, p.latitude);
      minLng = min(minLng, p.longitude);
      maxLng = max(maxLng, p.longitude);
    }
    final spanLat = maxLat - minLat;
    final spanLng = maxLng - minLng;
    final span = max(spanLat, spanLng);
    final zoom = span > 0.2
        ? 10.0
        : span > 0.1
            ? 11.5
            : span > 0.05
                ? 12.5
                : span > 0.02
                    ? 13.5
                    : 14.5;
    _moveCamera((minLat + maxLat) / 2, (minLng + maxLng) / 2, zoom);
  }

  // ── Rendu d'icône (Canvas → PNG) ────────────────────────────────────────────

  Future<Uint8List> _renderMarker(
    IconData icon,
    Color color, {
    required String label,
  }) async {
    const size = 128.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    // Étiquette
    final tp = TextPainter(
      text: TextSpan(
        text: label.length > 18 ? '${label.substring(0, 17)}…' : label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Colors.white,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: size);
    final labelW = tp.width + 16;
    final labelRect = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(size / 2, 15),
        width: labelW,
        height: 24,
      ),
      const Radius.circular(8),
    );
    canvas.drawRRect(labelRect, Paint()..color = color);
    tp.paint(canvas, Offset(size / 2 - tp.width / 2, 15 - tp.height / 2));

    // Pointe / pin
    _paintIcon(canvas, Icons.location_on, 44, color, Offset(size / 2, size / 2 + 22));
    _paintIcon(canvas, icon, 18, Colors.white, Offset(size / 2, size / 2 + 12));

    final img =
        await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  void _paintIcon(
    Canvas canvas,
    IconData icon,
    double s,
    Color color,
    Offset center,
  ) {
    final tp = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(icon.codePoint),
        style: TextStyle(
          fontSize: s,
          fontFamily: icon.fontFamily,
          package: icon.fontPackage,
          color: color,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, center - Offset(tp.width / 2, tp.height / 2));
  }

  // ── Feuille détail fournisseur ─────────────────────────────────────────────

  void _showSupplierSheet(SiteMapSupplier s) {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.border,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: const BoxDecoration(
                    color: AppColors.supplierSoft,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.storefront_rounded,
                    color: AppColors.success,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    s.name,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _sheetRow(
              Icons.qr_code_2_rounded,
              '${s.jcodeCount} J-Code${s.jcodeCount > 1 ? 's' : ''} retiré${s.jcodeCount > 1 ? 's' : ''}',
            ),
            if (s.montant > 0)
              _sheetRow(
                Icons.payments_outlined,
                'Matériaux : ${Formatters.fcfa(s.montant)}',
              ),
          ],
        ),
      ),
    );
  }

  Widget _sheetRow(IconData icon, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Row(
          children: [
            Icon(icon, size: 16, color: AppColors.textSecondary),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                text,
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ),
      );

  // ── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Carte du chantier'),
        backgroundColor: AppColors.surface,
        surfaceTintColor: AppColors.surface,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Actualiser',
            onPressed: _loading ? null : _load,
          ),
        ],
      ),
      body: Stack(
        children: [
          Positioned.fill(child: YandexMap(onMapCreated: _onMapCreated)),
          const Positioned(
            top: 12,
            left: 12,
            right: 12,
            child: MapOfflineNotice(margin: EdgeInsets.zero),
          ),
          if (_loading)
            const Center(child: CircularProgressIndicator())
          else if (_error != null)
            _CenteredCard(
              icon: Icons.error_outline_rounded,
              title: _error!,
              actionLabel: 'Réessayer',
              onAction: _load,
            )
          else if (_data != null && !_data!.hasAnyPoint)
            const _CenteredCard(
              icon: Icons.map_outlined,
              title: 'Aucune position à afficher pour le moment.\n'
                  'La position du client apparaît une fois la mission financée ; '
                  'les fournisseurs après le retrait des premiers J-Codes.',
            )
          else if (_data != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 16,
              child: _LegendCard(data: _data!),
            ),
        ],
      ),
    );
  }
}

class _LegendCard extends StatelessWidget {
  const _LegendCard({required this.data});

  final MissionSiteMap data;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.4,
      ),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface.withValues(alpha: 0.98),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.shadowColor,
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ListView(
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        children: [
          if (data.client != null) ...[
            _row(
              color: AppColors.client,
              icon: Icons.home_rounded,
              title: data.client!.name?.isNotEmpty == true
                  ? data.client!.name!
                  : 'Chantier du client',
              subtitle: data.client!.address ?? 'Position du client',
            ),
            if (data.suppliers.isNotEmpty)
              const Divider(height: 16, color: AppColors.border),
          ],
          if (data.suppliers.isEmpty && data.client != null)
            const Text(
              'Aucun fournisseur rattaché : aucun J-Code n\'a encore été retiré.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ...data.suppliers.map(
            (s) => Padding(
              padding: const EdgeInsets.only(top: 4),
              child: _row(
                color: AppColors.success,
                icon: Icons.storefront_rounded,
                title: s.name,
                subtitle:
                    '${s.jcodeCount} J-Code${s.jcodeCount > 1 ? 's' : ''}'
                    '${s.montant > 0 ? ' • ${Formatters.fcfa(s.montant)}' : ''}',
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _row({
    required Color color,
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.14),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, size: 16, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _CenteredCard extends StatelessWidget {
  const _CenteredCard({
    required this.icon,
    required this.title,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        margin: const EdgeInsets.all(24),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 32, color: AppColors.textSecondary),
            const SizedBox(height: 12),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                height: 1.4,
                color: AppColors.textPrimary,
              ),
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 14),
              FilledButton(
                onPressed: onAction,
                child: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TapListener implements mk.MapObjectTapListener {
  _TapListener(this._onTap);

  final bool Function(mk.MapObject, mk.Point) _onTap;

  @override
  bool onMapObjectTap(mk.MapObject mapObject, mk.Point point) =>
      _onTap(mapObject, point);
}
