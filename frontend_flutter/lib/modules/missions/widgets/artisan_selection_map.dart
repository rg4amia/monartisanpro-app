import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/image.dart' as mk_image;
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/yandex_map.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/artisan_model.dart';
import '../../../shared/widgets/score_nzassa.dart';
import '../controllers/artisan_selection_controller.dart';

abstract class _Palette {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.secondary;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
  static const warning = AppColors.accent;
}

const double _kAbidjanLat = 5.3484;
const double _kAbidjanLng = -4.0169;
const double _kDefaultZoom = 13.6;

class ArtisanSelectionMap extends StatefulWidget {
  const ArtisanSelectionMap({
    super.key,
    required this.controller,
  });

  final ArtisanSelectionController controller;

  @override
  State<ArtisanSelectionMap> createState() => _ArtisanSelectionMapState();
}

class _ArtisanSelectionMapState extends State<ArtisanSelectionMap> {
  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _artisanCollection;
  mk.MapObjectCollection? _clientCollection;

  final List<_ArtisanTapListener> _tapListeners = [];
  final Map<mk.PlacemarkMapObject, ArtisanModel> _placemarkIndex = {};

  late final Worker _artisanWorker;

  ArtisanModel? _selectedArtisan;
  bool _mapReady = false;
  bool _hasCenteredCamera = false;

  @override
  void initState() {
    super.initState();
    _artisanWorker = ever(widget.controller.artisans, (_) {
      _syncSelectedArtisan();
      if (mounted) {
        setState(() {});
      }
      _plotArtisans();
      _centerOnClient(animated: false);
    });
  }

  @override
  void dispose() {
    _artisanWorker.dispose();
    _tapListeners.clear();
    _placemarkIndex.clear();
    super.dispose();
  }

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _artisanCollection = mapWindow.map.mapObjects.addCollection();
    _clientCollection = mapWindow.map.mapObjects.addCollection();

    if (mounted) {
      setState(() => _mapReady = true);
    }

