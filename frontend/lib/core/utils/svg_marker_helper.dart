import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Helper to convert SVG assets to PNG bytes for map markers
class SvgMarkerHelper {
  /// Cache for loaded SVG markers
  static final Map<String, Uint8List> _cache = {};

  /// Load and convert SVG to PNG bytes for user marker
  static Future<Uint8List> getUserMarker({double size = 48}) async {
    final cacheKey = 'user_$size';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    final bytes = await _loadSvgAsBytes(
      'assets/markers/marker-fine.svg',
      size: size,
    );
    _cache[cacheKey] = bytes;
    return bytes;
  }

  /// Load and convert SVG to PNG bytes for artisan marker
  static Future<Uint8List> getArtisanMarker({
    required bool isNearby,
    double size = 48,
  }) async {
    final cacheKey = 'artisan_${isNearby ? 'nearby' : 'regular'}_$size';
    if (_cache.containsKey(cacheKey)) {
      return _cache[cacheKey]!;
    }

    final bytes = await _loadSvgAsBytes(
      'assets/markers/artisan-marker.svg',
      size: size,
      color: isNearby ? const Color(0xFFFBBF24) : const Color(0xFF5B7FFF),
    );
    _cache[cacheKey] = bytes;
    return bytes;
  }

  /// Load SVG from assets and convert to PNG bytes
  static Future<Uint8List> _loadSvgAsBytes(
    String assetPath, {
    required double size,
    Color? color,
  }) async {
    try {
      // Load SVG string from assets
      final svgString = await rootBundle.loadString(assetPath);

      // Parse SVG
      final pictureInfo = await vg.loadPicture(
        SvgStringLoader(svgString),
        null,
      );

      // Create a canvas to draw on
      final recorder = ui.PictureRecorder();
      final canvas = Canvas(recorder);

      // Apply color tint if specified
      if (color != null) {
        final paint = Paint()
          ..colorFilter = ColorFilter.mode(color, BlendMode.srcIn);
        canvas.saveLayer(null, paint);
      }

      // Scale to desired size
      final scale = size / pictureInfo.size.width;
      canvas.scale(scale);

      // Draw the SVG
      canvas.drawPicture(pictureInfo.picture);

      if (color != null) {
        canvas.restore();
      }

      // Convert to image
      final picture = recorder.endRecording();
      final img = await picture.toImage(
        size.toInt(),
        (size * (pictureInfo.size.height / pictureInfo.size.width)).toInt(),
      );

      // Convert to PNG bytes
      final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
      return byteData!.buffer.asUint8List();
    } catch (e) {
      debugPrint('Error loading SVG marker: $e');
      // Fallback: return a simple colored circle
      return _createFallbackMarker(size: size, color: color);
    }
  }

  /// Create a simple fallback marker if SVG loading fails
  static Future<Uint8List> _createFallbackMarker({
    required double size,
    Color? color,
  }) async {
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final paint = Paint()
      ..color = color ?? Colors.blue
      ..style = PaintingStyle.fill;

    // Draw a simple circle
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2, paint);

    // Draw white border
    paint
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawCircle(Offset(size / 2, size / 2), size / 2 - 1, paint);

    final picture = recorder.endRecording();
    final img = await picture.toImage(size.toInt(), size.toInt());
    final byteData = await img.toByteData(format: ui.ImageByteFormat.png);
    return byteData!.buffer.asUint8List();
  }

  /// Clear the cache
  static void clearCache() {
    _cache.clear();
  }
}
