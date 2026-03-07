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
          artisan.isGoldenMarker ? const Color(0xFFF39C12) : AppColors.primary;
      final bytes = await _renderArtisanIcon(
          color, artisan.scoreNzassa.toString(), artisan.isGoldenMarker);

      final pm = col.addPlacemarkWithImageStyle(
        mk.Point(latitude: lat, longitude: lng),
        mk_image.ImageProvider.fromImageProvider(MemoryImage(bytes)),
        const mk.IconStyle(scale: 1.0),
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
    const size = 90.0;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);

    // Ombre portée plus prononcée
    canvas.drawCircle(
      const Offset(size / 2 + 2, size / 2 + 3),
      30,
      Paint()
        ..color = Colors.black.withValues(alpha: 0.25)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
    );

    // Cercle extérieur (bordure dorée pour golden)
    if (isGolden) {
      canvas.drawCircle(
        Offset(size / 2, size / 2 - 5),
        32,
        Paint()
          ..color = const Color(0xFFFFD700)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 3,
      );
    }

    // Cercle principal avec gradient simulé
    final mainCircleCenter = Offset(size / 2, size / 2 - 5);
    canvas.drawCircle(mainCircleCenter, 30, Paint()..color = color);

    // Effet de brillance (highlight)
    canvas.drawCircle(
      Offset(size / 2 - 8, size / 2 - 13),
      8,
      Paint()..color = Colors.white.withValues(alpha: 0.3),
    );

    // Pointe du pin avec ombre
    final pinPath = Path()
      ..moveTo(size / 2 - 10, size / 2 + 23)
      ..lineTo(size / 2 + 10, size / 2 + 23)
      ..lineTo(size / 2, size / 2 + 40)
      ..close();
    canvas.drawPath(pinPath, Paint()..color = color);

    // Bordure blanche du cercle
    canvas.drawCircle(
      mainCircleCenter,
      30,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5,
    );

    // Badge étoile pour golden marker
    if (isGolden) {
      final starBadgeCenter = Offset(size / 2, size / 2 - 22);
      canvas.drawCircle(
        starBadgeCenter,
        10,
        Paint()..color = const Color(0xFFFFD700),
      );
      canvas.drawCircle(
        starBadgeCenter,
        10,
        Paint()
          ..color = Colors.white
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.5,
      );
      _paintText(canvas, '★', 14, Colors.white, starBadgeCenter);
    }

    // Icône artisan (marteau)
    if (!isGolden) {
      _paintIcon(canvas, Icons.handyman, 16,
          Colors.white.withValues(alpha: 0.9), Offset(size / 2, size / 2 - 18));
    }

    // Score avec fond semi-transparent
    final scoreY = isGolden ? size / 2 - 2 : size / 2 + 2;
    final scoreRect = Rect.fromCenter(
      center: Offset(size / 2, scoreY),
      width: 36,
      height: 20,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(scoreRect, const Radius.circular(10)),
      Paint()..color = Colors.black.withValues(alpha: 0.25),
    );
    _paintText(canvas, score, 13, Colors.white, Offset(size / 2, scoreY));

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

          // ── Badge compteur ──
          if (_mapReady)
            Positioned(
              top: MediaQuery.of(context).padding.top + 110,
              right: 16,
              child: const _ArtisanCountBadge(),
            ),

          // ── Bouton liste ──
          if (_mapReady)
            Positioned(
              top: MediaQuery.of(context).padding.top + 160,
              right: 16,
              child: _ListViewButton(
                onTap: () {
                  // TODO: Show artisan list view
                  Get.snackbar(
                    'Vue liste',
                    'Affichage de la liste des artisans',
                    snackPosition: SnackPosition.BOTTOM,
                  );
                },
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

      // ── FAB Ma position ──
      floatingActionButton: Padding(
        padding: EdgeInsets.only(bottom: _selectedArtisan != null ? 285 : 16),
        child: FloatingActionButton(
          heroTag: 'my_location_fab',
          backgroundColor: Colors.white,
          foregroundColor: AppColors.primary,
          onPressed: _centerOnUser,
          child: const Icon(Icons.my_location),
        ),
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
  bool _showSearch = false;

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
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.95),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.1),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: const Icon(Icons.arrow_back,
                      color: AppColors.primary, size: 22),
                ),
              ),
              const SizedBox(width: 12),

              // Barre de recherche ou titre
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 300),
                  child: _showSearch
                      ? Container(
                          key: const ValueKey('search'),
                          height: 40,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.95),
                            borderRadius: BorderRadius.circular(12),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.1),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: TextField(
                            controller: _searchCtrl,
                            autofocus: true,
                            decoration: InputDecoration(
                              hintText: 'Rechercher un artisan...',
                              hintStyle: TextStyle(
                                color:
                                    AppColors.textMuted.withValues(alpha: 0.6),
                                fontSize: 14,
                              ),
                              prefixIcon: const Icon(Icons.search,
                                  size: 20, color: AppColors.textMuted),
                              border: InputBorder.none,
                              contentPadding:
                                  const EdgeInsets.symmetric(vertical: 10),
                            ),
                            onSubmitted: (value) {
                              widget.onSearch(value);
                              setState(() => _showSearch = false);
                            },
                          ),
                        )
                      : Container(
                          key: const ValueKey('title'),
                          alignment: Alignment.centerLeft,
                          child: const Text(
                            'Artisans à proximité',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 17,
                              fontWeight: FontWeight.bold,
                              shadows: [
                                Shadow(blurRadius: 4, color: Colors.black54)
                              ],
                            ),
                          ),
                        ),
                ),
              ),
              const SizedBox(width: 8),

              // Bouton recherche
              GestureDetector(
                onTap: () => setState(() => _showSearch = !_showSearch),
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.95),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.1),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Icon(
                    _showSearch ? Icons.close : Icons.search,
                    color: AppColors.primary,
                    size: 22,
                  ),
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
                    setState(() => _selected = isAll ? null : label);
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
  const _CategoryChip(
      {required this.label,
      required this.icon,
      required this.isSelected,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected
              ? AppColors.primary
              : Colors.white.withValues(alpha: 0.95),
          borderRadius: BorderRadius.circular(20),
          border: isSelected
              ? Border.all(
                  color: Colors.white.withValues(alpha: 0.3), width: 1.5)
              : null,
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? AppColors.primary.withValues(alpha: 0.4)
                  : Colors.black.withValues(alpha: 0.12),
              blurRadius: isSelected ? 8 : 4,
              offset: Offset(0, isSelected ? 3 : 2),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon,
                size: 15, color: isSelected ? Colors.white : AppColors.primary),
            const SizedBox(width: 5),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
                color: isSelected ? Colors.white : AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Badge compteur ───────────────────────────────────────────────────────────

class _ArtisanCountBadge extends StatelessWidget {
  const _ArtisanCountBadge();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final count = Get.find<HomeController>().artisans.length;
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child:
                  const Icon(Icons.people, size: 14, color: AppColors.primary),
            ),
            const SizedBox(width: 8),
            Text(
              '$count artisan${count > 1 ? 's' : ''}',
              style: const TextStyle(
                fontSize: 13,
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

// ─── Bouton vue liste ─────────────────────────────────────────────────────────

class _ListViewButton extends StatelessWidget {
  final VoidCallback onTap;
  const _ListViewButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: const Icon(
          Icons.list_rounded,
          color: AppColors.primary,
          size: 24,
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
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 24,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Drag handle
            Container(
              width: 48,
              height: 5,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            const SizedBox(height: 20),

            Row(
              children: [
                // Avatar avec badge
                Stack(
                  children: [
                    CircleAvatar(
                      radius: 32,
                      backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                      child: Text(
                        Formatters.initial(artisan.name ?? artisan.phone),
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                    if (artisan.isGoldenMarker)
                      Positioned(
                        bottom: 0,
                        right: 0,
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFD700),
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: const Icon(
                            Icons.star,
                            color: Colors.white,
                            size: 12,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 16),

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
                                fontSize: 17,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ),
                          if (artisan.isGoldenMarker)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                gradient: const LinearGradient(
                                  colors: [
                                    Color(0xFFFFD700),
                                    Color(0xFFFFA500)
                                  ],
                                ),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.star,
                                      color: Colors.white, size: 12),
                                  SizedBox(width: 4),
                                  Text(
                                    'TOP',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: Colors.white,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                      if (artisan.trade != null) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.work_outline,
                              size: 14,
                              color: AppColors.textSecondary,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              artisan.trade!,
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                                fontSize: 14,
                              ),
                            ),
                          ],
                        ),
                      ],
                      if (artisan.distance != null) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(
                              Icons.location_on_outlined,
                              size: 14,
                              color: AppColors.textMuted,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              artisan.distance!,
                              style: const TextStyle(
                                color: AppColors.textMuted,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),

                const SizedBox(width: 12),
                ScoreNzassa(score: artisan.scoreNzassa, size: ScoreSize.large),
              ],
            ),
            const SizedBox(height: 24),

            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      onClose();
                      Get.toNamed(Routes.artisanProfile, arguments: artisan);
                    },
                    icon: const Icon(Icons.person_outline, size: 18),
                    label: const Text('Profil'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.primary,
                      side: const BorderSide(
                          color: AppColors.primary, width: 1.5),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
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
                        Routes.missionRequest,
                        arguments: {'artisan': artisan},
                      );
                    },
                    icon: const Icon(Icons.send_outlined, size: 18),
                    label: const Text('Demander devis'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      elevation: 2,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
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
