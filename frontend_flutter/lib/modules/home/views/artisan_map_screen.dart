import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import 'package:yandex_maps_mapkit/mapkit.dart' as mk;
import 'package:yandex_maps_mapkit/image.dart' as mk_image;
import 'package:yandex_maps_mapkit/yandex_map.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/artisan_model.dart';
import '../../../shared/widgets/score_nzassa.dart';
import '../controllers/home_controller.dart';

// ─── Constantes ───────────────────────────────────────────────────────────────

const double _kAbidjanLat = 5.3484;
const double _kAbidjanLng = -4.0169;
const double _kDefaultZoom = 14.0;

const List<Map<String, dynamic>> _kCategories = [
  {'label': 'Mieux Notés', 'icon': Icons.stars},
  {'label': 'Tailleurs', 'icon': null},
  {'label': 'Charpentiers', 'icon': null},
  {'label': 'Plombiers', 'icon': null},
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

  /// Conserve les listeners pour éviter le GC (FFI binding)
  final List<_ArtisanTapListener> _tapListeners = [];

  /// Lie chaque PlacemarkMapObject → ArtisanModel via userData
  final Map<mk.PlacemarkMapObject, ArtisanModel> _placemarkIndex = {};

  ArtisanModel? _selectedArtisan;
  bool _mapReady = false;

  @override
  void initState() {
    super.initState();
    final c = Get.find<HomeController>();
    if (c.artisans.isEmpty) c.searchByCategory(null);
  }

  // ─── Lifecycle carte ────────────────────────────────────────────────────────

  void _onMapCreated(mk.MapWindow mapWindow) {
    _mapWindow = mapWindow;
    _artisanCollection = mapWindow.map.mapObjects.addCollection();
    _userCollection = mapWindow.map.mapObjects.addCollection();

    if (mounted) setState(() => _mapReady = true);

    _centerOnUser(animated: false);
    _plotArtisans();

    // Réagir aux changements de liste
    ever(Get.find<HomeController>().artisans, (_) => _plotArtisans());
  }

  // ─── Caméra ──────────────────────────────────────────────────────────────────

  void _moveCamera(double lat, double lng, double zoom,
      {bool animated = true}) {
    final mw = _mapWindow;
    if (mw == null) return;

    final position = mk.CameraPosition(
      mk.Point(latitude: lat, longitude: lng),
      zoom: zoom,
      azimuth: 0.0,
      tilt: 0.0,
    );

    mw.map.move(
      position,
      animation: animated
          ? const mk.Animation(
              type: mk.AnimationType.Smooth,
              duration: 1.0,
            )
          : null,
    );
  }

  Future<void> _centerOnUser({bool animated = true}) async {
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings:
            const LocationSettings(accuracy: LocationAccuracy.high),
      );
      _moveCamera(pos.latitude, pos.longitude, _kDefaultZoom,
          animated: animated);
      _plotUserPosition(pos.latitude, pos.longitude);
    } catch (_) {
      _moveCamera(_kAbidjanLat, _kAbidjanLng, 12.0, animated: animated);
    }
  }

  // ─── Marqueur position utilisateur ──────────────────────────────────────────

  Future<void> _plotUserPosition(double lat, double lng) async {
    final col = _userCollection;
    if (col == null) return;
    col.clear();

    final bytes = await _renderUserIcon();
    col.addPlacemarkWithImageStyle(
      mk.Point(latitude: lat, longitude: lng),
      mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
      const mk.IconStyle(scale: 1.0),
    );
  }

  // ─── Marqueurs artisans ───────────────────────────────────────────────────────

  Future<void> _plotArtisans() async {
    final col = _artisanCollection;
    if (col == null) return;

    col.clear();
    _placemarkIndex.clear();
    _tapListeners.clear();

    final artisans = Get.find<HomeController>().artisans;

    for (final artisan in artisans) {
      if (artisan.location == null) continue;
      final lat = artisan.location!['lat']!;
      final lng = artisan.location!['lng']!;

      final color =
          artisan.isGoldenMarker ? const Color(0xFFFBBF24) : const Color(0xFF64748B);
      final bytes = await _renderArtisanIcon(
          color, artisan.scoreNzassa.toString(), artisan.isGoldenMarker);

      final pm = col.addPlacemarkWithImageStyle(
        mk.Point(latitude: lat, longitude: lng),
        mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
        mk.IconStyle(
          scale: 1.0,
          anchor: Point(0.5, 0.83),
        ),
      );

      _placemarkIndex[pm] = artisan;

      final listener = _ArtisanTapListener((obj, point) {
        final found = _placemarkIndex[obj as mk.PlacemarkMapObject];
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

  // ─── Rendu icônes via Canvas ─────────────────────────────────────────────────

  Future<Uint8List> _renderArtisanIcon(
      Color color, String score, bool isGolden) async {
    const size = 100.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    if (isGolden) {
      // Draw score badge
      final badgeWidth = 40.0;
      final badgeHeight = 22.0;
      final badgeRect = Rect.fromCenter(
        center: Offset(size / 2, badgeHeight / 2 + 5),
        width: badgeWidth,
        height: badgeHeight,
      );
      canvas.drawRRect(
        RRect.fromRectAndRadius(badgeRect, const Radius.circular(6)),
        Paint()
          ..color = const Color(0xFFFBBF24) // amber-400
          ..style = PaintingStyle.fill,
      );
      _paintText(canvas, score, 12, const Color(0xFF451A03),
          Offset(size / 2, badgeHeight / 2 + 5));

      // Draw location icon
      _paintIcon(canvas, Icons.location_on, 36, const Color(0xFFF59E0B),
          const Offset(size / 2, size / 2 + 15));
    } else {
      // Just location icon for others
      _paintIcon(canvas, Icons.location_on, 36, const Color(0xFF64748B),
          const Offset(size / 2, size / 2 + 15));
    }

    final img =
        await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  Future<Uint8List> _renderUserIcon() async {
    const size = 56.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    // Ombre
    canvas.drawCircle(
      const Offset(size / 2 + 1, size / 2 + 2),
      24,
      Paint()
        ..color = Colors.black.withValues(alpha: 0.2)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5),
    );

    // Cercle extérieur pulsant
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      24,
      Paint()..color = const Color(0xFF3498DB).withValues(alpha: 0.2),
    );

    // Cercle intermédiaire
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      16,
      Paint()..color = const Color(0xFF3498DB).withValues(alpha: 0.4),
    );

    // Cercle central
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      11,
      Paint()..color = const Color(0xFF3498DB),
    );

    // Bordure blanche
    canvas.drawCircle(
      const Offset(size / 2, size / 2),
      11,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3,
    );

    final img =
        await recorder.endRecording().toImage(size.toInt(), size.toInt());
    final data = await img.toByteData(format: ui.ImageByteFormat.png);
    return data!.buffer.asUint8List();
  }

  void _paintText(
      Canvas canvas, String text, double fontSize, Color color, Offset center) {
    final tp = TextPainter(
      text: TextSpan(
          text: text,
          style: TextStyle(
              fontSize: fontSize, color: color, fontWeight: FontWeight.bold)),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, center - Offset(tp.width / 2, tp.height / 2));
  }

  void _paintIcon(
      Canvas canvas, IconData icon, double size, Color color, Offset center) {
    final tp = TextPainter(
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
    tp.paint(canvas, center - Offset(tp.width / 2, tp.height / 2));
  }

  // ─── Build ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          // ── Yandex Map ──
          YandexMap(onMapCreated: _onMapCreated),

          // ── Header : retour + recherche + filtre catégories ──
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: _MapHeader(
              onBack: () => Get.back(),
              onSearch: (query) {
                // TODO: Implement search functionality
                print('Search: $query');
              },
              onCategorySelected: (cat) {
                setState(() => _selectedArtisan = null);
                Get.find<HomeController>().searchByCategory(cat);
              },
            ),
          ),

          // ── Zoom Controls & My Location ──
          if (_mapReady)
            Positioned(
              right: 16,
              bottom: _selectedArtisan != null ? 300 : 100,
              child: Column(
                children: [
                  _MapControlButton(
                    icon: Icons.add,
                    onTap: () {
                      final pos = _mapWindow?.map.cameraPosition;
                      if (pos != null) {
                        _moveCamera(pos.target.latitude, pos.target.longitude,
                            pos.zoom + 1);
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  _MapControlButton(
                    icon: Icons.remove,
                    onTap: () {
                      final pos = _mapWindow?.map.cameraPosition;
                      if (pos != null) {
                        _moveCamera(pos.target.latitude, pos.target.longitude,
                            pos.zoom - 1);
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  _MapControlButton(
                    icon: Icons.my_location,
                    iconColor: AppColors.primary,
                    onTap: _centerOnUser,
                  ),
                ],
              ),
            ),

          // ── Indicateur de chargement ──
          Obx(() {
            final loading = Get.find<HomeController>().isMapLoading.value;
            return loading
                ? const Positioned.fill(
                    child: ColoredBox(
                      color: Colors.black26,
                      child: Center(child: CircularProgressIndicator()),
                    ),
                  )
                : const SizedBox.shrink();
          }),

          // ── Bottom panel artisan sélectionné ──
          AnimatedPositioned(
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOutCubic,
            bottom: _selectedArtisan != null ? 0 : -280,
            left: 0,
            right: 0,
            child: _selectedArtisan != null
                ? _ArtisanBottomPanel(
                    artisan: _selectedArtisan!,
                    onClose: () => setState(() => _selectedArtisan = null),
                  )
                : const SizedBox(height: 280),
          ),
        ],
      ),

    );
  }
}

class _MapControlButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final Color? iconColor;

  const _MapControlButton({
    required this.icon,
    required this.onTap,
    this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Icon(icon, color: iconColor ?? const Color(0xFF64748B), size: 24),
      ),
    );
  }
}