    _centerOnClient(animated: false, force: true);
    _plotArtisans();
  }

  void _moveCamera(
    double lat,
    double lng,
    double zoom, {
    bool animated = true,
  }) {
    final mapWindow = _mapWindow;
    if (mapWindow == null) return;

    mapWindow.map.move(
      mk.CameraPosition(
        mk.Point(latitude: lat, longitude: lng),
        zoom: zoom,
        azimuth: 0.0,
        tilt: 0.0,
      ),
      animation: animated
          ? const mk.Animation(
              type: mk.AnimationType.Smooth,
              duration: 0.8,
            )
          : null,
    );
  }

  Future<void> _centerOnClient({
    bool animated = true,
    bool force = false,
  }) async {
    final lat = widget.controller.clientLatitude.value;
    final lng = widget.controller.clientLongitude.value;

    if (lat != 0.0 && lng != 0.0) {
      await _plotClientMarker(lat, lng);
      if (force || !_hasCenteredCamera) {
        _moveCamera(lat, lng, _kDefaultZoom, animated: animated);
        _hasCenteredCamera = true;
      }
      return;
    }

    _clientCollection?.clear();

    if (!force && _hasCenteredCamera) {
      return;
    }

    final firstArtisan = _firstArtisanWithLocation();
    if (firstArtisan?.location != null) {
      _moveCamera(
        firstArtisan!.location!['lat']!,
        firstArtisan.location!['lng']!,
        _kDefaultZoom - 0.2,
        animated: animated,
      );
    } else {
      _moveCamera(_kAbidjanLat, _kAbidjanLng, 11.8, animated: animated);
    }
    _hasCenteredCamera = true;
  }

  Future<void> _plotClientMarker(double lat, double lng) async {
    final clientCollection = _clientCollection;
    if (clientCollection == null) return;

    clientCollection.clear();

    final bytes = await _renderClientMarker();
    if (!mounted) return;

    clientCollection.addPlacemarkWithImageStyle(
      mk.Point(latitude: lat, longitude: lng),
      mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
      const mk.IconStyle(scale: 1.0),
    );
  }

  Future<void> _plotArtisans() async {
    final artisanCollection = _artisanCollection;
    if (artisanCollection == null) return;

    artisanCollection.clear();
    _placemarkIndex.clear();
    _tapListeners.clear();

    final artisans = widget.controller.artisans.toList();

    for (final artisan in artisans) {
      final location = artisan.location;
      if (location == null) continue;

      final isSelected = artisan.id == _selectedArtisan?.id;
      final bytes = await _renderArtisanMarker(
        score: artisan.scoreProsArtisan,
        isGolden: artisan.isGoldenMarker,
        isSelected: isSelected,
      );
      if (!mounted) return;

      final placemark = artisanCollection.addPlacemarkWithImageStyle(
        mk.Point(
          latitude: location['lat']!,
          longitude: location['lng']!,
        ),
        mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
        mk.IconStyle(
          scale: 1.0,
          anchor: Point(0.5, 0.84),
        ),
      );

      _placemarkIndex[placemark] = artisan;

      final listener = _ArtisanTapListener((mapObject, point) {
        final tapped = _placemarkIndex[mapObject as mk.PlacemarkMapObject];
        if (tapped == null) {
          return false;
        }

        setState(() => _selectedArtisan = tapped);
        _moveCamera(
          point.latitude,
          point.longitude,
          _kDefaultZoom + 0.7,
        );
        _plotArtisans();
        return true;
      });

      _tapListeners.add(listener);
      placemark.addTapListener(listener);
    }
  }

  void _syncSelectedArtisan() {
    final selectedId = _selectedArtisan?.id;
    if (selectedId == null) return;

    ArtisanModel? updated;
    for (final artisan in widget.controller.artisans) {
      if (artisan.id == selectedId) {
        updated = artisan;
        break;
      }
    }

    if (updated == null) {
      if (mounted) {
        setState(() => _selectedArtisan = null);
      }
      return;
    }

    if (!identical(updated, _selectedArtisan) && mounted) {
      setState(() => _selectedArtisan = updated);
    }
  }

  ArtisanModel? _firstArtisanWithLocation() {
    for (final artisan in widget.controller.artisans) {
      if (artisan.location != null) {
        return artisan;
      }
    }

    return null;
  }

  Future<Uint8List> _renderArtisanMarker({
    required int score,
    required bool isGolden,
    required bool isSelected,
  }) async {
    const size = 120.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    final pinColor = isSelected
        ? _Palette.primary
        : isGolden
            ? _Palette.warning
            : const Color(0xFF475569);
    final badgeBg = isSelected
        ? _Palette.primary
        : isGolden
            ? const Color(0xFFFFF3CD)
            : _Palette.surface;
    final badgeFg = isSelected
        ? Colors.white
        : isGolden
            ? const Color(0xFF8A5A00)
            : _Palette.ink;

    if (isSelected) {
      canvas.drawCircle(
        const Offset(size / 2, size / 2 + 14),
        26,
        Paint()..color = _Palette.primary.withValues(alpha: 0.16),
      );
    }

    final badgeRect = RRect.fromRectAndRadius(
      const Rect.fromLTWH(36, 6, 48, 24),
      const Radius.circular(8),
    );
    canvas.drawRRect(
      badgeRect,
      Paint()..color = badgeBg,
    );
    canvas.drawRRect(
      badgeRect,
      Paint()
        ..color = pinColor.withValues(alpha: 0.2)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5,
    );

    _paintText(
      canvas,
      '$score',
      13,
      badgeFg,
      const Offset(size / 2, 18),
    );
    _paintIcon(
      canvas,
      Icons.location_on,
      isSelected ? 42 : 38,
      pinColor,
      const Offset(size / 2, size / 2 + 18),
    );

    if (isGolden && !isSelected) {
      canvas.drawCircle(
        const Offset(size / 2 + 18, size / 2 + 14),
        6,
        Paint()..color = const Color(0xFFFFF7D1),
      );
    }

    final image =
        await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  Future<Uint8List> _renderClientMarker() async {
    const size = 56.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      24,
      Paint()..color = const Color(0xFF3498DB).withValues(alpha: 0.18),
    );
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      16,
      Paint()..color = const Color(0xFF3498DB).withValues(alpha: 0.35),
    );
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      10,
      Paint()..color = const Color(0xFF3498DB),
    );
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      10,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3,
    );

    final image =
        await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  void _paintText(
    Canvas canvas,
    String text,
    double fontSize,
    Color color,
    Offset center,
  ) {
    final painter = TextPainter(
      text: TextSpan(
        text: text,
        style: TextStyle(
          fontSize: fontSize,
          color: color,
          fontWeight: FontWeight.w700,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    painter.paint(
      canvas,
      center - Offset(painter.width / 2, painter.height / 2),
    );
  }

  void _paintIcon(
    Canvas canvas,
    IconData icon,
    double size,
    Color color,
    Offset center,
  ) {
    final painter = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(icon.codePoint),
        style: TextStyle(
          fontSize: size,
          fontFamily: icon.fontFamily,
          package: icon.fontPackage,
          color: color,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    painter.paint(
      canvas,
      center - Offset(painter.width / 2, painter.height / 2),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _Palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _Palette.subtle),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Stack(
          children: [
            YandexMap(onMapCreated: _onMapCreated),
            Positioned(
              top: 14,
              left: 14,
              right: 14,
              child: _MapSummaryCard(controller: widget.controller),
            ),
            if (_mapReady)
              Positioned(
                right: 14,
                bottom: widget.controller.artisans.isEmpty
                    ? 110
                    : _selectedArtisan != null
                        ? 250
                        : 132,
                child: Column(
                  children: [
                    _MapControlButton(
                      icon: Icons.add,
                      onTap: () {
                        final position = _mapWindow?.map.cameraPosition;
                        if (position == null) return;

                        _moveCamera(
                          position.target.latitude,
                          position.target.longitude,
                          position.zoom + 1,
                        );
                      },
                    ),
                    const SizedBox(height: 8),
                    _MapControlButton(
                      icon: Icons.remove,
                      onTap: () {
                        final position = _mapWindow?.map.cameraPosition;
                        if (position == null) return;

                        _moveCamera(
                          position.target.latitude,
                          position.target.longitude,
                          position.zoom - 1,
                        );
                      },
                    ),
                    const SizedBox(height: 8),
                    _MapControlButton(
                      icon: Icons.my_location,
                      iconColor: _Palette.primary,
                      onTap: () => _centerOnClient(force: true),
                    ),
                  ],
                ),
              ),
            Positioned(
              left: 16,
              right: 16,
              bottom: 16,
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 220),
                child: widget.controller.artisans.isEmpty
                    ? const _MapEmptyCard()
                    : _selectedArtisan != null
                        ? _SelectedArtisanCard(
                            key: ValueKey(_selectedArtisan!.id),
                            artisan: _selectedArtisan!,
                            onClose: () {
                              setState(() => _selectedArtisan = null);
                              _plotArtisans();
                            },
                            onProfile: () => Get.toNamed(
                              Routes.artisanProfile,
                              arguments: _selectedArtisan,
                            ),
                            onChoose: () => widget.controller
                                .selectArtisan(_selectedArtisan!),
                          )
                        : const _MapHintCard(key: ValueKey('map-hint')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MapSummaryCard extends StatelessWidget {
  const _MapSummaryCard({
    required this.controller,
  });

  final ArtisanSelectionController controller;

  @override
  Widget build(BuildContext context) {
    final locationText = controller.locationAddress.value.isNotEmpty
        ? controller.locationAddress.value
        : controller.locationDetail.value.isNotEmpty
            ? controller.locationDetail.value
            : 'Position du client';
    final artisansCount = controller.artisans.length;
    final hasNoNearby = artisansCount > 0 && (controller.artisans.first.distanceMetres ?? 0.0) > 5000;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _Palette.surface.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _Palette.subtle),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Carte des artisans',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _Palette.ink,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: _Palette.primaryLight,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  '$artisansCount disponible${artisansCount > 1 ? 's' : ''}',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _Palette.primary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.place_outlined,
                size: 16,
                color: _Palette.muted,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  locationText,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    color: _Palette.ink,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(
                Icons.privacy_tip_outlined,
                size: 15,
                color: _Palette.muted,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  controller.nightIntervention.value
                      ? 'Affichage limite aux artisans disponibles la nuit. Positions approximatives.'
                      : 'Positions approximatives affichees pour proteger les artisans.',
                  style: const TextStyle(
                    fontSize: 12,
                    color: _Palette.muted,
                    height: 1.3,
                  ),
                ),
              ),
            ],
          ),
          if (hasNoNearby) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFC0842C).withOpacity(0.08),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFC0842C).withOpacity(0.2)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.info_outline, color: Color(0xFFC0842C), size: 16),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Aucun artisan à moins de 5 km. Recherche élargie à d\'autres communes.',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: _Palette.ink,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _MapControlButton extends StatelessWidget {
  const _MapControlButton({
    required this.icon,
    required this.onTap,
    this.iconColor,
  });

  final IconData icon;
  final VoidCallback onTap;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          color: _Palette.surface,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: _Palette.ink.withValues(alpha: 0.14),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Icon(
          icon,
          size: 22,
          color: iconColor ?? _Palette.muted,
        ),
      ),
    );
  }
}

