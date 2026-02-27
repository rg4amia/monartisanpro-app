import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/artisan_model.dart';
import '../../../shared/widgets/score_nzassa.dart';
import '../controllers/home_controller.dart';

// ─── Constantes ──────────────────────────────────────────────────────────────

const double _kAbidjanLat = 5.3484;
const double _kAbidjanLng = -4.0169;
const double _kDefaultZoom = 14.0;

const List<Map<String, dynamic>> _kCategories = [
  {'label': 'Tous', 'icon': Icons.apps},
  {'label': 'Plomberie', 'icon': Icons.water_drop_outlined},
  {'label': 'Électricité', 'icon': Icons.bolt_outlined},
  {'label': 'Maçonnerie', 'icon': Icons.construction_outlined},
  {'label': 'Menuiserie', 'icon': Icons.chair_outlined},
  {'label': 'Peinture', 'icon': Icons.format_paint_outlined},
  {'label': 'Carrelage', 'icon': Icons.grid_on_outlined},
];

// ─── Screen ───────────────────────────────────────────────────────────────────

class ArtisanMapScreen extends StatefulWidget {
  const ArtisanMapScreen({super.key});

  @override
  State<ArtisanMapScreen> createState() => _ArtisanMapScreenState();
}

class _ArtisanMapScreenState extends State<ArtisanMapScreen> {
  mk.MapWindow? _mapWindow;
  mk.MapObjectCollection? _artisanCollection;
  mk.MapObjectCollection? _userCollection;

  /// Lie chaque PlacemarkMapObject à son ArtisanModel
  final Map<mk.PlacemarkMapObject, ArtisanModel> _placemarkIndex = {};

  /// Conserve les listeners pour éviter le GC
  final List<_ArtisanTapListener> _tapListeners = [];

  ArtisanModel? _selectedArtisan;
  bool _mapReady = false;

  @override
  void initState() {
    super.initState();
    // Charger les artisans dès l'ouverture si pas encore fait
    final c = Get.find<HomeController>();
    if (c.artisans.isEmpty) {
      c.searchByCategory(null);
    }
  }

  // ─── Lifecycle carte ────────────────────────────────────────────────────────

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _artisanCollection = mapWindow.map.mapObjects.addCollection();
    _userCollection = mapWindow.map.mapObjects.addCollection();

    setState(() => _mapReady = true);
    _centerOnUser(animated: false);
    _plotArtisans();

