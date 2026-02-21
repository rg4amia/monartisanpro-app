import 'package:flutter/material.dart' hide Icon;
import 'package:yandex_maps_mapkit/image.dart' as mapkit_image;
import 'package:yandex_maps_mapkit/mapkit.dart';
import '../theme/app_colors.dart';

/// Helper class for creating custom map markers
class MapMarkersHelper {
  MapMarkersHelper._();

  /// Creates a custom marker icon from a widget
  static Future<mapkit_image.ImageProvider> createMarkerFromWidget(
    Widget widget, {
    double width = 48,
    double height = 48,
  }) async {
    // Note: This is a placeholder. In production, you would use a package like
    // 'screenshot' or render the widget to an image
    // For now, we'll use default markers
    throw UnimplementedError(
      'Custom marker creation requires additional implementation',
    );
  }

  /// Creates a user position marker widget
  static Widget createUserMarkerWidget({double size = 48}) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: AppColors.lightAccentPrimary,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: [
          BoxShadow(
            color: AppColors.lightAccentPrimary.withValues(alpha: 0.4),
            blurRadius: 8,
            spreadRadius: 2,
          ),
        ],
      ),
      child: const Icon(Icons.person, color: Colors.white, size: 24),
    );
  }

  /// Creates an artisan marker widget
  static Widget createArtisanMarkerWidget({
    required bool isNearby,
    double size = 40,
  }) {
    final color = isNearby ? AppColors.goldenMarker : AppColors.blueMarker;

    return Container(
      width: size,
      height: size + 8, // Extra height for pointer
      child: Stack(
        alignment: Alignment.topCenter,
        children: [
          // Pointer/pin shape
          CustomPaint(
            size: Size(size, size + 8),
            painter: _MarkerPinPainter(color: color),
          ),
          // Icon
          Positioned(
            top: 4,
            child: Container(
              width: size * 0.7,
              height: size * 0.7,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.construction, color: color, size: size * 0.5),
            ),
          ),
        ],
      ),
    );
  }

  /// Creates a cluster marker widget
  static Widget createClusterMarkerWidget({
    required int count,
    double size = 48,
  }) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppColors.clusterMarker,
            AppColors.clusterMarker.withValues(alpha: 0.8),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: [
          BoxShadow(
            color: AppColors.clusterMarker.withValues(alpha: 0.4),
            blurRadius: 8,
            spreadRadius: 2,
          ),
        ],
      ),
      child: Center(
        child: Text(
          count > 99 ? '99+' : count.toString(),
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  /// Gets the asset path for user position marker
  static String getUserPositionMarkerAsset() {
    return 'assets/markers/user_position.png';
  }

  /// Gets the asset path for artisan marker
  static String getArtisanMarkerAsset({required bool isNearby}) {
    return isNearby
        ? 'assets/markers/artisan_nearby.png'
        : 'assets/markers/artisan_regular.png';
  }

  /// Gets the asset path for cluster marker
  static String getClusterMarkerAsset() {
    return 'assets/markers/cluster.png';
  }
}

/// Custom painter for marker pin shape
class _MarkerPinPainter extends CustomPainter {
  final Color color;

  _MarkerPinPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    final shadowPaint = Paint()
      ..color = Colors.black.withValues(alpha: 0.2)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4);

    final path = Path();

    // Create pin shape
    final radius = size.width / 2;
    final center = Offset(size.width / 2, radius);

    // Circle part
    path.addOval(
      Rect.fromCenter(center: center, width: radius * 2, height: radius * 2),
    );

    // Pointer part
    path.moveTo(size.width / 2 - radius * 0.3, radius * 1.5);
    path.lineTo(size.width / 2, size.height);
    path.lineTo(size.width / 2 + radius * 0.3, radius * 1.5);
    path.close();

    // Draw shadow
    canvas.drawPath(path.shift(const Offset(0, 2)), shadowPaint);

    // Draw pin
    canvas.drawPath(path, paint);

    // Draw white border
    final borderPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    canvas.drawPath(path, borderPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