class _MapHintCard extends StatelessWidget {
  const _MapHintCard({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _Palette.surface.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.subtle),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.08),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: const Row(
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              color: _Palette.primaryLight,
              shape: BoxShape.circle,
            ),
            child: Padding(
              padding: EdgeInsets.all(10),
              child: Icon(
                Icons.touch_app_outlined,
                color: _Palette.primary,
                size: 22,
              ),
            ),
          ),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Touchez un repere sur la carte pour voir le profil rapide et choisir un artisan.',
              style: TextStyle(
                fontSize: 13,
                height: 1.35,
                color: _Palette.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapEmptyCard extends StatelessWidget {
  const _MapEmptyCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _Palette.surface.withValues(alpha: 0.98),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _Palette.subtle),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.08),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: const Row(
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              color: _Palette.primaryLight,
              borderRadius: BorderRadius.all(Radius.circular(12)),
            ),
            child: Padding(
              padding: EdgeInsets.all(10),
              child: Icon(
                Icons.search_off_rounded,
                color: _Palette.primary,
                size: 24,
              ),
            ),
          ),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Aucun artisan geolocalise n\'a ete trouve dans cette zone pour le moment.',
              style: TextStyle(
                fontSize: 13,
                height: 1.35,
                color: _Palette.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SelectedArtisanCard extends StatelessWidget {
  const _SelectedArtisanCard({
    super.key,
    required this.artisan,
    required this.onClose,
    required this.onProfile,
    required this.onChoose,
  });

  final ArtisanModel artisan;
  final VoidCallback onClose;
  final VoidCallback onProfile;
  final VoidCallback onChoose;

  @override
  Widget build(BuildContext context) {
    final subtitle = artisan.distance ??
        artisan.locationLabel ??
        artisan.commune ??
        'Position approximative';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _Palette.surface.withValues(alpha: 0.98),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: artisan.isGoldenMarker
              ? _Palette.warning.withValues(alpha: 0.34)
              : _Palette.subtle,
        ),
        boxShadow: [
          BoxShadow(
            color: _Palette.ink.withValues(alpha: 0.12),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: _Palette.primaryLight,
                  borderRadius: BorderRadius.circular(14),
                  image: artisan.photo != null
                      ? DecorationImage(
                          image: NetworkImage(artisan.photo!),
                          fit: BoxFit.cover,
                        )
                      : null,
                ),
                child: artisan.photo == null
                    ? const Icon(
                        Icons.person,
                        color: _Palette.primary,
                        size: 28,
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      artisan.name ?? 'Artisan',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: _Palette.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      artisan.trade ?? 'Metier non renseigne',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        color: _Palette.muted,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(
                          Icons.place_outlined,
                          size: 15,
                          color: _Palette.muted,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            subtitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              color: _Palette.muted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Column(
                children: [
                  GestureDetector(
                    onTap: onClose,
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: _Palette.bg,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.close,
                        size: 18,
                        color: _Palette.muted,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ScoreProsArtisanBadge(score: artisan.scoreProsArtisan),
                ],
              ),
            ],
          ),
          if (artisan.isGoldenMarker || artisan.nightInterventionAvailable) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (artisan.isGoldenMarker)
                  _InfoChip(
                    icon: Icons.stars_rounded,
                    label: 'Artisan d\'elite',
                    color: _Palette.warning,
                    background: const Color(0xFFFFF7D1),
                  ),
                if (artisan.nightInterventionAvailable)
                  _InfoChip(
                    icon: Icons.nightlight_round,
                    label: 'Disponible la nuit',
                    color: _Palette.primary,
                    background: _Palette.primaryLight,
                  ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(
                Icons.privacy_tip_outlined,
                size: 14,
                color: _Palette.muted,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Position approximative affichee pour proteger l\'artisan.',
                  style: const TextStyle(
                    fontSize: 12,
                    color: _Palette.muted,
                    height: 1.3,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onProfile,
                  icon: const Icon(Icons.person_outline, size: 16),
                  label: const Text('Profil'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _Palette.primary,
                    side: BorderSide(
                      color: _Palette.primary.withValues(alpha: 0.28),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: onChoose,
                  icon: const Icon(Icons.handshake, size: 16),
                  label: const Text('Choisir'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _Palette.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({
    required this.icon,
    required this.label,
    required this.color,
    required this.background,
  });

  final IconData icon;
  final String label;
  final Color color;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _ArtisanTapListener implements mk.MapObjectTapListener {
  _ArtisanTapListener(this._onTap);

  final bool Function(mk.MapObject, mk.Point) _onTap;

  @override
  bool onMapObjectTap(mk.MapObject mapObject, mk.Point point) =>
      _onTap(mapObject, point);
}