// ─── Header carte ─────────────────────────────────────────────────────────────

class _MapHeader extends StatefulWidget {
  final VoidCallback onBack;
  final void Function(String) onSearch;
  final void Function(String?) onCategorySelected;
  const _MapHeader({
    required this.onBack,
    required this.onSearch,
    required this.onCategorySelected,
  });

  @override
  State<_MapHeader> createState() => _MapHeaderState();
}

class _MapHeaderState extends State<_MapHeader> {
  String? _selected;
  final _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

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
            Colors.black.withValues(alpha: 0.6),
            Colors.transparent,
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              // Bouton retour
              GestureDetector(
                onTap: widget.onBack,
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black12,
                        blurRadius: 10,
                        offset: Offset(0, 4),
                      ),
                    ],
                  ),
                  child: const Icon(Icons.arrow_back,
                      color: Color(0xFF64748B), size: 22),
                ),
              ),
              const SizedBox(width: 12),

              // Barre de recherche
              Expanded(
                child: Container(
                  height: 44,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black12,
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: TextField(
                    controller: _searchCtrl,
                    decoration: InputDecoration(
                      hintText: 'Rechercher des artisans...',
                      hintStyle: const TextStyle(
                        color: Color(0xFF94A3B8),
                        fontSize: 14,
                      ),
                      prefixIcon: const Icon(Icons.search,
                          size: 20, color: Color(0xFF94A3B8)),
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    onSubmitted: widget.onSearch,
                  ),
                ),
              ),
              const SizedBox(width: 8),

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
                final icon = cat['icon'] as IconData?;
                final isSelected = _selected == label || (_selected == null && i == 0);
                return _CategoryChip(
                  label: label,
                  icon: icon,
                  isSelected: isSelected,
                  onTap: () {
                    setState(() => _selected = label);
                    widget.onCategorySelected(label);
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
  final IconData? icon;
  final bool isSelected;
  final VoidCallback onTap;
  const _CategoryChip(
      {required this.label,
      this.icon,
      required this.isSelected,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        height: 36,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: isSelected
              ? const Color(0xFF4F46E5)
              : Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon,
                  size: 16,
                  color: isSelected ? Colors.white : const Color(0xFF64748B)),
              const SizedBox(width: 8),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Bottom panel artisan ─────────────────────────────────────────────────────

class _ArtisanBottomPanel extends StatelessWidget {
  final ArtisanModel artisan;
  final VoidCallback onClose;
  const _ArtisanBottomPanel({required this.artisan, required this.onClose});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onVerticalDragEnd: (details) {
        if (details.primaryVelocity! > 500) {
          onClose();
        }
      },
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 20,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Image
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(12),
                    image: artisan.photo != null
                        ? DecorationImage(
                            image: NetworkImage(artisan.photo!),
                            fit: BoxFit.cover,
                          )
                        : null,
                  ),
                  child: artisan.photo == null
                      ? const Icon(Icons.person, color: Colors.grey, size: 40)
                      : null,
                ),
                const SizedBox(width: 16),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (artisan.isGoldenMarker)
                        const Text(
                          'ARTISAN D\'ÉLITE',
                          style: TextStyle(
                            color: Color(0xFF4F46E5),
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 1,
                          ),
                        ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              artisan.name ?? 'Artisan',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 18,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFF7ED),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFFFEDD5)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.stars,
                                    color: Color(0xFFF59E0B), size: 14),
                                const SizedBox(width: 4),
                                Text(
                                  artisan.scoreNzassa.toString(),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF9A3412),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.near_me,
                              size: 14, color: Color(0xFF64748B)),
                          const SizedBox(width: 4),
                          Text(
                            artisan.distance ?? 'à 0,8 km',
                            style: const TextStyle(
                                color: Color(0xFF64748B), fontSize: 13),
                          ),
                          const SizedBox(width: 8),
                          const Icon(Icons.circle, size: 4, color: Color(0xFFCBD5E1)),
                          const SizedBox(width: 8),
                          Text(
                            artisan.trade ?? 'Tailleur',
                            style: const TextStyle(
                                color: Color(0xFF64748B), fontSize: 13),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      onClose();
                      Get.toNamed(Routes.artisanProfile, arguments: artisan);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF4F46E5),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      elevation: 0,
                    ),
                    child: const Text('Voir le Profil',
                        style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
                const SizedBox(width: 12),
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEEF2FF),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.chat_bubble, color: Color(0xFF4F46E5)),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 24,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFF4F46E5),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 4),
                Container(
                  width: 6,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 4),
                Container(
                  width: 6,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Tap listener (FFI binding — doit rester en mémoire) ──────────────────────

class _ArtisanTapListener implements mk.MapObjectTapListener {
  final bool Function(mk.MapObject, mk.Point) _onTap;
  _ArtisanTapListener(this._onTap);

  @override
  bool onMapObjectTap(mk.MapObject mapObject, mk.Point point) =>
      _onTap(mapObject, point);
}