    // Réagir aux changements de liste d'artisans
    ever(Get.find<HomeController>().artisans, (_) => _plotArtisans());
  }

  void _onMapDispose() {
    _mapWindow = null;
    _artisanCollection = null;
    _userCollection = null;
    _placemarkIndex.clear();
    _tapListeners.clear();
  }

  // ─── Caméra ─────────────────────────────────────────────────────────────────

  void _moveCamera(double lat, double lng, double zoom,
      {bool animated = true}) {
    final mapWindow = _mapWindow;
    if (mapWindow == null) return;

    final position = mk.CameraPosition(
      mk.Point(latitude: lat, longitude: lng),
      zoom,
      0.0,
      0.0,
    );

    if (animated) {
      mapWindow.map.move(
        position,
        const mk.Animation(mk.AnimationType.smooth, 1.0),
        null,
      );
    } else {
      mapWindow.map.move(position);
    }
  }

  Future<void> _centerOnUser({bool animated = true}) async {
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      _moveCamera(pos.latitude, pos.longitude, _kDefaultZoom,
          animated: animated);
      _plotUserPosition(pos.latitude, pos.longitude);
    } catch (_) {
      _moveCamera(_kAbidjanLat, _kAbidjanLng, 12.0, animated: animated);
    }
  }

  // ─── Marqueur position utilisateur ─────────────────────────────────────────

  Future<void> _plotUserPosition(double lat, double lng) async {
    final col = _userCollection;
    if (col == null) return;
    col.clear();

    final iconBytes = await _renderUserIcon();
    final pm = col.addPlacemark();
    pm.geometry = mk.Point(latitude: lat, longitude: lng);
    pm.setIcon(
      mk.ImageProvider.fromImageData(
        data: iconBytes,
        format: mk.ImageDataFormat.png,
      ),
      const mk.PlacemarkIconStyle(scale: 1.0),
    );
  }

  // ─── Marqueurs artisans ──────────────────────────────────────────────────────

  Future<void> _plotArtisans() async {
    final col = _artisanCollection;
    if (col == null) return;

    col.clear();
    _placemarkIndex.clear();
    _tapListeners.clear();

    final artisans = Get.find<HomeController>().artisans;

    for (final artisan in artisans) {
      if (artisan.lat == null || artisan.lng == null) continue;

      final iconBytes = await _renderArtisanIcon(
        artisan.isGoldenMarker ? const Color(0xFFF39C12) : AppColors.primary,
        artisan.scoreNzassa.toString(),
        artisan.isGoldenMarker,
      );

      final pm = col.addPlacemark();
      pm.geometry = mk.Point(latitude: artisan.lat!, longitude: artisan.lng!);
      pm.setIcon(
        mk.ImageProvider.fromImageData(
          data: iconBytes,
          format: mk.ImageDataFormat.png,
        ),
        const mk.PlacemarkIconStyle(scale: 1.0),
      );

      _placemarkIndex[pm] = artisan;

      final listener = _ArtisanTapListener((tappedPm, point) {
        final found = _placemarkIndex[tappedPm];
        if (found != null) {
          setState(() => _selectedArtisan = found);
          _moveCamera(point.latitude, point.longitude, _kDefaultZoom + 1);
        }
        return true;
      });
      _tapListeners.add(listener);
      pm.addTapListener(listener);
    }
  }

  // ─── Rendu icônes Canvas ────────────────────────────────────────────────────

  Future<Uint8List> _renderArtisanIcon(
      Color color, String score, bool isGolden) async {
    const size = 80.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    // Ombre portée
    canvas.drawCircle(
      const Offset(size / 2 + 1, size / 2 + 2),
      26,
      Paint()
        ..color = Colors.black26
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4),
    );

    // Cercle principal
    canvas.drawCircle(
      Offset(size / 2, size / 2 - 4),
      26,
      Paint()..color = color,
    );

    // Pointe du marqueur
    canvas.drawPath(
      Path()
        ..moveTo(size / 2 - 8, size / 2 + 20)
        ..lineTo(size / 2 + 8, size / 2 + 20)
        ..lineTo(size / 2, size / 2 + 34)
        ..close(),
      Paint()..color = color,
    );

    // Bordure blanche
    canvas.drawCircle(
      Offset(size / 2, size / 2 - 4),
      26,
      Paint()
        ..color = Colors.white.withValues(alpha: 0.25)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2,
    );

    // Étoile dorée pour isGoldenMarker
    if (isGolden) {
      _paintText(canvas, '★', 14, Colors.white,
          Offset(size / 2, size / 2 - 18));
    }

    // Score
    _paintText(
      canvas,
      score,
      isGolden ? 13 : 16,
      Colors.white,
      Offset(size / 2, isGolden ? size / 2 - 4 : size / 2 - 4),
    );

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), size.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  Future<Uint8List> _renderUserIcon() async {
    const size = 48.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    // Halo bleu
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      22,
      Paint()..color = const Color(0xFF3498DB).withValues(alpha: 0.25),
    );
    // Point central
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      10,
      Paint()..color = const Color(0xFF3498DB),
    );
    // Bordure blanche
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      10,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3,
    );

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), size.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  void _paintText(Canvas canvas, String text, double fontSize, Color color,
      Offset center) {
    final tp = TextPainter(
      text: TextSpan(
        text: text,
        style: TextStyle(
            fontSize: fontSize,
            color: color,
            fontWeight: FontWeight.bold),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(
        canvas, center - Offset(tp.width / 2, tp.height / 2));
  }

  // ─── Build ───────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── Yandex Map ──
          mk.FlutterMapWidget(
            onMapCreated: _onMapCreated,
            onMapDispose: _onMapDispose,
          ),

          // ── Header avec retour + filtre catégories ──
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: _MapHeader(
              onBack: () => Get.back(),
              onCategorySelected: (cat) {
                setState(() => _selectedArtisan = null);
                Get.find<HomeController>().searchByCategory(cat);
              },
            ),
          ),

          // ── Compteur artisans ──
          if (_mapReady)
            Positioned(
              top: MediaQuery.of(context).padding.top + 100,
              right: 16,
              child: _ArtisanCountBadge(),
            ),

          // ── Loader ──
          Obx(() {
            if (!Get.find<HomeController>().isMapLoading.value) {
              return const SizedBox();
            }
            return const Positioned.fill(
              child: ColoredBox(
                color: Colors.black26,
                child: Center(child: CircularProgressIndicator()),
              ),
            );
          }),

          // ── Bottom sheet artisan sélectionné ──
          AnimatedPositioned(
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOutCubic,
            bottom: _selectedArtisan != null ? 0 : -260,
            left: 0,
            right: 0,
            child: _selectedArtisan != null
                ? _ArtisanBottomPanel(
                    artisan: _selectedArtisan!,
                    onClose: () => setState(() => _selectedArtisan = null),
                  )
                : const SizedBox(height: 260),
          ),
        ],
      ),

      // ── FAB Ma position ──
      floatingActionButton: Padding(
        padding: EdgeInsets.only(
          bottom: _selectedArtisan != null ? 270 : 16,
        ),
        child: FloatingActionButton(
          heroTag: 'my_location',
          backgroundColor: Colors.white,
          foregroundColor: AppColors.primary,
          onPressed: _centerOnUser,
          child: const Icon(Icons.my_location),
        ),
      ),
    );
  }
}

// ─── Header avec bouton retour + filtre catégories ────────────────────────────

class _MapHeader extends StatefulWidget {
  final VoidCallback onBack;
  final void Function(String?) onCategorySelected;
  const _MapHeader(
      {required this.onBack, required this.onCategorySelected});

  @override
  State<_MapHeader> createState() => _MapHeaderState();
}

class _MapHeaderState extends State<_MapHeader> {
  String? _selected;

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top;
    return Container(
      padding: EdgeInsets.fromLTRB(12, topPad + 8, 12, 8),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Colors.black.withValues(alpha: 0.55),
            Colors.transparent,
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Bouton retour + titre
          Row(
            children: [
              GestureDetector(
                onTap: widget.onBack,
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.9),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.arrow_back,
                      color: AppColors.primary, size: 22),
                ),
              ),
              const SizedBox(width: 12),
              const Text(
                'Artisans à proximité',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                  shadows: [Shadow(blurRadius: 4, color: Colors.black54)],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Chips de catégorie
          SizedBox(
            height: 36,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _kCategories.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final cat = _kCategories[i];
                final label = cat['label'] as String;
                final icon = cat['icon'] as IconData;
                final isAll = label == 'Tous';
                final isSelected =
                    isAll ? _selected == null : _selected == label;
                return _CategoryChip(
                  label: label,
                  icon: icon,
                  isSelected: isSelected,
                  onTap: () {
                    setState(() =>
                        _selected = isAll ? null : label);
                    widget.onCategorySelected(isAll ? null : label);
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool isSelected;
  final VoidCallback onTap;
  const _CategoryChip({
    required this.label,
    required this.icon,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary : Colors.white.withValues(alpha: 0.9),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon,
                size: 14,
                color: isSelected ? Colors.white : AppColors.primary),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Badge compteur d'artisans ────────────────────────────────────────────────

class _ArtisanCountBadge extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final count = Get.find<HomeController>().artisans.length;
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.12),
              blurRadius: 6,
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.people, size: 14, color: AppColors.primary),
            const SizedBox(width: 6),
            Text(
              '$count artisan${count > 1 ? 's' : ''}',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.bold,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      );
    });
  }
}

// ─── Bottom panel artisan sélectionné ────────────────────────────────────────

class _ArtisanBottomPanel extends StatelessWidget {
  final ArtisanModel artisan;
  final VoidCallback onClose;
  const _ArtisanBottomPanel(
      {required this.artisan, required this.onClose});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(color: Colors.black26, blurRadius: 20, offset: Offset(0, -4)),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 16),

          // Infos artisan
          Row(
            children: [
              // Avatar
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                child: Text(
                  Formatters.initial(artisan.name ?? artisan.phone),
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(width: 14),

              // Nom + métier + distance
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            artisan.name ?? 'Artisan',
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        if (artisan.isGoldenMarker)
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF39C12).withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.star,
                                    color: Color(0xFFF39C12), size: 12),
                                SizedBox(width: 3),
                                Text('Doré',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFFF39C12),
                                      fontWeight: FontWeight.bold,
                                    )),
                              ],
                            ),
                          ),
                      ],
                    ),
                    if (artisan.trade != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        artisan.trade!,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                    ],
                    if (artisan.distance != null) ...[
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.location_on_outlined,
                              size: 13, color: AppColors.textMuted),
                          const SizedBox(width: 3),
                          Text(
                            Formatters.distance(artisan.distance!),
                            style: const TextStyle(
                              color: AppColors.textMuted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),

              // Score
              ScoreNzassa(
                score: artisan.scoreNzassa,
                size: ScoreSize.medium,
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Actions
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onClose,
                  icon: const Icon(Icons.close, size: 16),
                  label: const Text('Fermer'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.textSecondary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton.icon(
                  onPressed: () {
                    onClose();
                    Get.toNamed(
                      Routes.artisanProfile,
                      arguments: artisan,
                    );
                  },
                  icon: const Icon(Icons.handyman_outlined, size: 16),
                  label: const Text('Voir le profil'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ─── Tap listener Yandex MapKit ───────────────────────────────────────────────

class _ArtisanTapListener implements mk.MapObjectTapListener {
  final bool Function(mk.MapObject, mk.Point) _onTap;
  _ArtisanTapListener(this._onTap);

  @override
  bool onMapObjectTap(mk.MapObject mapObject, mk.Point point) =>
      _onTap(mapObject, point);
}
